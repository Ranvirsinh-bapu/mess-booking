<?php
session_start();
require_once 'config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.php');
    exit;
}

$staff_username = $_SESSION['staff_username'];
$staff_mess_id = $_SESSION['staff_mess_id'];

$conn = getDBConnection();

$message = '';
$message_type = ''; // 'success' or 'danger'
$today_date = date('Y-m-d');

// Fetch mess name for the staff's assigned mess
$mess_name = 'N/A';
if ($staff_mess_id) {
    $mess_query = $conn->prepare("SELECT name, daily_capacity FROM mess WHERE id = ?");
    $mess_query->bind_param("i", $staff_mess_id);
    $mess_query->execute();
    $mess_result = $mess_query->get_result();
    if ($mess_row = $mess_result->fetch_assoc()) {
        $mess_name = $mess_row['name'];
        $mess_daily_capacity = $mess_row['daily_capacity'];
    }
}

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_availability') {
    $meal_type = trim($_POST['meal_type']);
    $is_available = intval($_POST['is_available']); // 0 or 1

    if (empty($meal_type)) {
        $message = 'Meal type is required.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO mess_meal_availability (mess_id, date, meal_type, is_available) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_available = ?, updated_at = NOW()");
        $stmt->bind_param("issii", $staff_mess_id, $today_date, $meal_type, $is_available, $is_available);
        if ($stmt->execute()) {
            $status_text = $is_available ? 'available' : 'unavailable';
            $message = ucfirst($meal_type) . ' marked as ' . $status_text . ' successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating availability: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    $_SESSION['staff_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: staff-manage-meal-availability.php');
    exit;
}

// Retrieve messages from session
if (isset($_SESSION['staff_message'])) {
    $message = $_SESSION['staff_message']['text'];
    $message_type = $_SESSION['staff_message']['type'];
    unset($_SESSION['staff_message']);
}

// Fetch current meal availability for today for this mess
$meal_availability = [];
$availability_query = $conn->prepare("SELECT meal_type, is_available FROM mess_meal_availability WHERE mess_id = ? AND date = ?");
$availability_query->bind_param("is", $staff_mess_id, $today_date);
$availability_query->execute();
$result = $availability_query->get_result();
while ($row = $result->fetch_assoc()) {
    $meal_availability[$row['meal_type']] = (bool)$row['is_available'];
}

// Default all meals to available if no entry exists
$meal_types_to_manage = ['breakfast', 'lunch', 'dinner'];
foreach ($meal_types_to_manage as $type) {
    if (!isset($meal_availability[$type])) {
        $meal_availability[$type] = true; // Default to available
    }
}

// Fetch today's bookings for capacity overview
$used_capacity_query = $conn->prepare("SELECT COALESCE(SUM(persons), 0) as used_capacity FROM bookings WHERE mess_id = ? AND booking_date = ? AND booking_status IN ('active', 'completed')");
$used_capacity_query->bind_param("is", $staff_mess_id, $today_date);
$used_capacity_query->execute();
$used_capacity = $used_capacity_query->get_result()->fetch_assoc()['used_capacity'];
$available_capacity = max(0, $mess_daily_capacity - $used_capacity);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meal Availability - Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #28a745; /* Green for staff */
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #218838;
            padding-top: 56px; /* Height of navbar */
            color: white;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            transition: background-color 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #1e7e34;
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
            <a class="navbar-brand" href="#">PU Mess Staff</a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($staff_username); ?> (Mess: <?php echo htmlspecialchars($mess_name); ?>)
                </span>
                <a href="logout.php?type=staff" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar">
        <div class="d-flex flex-column p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="staff-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="staff-manage-meal-availability.php" class="nav-link active">
                        <i class="fas fa-calendar-check me-2"></i> Meal Availability
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-qrcode me-2"></i> Scan QR (Future)
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-history me-2"></i> Booking History
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content">
        <h1 class="mb-4">Manage Meal Availability</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card card-dashboard text-white bg-primary">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Today's Capacity (<?php echo htmlspecialchars($mess_name); ?>)</h5>
                            <p class="card-text fs-2"><?php echo htmlspecialchars($used_capacity); ?> / <?php echo htmlspecialchars($mess_daily_capacity); ?></p>
                            <p class="card-text">Available: <?php echo htmlspecialchars($available_capacity); ?> spots</p>
                        </div>
                        <i class="fas fa-chart-bar card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-dashboard text-white bg-info">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Date</h5>
                            <p class="card-text fs-2"><?php echo date('d M Y'); ?></p>
                            <p class="card-text">Today is <?php echo date('l'); ?></p>
                        </div>
                        <i class="fas fa-calendar-alt card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0">Set Meal Availability for Today</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Toggle the switch to mark a meal type as available or unavailable for today at <?php echo htmlspecialchars($mess_name); ?>.</p>
                <div class="list-group">
                    <?php foreach ($meal_types_to_manage as $meal_type): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <h5><?php echo htmlspecialchars(ucfirst($meal_type)); ?></h5>
                        <form action="staff-manage-meal-availability.php" method="post" class="d-inline-flex align-items-center">
                            <input type="hidden" name="action" value="toggle_availability">
                            <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggle-<?php echo htmlspecialchars($meal_type); ?>" name="is_available" value="1" <?php echo $meal_availability[$meal_type] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="toggle-<?php echo htmlspecialchars($meal_type); ?>">
                                    <?php echo $meal_availability[$meal_type] ? 'Available' : 'Unavailable'; ?>
                                </label>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
