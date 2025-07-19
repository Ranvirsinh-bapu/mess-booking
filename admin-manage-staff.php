<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'];
$conn = getDBConnection();

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff' || $action === 'edit_staff') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $mess_id = !empty($_POST['mess_id']) ? intval($_POST['mess_id']) : null;
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email)) {
            $message = 'Username and Email are required.';
            $message_type = 'danger';
        } else {
            if ($action === 'add_staff') {
                if (empty($password)) {
                    $message = 'Password is required for new staff.';
                    $message_type = 'danger';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO staff (username, password, email, mess_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $username, $hashed_password, $email, $mess_id);
                    if ($stmt->execute()) {
                        $message = 'Staff member added successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding staff: ' . $conn->error;
                        $message_type = 'danger';
                    }
                }
            } elseif ($action === 'edit_staff') {
                $staff_id = intval($_POST['staff_id']);
                $update_sql = "UPDATE staff SET username = ?, email = ?, mess_id = ?";
                $types = "ssi";
                $params = [$username, $email, $mess_id];

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql .= ", password = ?";
                    $types .= "s";
                    $params[] = $hashed_password;
                }
                $update_sql .= " WHERE id = ?";
                $types .= "i";
                $params[] = $staff_id;

                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = 'Staff member updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating staff: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'delete_staff') {
        $staff_id = intval($_POST['staff_id']);
        $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->bind_param("i", $staff_id);
        if ($stmt->execute()) {
            $message = 'Staff member deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting staff: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    // Store message in session to display after redirect
    $_SESSION['admin_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: admin-manage-staff.php');
    exit;
}

// Retrieve messages from session
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message']['text'];
    $message_type = $_SESSION['admin_message']['type'];
    unset($_SESSION['admin_message']);
}

// --- Fetch Data for Display ---
// Fetch all staff members
$staff_list_query = $conn->query("
    SELECT s.id, s.username, s.email, s.mess_id, m.name as mess_name 
    FROM staff s 
    LEFT JOIN mess m ON s.mess_id = m.id 
    ORDER BY s.username
");

// Fetch all active messes for dropdown
$messes_query = $conn->query("SELECT id, name FROM mess WHERE status = 'active' ORDER BY name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #2c3e50;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #34495e;
            padding-top: 56px; /* Height of navbar */
            color: white;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            transition: background-color 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #2c3e50;
            color: white;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">PU Mess Admin</a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user-shield"></i> Welcome, <?php echo htmlspecialchars($admin_username); ?>
                </span>
                <a href="logout.php?type=admin" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar">
        <div class="d-flex flex-column p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-book me-2"></i> Manage Bookings
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-utensils me-2"></i> Manage Messes
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-dollar-sign me-2"></i> Manage Pricing
                    </a>
                </li>
                <li>
                    <a href="admin-manage-staff.php" class="nav-link active">
                        <i class="fas fa-users-cog me-2"></i> Manage Staff
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-cogs me-2"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content">
        <h1 class="mb-4">Manage Staff</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add New Staff</h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-staff.php" method="post">
                    <input type="hidden" name="action" value="add_staff">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mess_id" class="form-label">Assign Mess (Optional)</label>
                            <select class="form-select" id="mess_id" name="mess_id">
                                <option value="">-- No Mess Assigned --</option>
                                <?php 
                                // Reset pointer for messes_query
                                if ($messes_query->num_rows > 0) {
                                    $messes_query->data_seek(0);
                                    while($mess = $messes_query->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $mess['id']; ?>"><?php echo htmlspecialchars($mess['name']); ?></option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i> Add Staff</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0">Existing Staff Members</h4>
            </div>
            <div class="card-body">
                <?php if ($staff_list_query->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Assigned Mess</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($staff = $staff_list_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($staff['id']); ?></td>
                                <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td><?php echo htmlspecialchars($staff['mess_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info me-2" 
                                            data-bs-toggle="modal" data-bs-target="#editStaffModal"
                                            data-id="<?php echo $staff['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($staff['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                            data-messid="<?php echo htmlspecialchars($staff['mess_id'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form action="admin-manage-staff.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No staff members found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-staff.php" method="post">
                    <input type="hidden" name="action" value="edit_staff">
                    <input type="hidden" name="staff_id" id="editStaffId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStaffModalLabel">Edit Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="editPassword" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="editMessId" class="form-label">Assign Mess</label>
                            <select class="form-select" id="editMessId" name="mess_id">
                                <option value="">-- No Mess Assigned --</option>
                                <?php 
                                // Reset pointer for messes_query
                                if ($messes_query->num_rows > 0) {
                                    $messes_query->data_seek(0);
                                    while($mess = $messes_query->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $mess['id']; ?>"><?php echo htmlspecialchars($mess['name']); ?></option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate edit staff modal
        var editStaffModal = document.getElementById('editStaffModal');
        editStaffModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var email = button.getAttribute('data-email');
            var messId = button.getAttribute('data-messid');

            var modalIdInput = editStaffModal.querySelector('#editStaffId');
            var modalUsernameInput = editStaffModal.querySelector('#editUsername');
            var modalEmailInput = editStaffModal.querySelector('#editEmail');
            var modalMessIdSelect = editStaffModal.querySelector('#editMessId');

            modalIdInput.value = id;
            modalUsernameInput.value = username;
            modalEmailInput.value = email;
            modalMessIdSelect.value = messId; // Set the selected option
        });
    </script>
</body>
</html>
