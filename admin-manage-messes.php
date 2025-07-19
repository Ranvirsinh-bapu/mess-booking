<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

include('admin-header.php');
$admin_username = $_SESSION['admin_username'];
$conn = getDBConnection();

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_mess' || $action === 'edit_mess') {
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $contact = trim($_POST['contact']);
        $daily_capacity = intval($_POST['daily_capacity']);
        $status = $_POST['status'];
        $capacity_enabled = isset($_POST['capacity_enabled']) ? 1 : 0;

        if (empty($name) || empty($location) || empty($contact)) {
            $message = 'Name, Location, and Contact are required.';
            $message_type = 'danger';
        } else {
            if ($action === 'add_mess') {
                $stmt = $conn->prepare("INSERT INTO mess (name, location, contact, daily_capacity, status, capacity_enabled) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisi", $name, $location, $contact, $daily_capacity, $status, $capacity_enabled);
                if ($stmt->execute()) {
                    $message = 'Mess added successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding mess: ' . $conn->error;
                    $message_type = 'danger';
                }
            } elseif ($action === 'edit_mess') {
                $mess_id = intval($_POST['mess_id']);
                $stmt = $conn->prepare("UPDATE mess SET name = ?, location = ?, contact = ?, daily_capacity = ?, status = ?, capacity_enabled = ? WHERE id = ?");
                $stmt->bind_param("sssisii", $name, $location, $contact, $daily_capacity, $status, $capacity_enabled, $mess_id);
                if ($stmt->execute()) {
                    $message = 'Mess updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating mess: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'delete_mess') {
        $mess_id = intval($_POST['mess_id']);
        $stmt = $conn->prepare("DELETE FROM mess WHERE id = ?");
        $stmt->bind_param("i", $mess_id);
        if ($stmt->execute()) {
            $message = 'Mess deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting mess: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    // Store message in session to display after redirect
    $_SESSION['admin_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: admin-manage-messes.php');
    exit;
}

// Retrieve messages from session
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message']['text'];
    $message_type = $_SESSION['admin_message']['type'];
    unset($_SESSION['admin_message']);
}

// --- Fetch Data for Display ---
// Fetch all messes
$mess_list_query = $conn->query("SELECT * FROM mess ORDER BY name");

$conn->close();
?>
    <style>
       
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>


<div class="container mt-5">
    <div class="row">
        <h1 class="mb-4">Manage Messes</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add New Mess</h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-messes.php" method="post">
                    <input type="hidden" name="action" value="add_mess">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Mess Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                        <div class="col-md-4">
                            <label for="contact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="contact" name="contact" required>
                        </div>
                        <div class="col-md-4">
                            <label for="daily_capacity" class="form-label">Daily Capacity</label>
                            <input type="number" class="form-control" id="daily_capacity" name="daily_capacity" value="100" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="capacity_enabled" name="capacity_enabled" checked>
                                <label class="form-check-label" for="capacity_enabled">
                                    Enable Daily Capacity Limit
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i> Add Mess</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0">Existing Messes</h4>
            </div>
            <div class="card-body">
                <?php if ($mess_list_query->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($mess = $mess_list_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mess['id']); ?></td>
                                <td><?php echo htmlspecialchars($mess['name']); ?></td>
                                <td><?php echo htmlspecialchars($mess['location']); ?></td>
                                <td><?php echo htmlspecialchars($mess['contact']); ?></td>
                                <td>
                                    <?php 
                                        echo htmlspecialchars($mess['daily_capacity']); 
                                        echo $mess['capacity_enabled'] ? '' : ' (Unlimited)';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $mess['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($mess['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info me-2" 
                                            data-bs-toggle="modal" data-bs-target="#editMessModal"
                                            data-id="<?php echo $mess['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($mess['name']); ?>"
                                            data-location="<?php echo htmlspecialchars($mess['location']); ?>"
                                            data-contact="<?php echo htmlspecialchars($mess['contact']); ?>"
                                            data-dailycapacity="<?php echo htmlspecialchars($mess['daily_capacity']); ?>"
                                            data-status="<?php echo htmlspecialchars($mess['status']); ?>"
                                            data-capacityenabled="<?php echo htmlspecialchars($mess['capacity_enabled']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form action="admin-manage-messes.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this mess? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_mess">
                                        <input type="hidden" name="mess_id" value="<?php echo $mess['id']; ?>">
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
                    <div class="alert alert-info mb-0">No messes found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Mess Modal -->
    <div class="modal fade" id="editMessModal" tabindex="-1" aria-labelledby="editMessModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-messes.php" method="post">
                    <input type="hidden" name="action" value="edit_mess">
                    <input type="hidden" name="mess_id" id="editMessId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMessModalLabel">Edit Mess Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editName" class="form-label">Mess Name</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="editLocation" name="location" required>
                        </div>
                        <div class="mb-3">
                            <label for="editContact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="editContact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDailyCapacity" class="form-label">Daily Capacity</label>
                            <input type="number" class="form-control" id="editDailyCapacity" name="daily_capacity" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editCapacityEnabled" name="capacity_enabled">
                                <label class="form-check-label" for="editCapacityEnabled">
                                    Enable Daily Capacity Limit
                                </label>
                            </div>
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
</div> <!-- End container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate edit mess modal
        var editMessModal = document.getElementById('editMessModal');
        editMessModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var location = button.getAttribute('data-location');
            var contact = button.getAttribute('data-contact');
            var dailyCapacity = button.getAttribute('data-dailycapacity');
            var status = button.getAttribute('data-status');
            var capacityEnabled = button.getAttribute('data-capacityenabled');

            var modalIdInput = editMessModal.querySelector('#editMessId');
            var modalNameInput = editMessModal.querySelector('#editName');
            var modalLocationInput = editMessModal.querySelector('#editLocation');
            var modalContactInput = editMessModal.querySelector('#editContact');
            var modalDailyCapacityInput = editMessModal.querySelector('#editDailyCapacity');
            var modalStatusSelect = editMessModal.querySelector('#editStatus');
            var modalCapacityEnabledCheckbox = editMessModal.querySelector('#editCapacityEnabled');

            modalIdInput.value = id;
            modalNameInput.value = name;
            modalLocationInput.value = location;
            modalContactInput.value = contact;
            modalDailyCapacityInput.value = dailyCapacity;
            modalStatusSelect.value = status;
            modalCapacityEnabledCheckbox.checked = (capacityEnabled === '1');
        });
    </script>
<?php
require_once 'footer.php';
?>