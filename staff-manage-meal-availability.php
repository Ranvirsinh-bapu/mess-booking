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
$mess_daily_capacity = 0;
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

// Handle meal capacity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_meal_capacity') {
    $meal_type = trim($_POST['meal_type']);
    $meal_capacity = intval($_POST['meal_capacity']);
    
    if (empty($meal_type) || $meal_capacity < 0) {
        $message = 'Valid meal type and capacity are required.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO mess_meal_capacity (mess_id, date, meal_type, capacity) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE capacity = ?, updated_at = NOW()");
        $stmt->bind_param("issii", $staff_mess_id, $today_date, $meal_type, $meal_capacity, $meal_capacity);
        if ($stmt->execute()) {
            $message = ucfirst($meal_type) . ' capacity updated to ' . $meal_capacity . ' successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating capacity: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    
    $_SESSION['staff_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: staff-manage-meal-availability.php');
    exit;
}

// Handle meal limits update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_meal_limits') {
    $meal_type = trim($_POST['meal_type']);
    $max_persons_per_booking = intval($_POST['max_persons_per_booking']);
    $advance_booking_hours = intval($_POST['advance_booking_hours']);
    $cutoff_hours = intval($_POST['cutoff_hours']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    
    if (empty($meal_type)) {
        $message = 'Meal type is required.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO mess_meal_limits (mess_id, meal_type, max_persons_per_booking, advance_booking_hours, cutoff_hours, start_time, end_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                max_persons_per_booking = ?, 
                advance_booking_hours = ?, 
                cutoff_hours = ?, 
                start_time = ?, 
                end_time = ?, 
                updated_at = NOW()
        ");
        $stmt->bind_param("isiisssiisss", 
            $staff_mess_id, $meal_type, $max_persons_per_booking, $advance_booking_hours, $cutoff_hours, $start_time, $end_time,
            $max_persons_per_booking, $advance_booking_hours, $cutoff_hours, $start_time, $end_time
        );
        if ($stmt->execute()) {
            $message = ucfirst($meal_type) . ' limits updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating limits: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    
    $_SESSION['staff_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: staff-manage-meal-availability.php');
    exit;
}

// Handle daily limits update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_daily_limits') {
    $max_bookings_per_user = intval($_POST['max_bookings_per_user']);
    $max_total_persons = intval($_POST['max_total_persons']);
    $emergency_contact = trim($_POST['emergency_contact']);
    
    $stmt = $conn->prepare("
        INSERT INTO mess_daily_limits (mess_id, date, max_bookings_per_user, max_total_persons, emergency_contact) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            max_bookings_per_user = ?, 
            max_total_persons = ?, 
            emergency_contact = ?, 
            updated_at = NOW()
    ");
    $stmt->bind_param("isiisiiis", 
        $staff_mess_id, $today_date, $max_bookings_per_user, $max_total_persons, $emergency_contact,
        $max_bookings_per_user, $max_total_persons, $emergency_contact
    );
    if ($stmt->execute()) {
        $message = 'Daily limits updated successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error updating daily limits: ' . $conn->error;
        $message_type = 'danger';
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

// Fetch meal capacities for today
$meal_capacities = [];
$capacity_query = $conn->prepare("SELECT meal_type, capacity FROM mess_meal_capacity WHERE mess_id = ? AND date = ?");
$capacity_query->bind_param("is", $staff_mess_id, $today_date);
$capacity_query->execute();
$capacity_result = $capacity_query->get_result();
while ($row = $capacity_result->fetch_assoc()) {
    $meal_capacities[$row['meal_type']] = $row['capacity'];
}

// Fetch meal limits
$meal_limits = [];
$limits_query = $conn->prepare("SELECT * FROM mess_meal_limits WHERE mess_id = ?");
$limits_query->bind_param("i", $staff_mess_id);
$limits_query->execute();
$limits_result = $limits_query->get_result();
while ($row = $limits_result->fetch_assoc()) {
    $meal_limits[$row['meal_type']] = $row;
}

// Fetch daily limits
$daily_limits = [];
$daily_limits_query = $conn->prepare("SELECT * FROM mess_daily_limits WHERE mess_id = ? AND date = ?");
$daily_limits_query->bind_param("is", $staff_mess_id, $today_date);
$daily_limits_query->execute();
$daily_limits_result = $daily_limits_query->get_result();
if ($daily_limits_row = $daily_limits_result->fetch_assoc()) {
    $daily_limits = $daily_limits_row;
}

// Default all meals to available if no entry exists
$meal_types_to_manage = ['breakfast', 'lunch', 'dinner'];
foreach ($meal_types_to_manage as $type) {
    if (!isset($meal_availability[$type])) {
        $meal_availability[$type] = true; // Default to available
    }
    if (!isset($meal_capacities[$type])) {
        $meal_capacities[$type] = intval($mess_daily_capacity / 3); // Default to 1/3 of daily capacity
    }
    if (!isset($meal_limits[$type])) {
        $meal_limits[$type] = [
            'max_persons_per_booking' => 10,
            'advance_booking_hours' => 24,
            'cutoff_hours' => 2,
            'start_time' => ($type == 'breakfast' ? '07:00' : ($type == 'lunch' ? '12:00' : '19:00')),
            'end_time' => ($type == 'breakfast' ? '10:00' : ($type == 'lunch' ? '15:00' : '22:00'))
        ];
    }
}

// Set default daily limits
if (empty($daily_limits)) {
    $daily_limits = [
        'max_bookings_per_user' => 3,
        'max_total_persons' => $mess_daily_capacity,
        'emergency_contact' => ''
    ];
}

// Fetch today's bookings for capacity overview
$used_capacity_query = $conn->prepare("SELECT COALESCE(SUM(persons), 0) as used_capacity FROM bookings WHERE mess_id = ? AND booking_date = ? AND booking_status IN ('active', 'completed')");
$used_capacity_query->bind_param("is", $staff_mess_id, $today_date);
$used_capacity_query->execute();
$used_capacity = $used_capacity_query->get_result()->fetch_assoc()['used_capacity'];

// Fetch meal-wise bookings for today
$meal_bookings = [];
$meal_booking_query = $conn->prepare("
    SELECT 
        CASE 
            WHEN coupon_type = 'breakfast' THEN 'breakfast'
            WHEN coupon_type = 'lunch' THEN 'lunch' 
            WHEN coupon_type = 'dinner' THEN 'dinner'
            WHEN coupon_type = 'full_day' THEN 'full_day'
            ELSE coupon_type
        END as meal_category,
        SUM(persons) as total_persons,
        COUNT(*) as booking_count
    FROM bookings 
    WHERE mess_id = ? AND booking_date = ? AND booking_status IN ('active', 'completed')
    GROUP BY meal_category
");
$meal_booking_query->bind_param("is", $staff_mess_id, $today_date);
$meal_booking_query->execute();
$meal_booking_result = $meal_booking_query->get_result();

while ($row = $meal_booking_result->fetch_assoc()) {
    $meal_bookings[$row['meal_category']] = [
        'persons' => $row['total_persons'],
        'bookings' => $row['booking_count']
    ];
}

// Calculate individual meal usage (including full day bookings)
$meal_usage = [];
foreach ($meal_types_to_manage as $meal_type) {
    $usage = $meal_bookings[$meal_type]['persons'] ?? 0;
    // Add full day bookings to each meal
    if (isset($meal_bookings['full_day'])) {
        $usage += $meal_bookings['full_day']['persons'];
    }
    $meal_usage[$meal_type] = $usage;
}

$available_capacity = max(0, $mess_daily_capacity - $used_capacity);

// Check for limit violations
$limit_violations = [];
foreach ($meal_types_to_manage as $meal_type) {
    $usage = $meal_usage[$meal_type];
    $capacity = $meal_capacities[$meal_type];
    $percentage = $capacity > 0 ? ($usage / $capacity) * 100 : 0;
    
    if ($percentage >= 100) {
        $limit_violations[] = ucfirst($meal_type) . ' is at full capacity!';
    } elseif ($percentage >= 90) {
        $limit_violations[] = ucfirst($meal_type) . ' is nearly full (' . round($percentage, 1) . '%)';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meal Availability & Limits - Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            overflow-y: auto;
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
            transition: transform 0.3s ease;
        }
        .card-dashboard:hover {
            transform: translateY(-2px);
        }
        .card-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .progress-custom {
            height: 25px;
            border-radius: 15px;
        }
        .meal-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .meal-card.breakfast { border-left-color: #ffc107; }
        .meal-card.lunch { border-left-color: #28a745; }
        .meal-card.dinner { border-left-color: #17a2b8; }
        .meal-card:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .limits-card {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            color: white;
        }
        .violation-alert {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">PU Mess Staff</a>
            <button class="navbar-toggler d-md-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
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

    <div class="sidebar" id="sidebar">
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
        <h1 class="mb-4">Manage Meal Availability, Capacity & Limits</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Limit Violations Alert -->
        <?php if (!empty($limit_violations)): ?>
            <div class="alert violation-alert alert-dismissible fade show" role="alert">
                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Capacity Alerts</h6>
                <ul class="mb-0">
                    <?php foreach ($limit_violations as $violation): ?>
                        <li><?php echo htmlspecialchars($violation); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Overview Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-dashboard text-white bg-primary">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title">Total Capacity</h6>
                            <h2 class="mb-0"><?php echo htmlspecialchars($mess_daily_capacity); ?></h2>
                            <small>Daily capacity</small>
                        </div>
                        <i class="fas fa-chart-bar card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard text-white bg-success">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title">Used Today</h6>
                            <h2 class="mb-0"><?php echo htmlspecialchars($used_capacity); ?></h2>
                            <small><?php echo round(($used_capacity / max($mess_daily_capacity, 1)) * 100, 1); ?>% utilized</small>
                        </div>
                        <i class="fas fa-users card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard text-white bg-info">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title">Available</h6>
                            <h2 class="mb-0"><?php echo htmlspecialchars($available_capacity); ?></h2>
                            <small>Spots remaining</small>
                        </div>
                        <i class="fas fa-calendar-alt card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard limits-card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title">Max Per User</h6>
                            <h2 class="mb-0"><?php echo htmlspecialchars($daily_limits['max_bookings_per_user']); ?></h2>
                            <small>Bookings per day</small>
                        </div>
                        <i class="fas fa-user-clock card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meal-wise Capacity Overview -->
        <div class="row mb-4">
            <?php foreach ($meal_types_to_manage as $index => $meal_type): 
                $usage = $meal_usage[$meal_type];
                $capacity = $meal_capacities[$meal_type];
                $percentage = $capacity > 0 ? round(($usage / $capacity) * 100, 1) : 0;
                $card_classes = ['breakfast', 'lunch', 'dinner'];
                $icons = ['fas fa-coffee', 'fas fa-utensils', 'fas fa-moon'];
                $limits = $meal_limits[$meal_type];
            ?>
            <div class="col-md-4">
                <div class="card card-dashboard meal-card <?php echo $card_classes[$index]; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <i class="<?php echo $icons[$index]; ?> me-2"></i>
                                <?php echo ucfirst($meal_type); ?>
                            </h5>
                            <span class="badge <?php echo $meal_availability[$meal_type] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $meal_availability[$meal_type] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small>Usage: <?php echo $usage; ?> / <?php echo $capacity; ?></small>
                                <small><?php echo $percentage; ?>%</small>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar <?php echo $percentage > 90 ? 'bg-danger' : ($percentage > 70 ? 'bg-warning' : 'bg-success'); ?>" 
                                     style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted">Max/Booking</small>
                                <div class="fw-bold"><?php echo $limits['max_persons_per_booking']; ?></div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Cutoff</small>
                                <div class="fw-bold"><?php echo $limits['cutoff_hours']; ?>h</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Remaining</small>
                                <div class="fw-bold"><?php echo max(0, $capacity - $usage); ?></div>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted">Timing</small>
                                <div class="fw-bold"><?php echo date('H:i', strtotime($limits['start_time'])) . '-' . date('H:i', strtotime($limits['end_time'])); ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Advance</small>
                                <div class="fw-bold"><?php echo $limits['advance_booking_hours']; ?>h</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Availability Management -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-toggle-on me-2"></i>Meal Availability Control</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Toggle the switch to mark a meal type as available or unavailable for today.</p>
                <div class="list-group">
                    <?php foreach ($meal_types_to_manage as $meal_type): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="<?php echo ['fas fa-coffee', 'fas fa-utensils', 'fas fa-moon'][array_search($meal_type, $meal_types_to_manage)]; ?> me-3 text-muted"></i>
                            <h5 class="mb-0"><?php echo htmlspecialchars(ucfirst($meal_type)); ?></h5>
                        </div>
                        <form action="staff-manage-meal-availability.php" method="post" class="d-inline-flex align-items-center">
                            <input type="hidden" name="action" value="toggle_availability">
                            <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggle-<?php echo htmlspecialchars($meal_type); ?>" 
                                       name="is_available" value="1" <?php echo $meal_availability[$meal_type] ? 'checked' : ''; ?> 
                                       onchange="this.form.submit()">
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

        <!-- Capacity Management -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>Meal Capacity Settings</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Set individual capacity limits for each meal type.</p>
                <div class="row">
                    <?php foreach ($meal_types_to_manage as $meal_type): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo ucfirst($meal_type); ?> Capacity</h6>
                                <form action="staff-manage-meal-availability.php" method="post">
                                    <input type="hidden" name="action" value="update_meal_capacity">
                                    <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="meal_capacity" 
                                               value="<?php echo htmlspecialchars($meal_capacities[$meal_type]); ?>" 
                                               min="0" max="<?php echo $mess_daily_capacity; ?>" required>
                                        <button class="btn btn-outline-primary" type="submit">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Meal Limits Management -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Meal Booking Limits & Timing</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Configure booking limits, timing, and restrictions for each meal type.</p>
                <div class="row">
                    <?php foreach ($meal_types_to_manage as $meal_type): 
                        $limits = $meal_limits[$meal_type];
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="card border">
                            <div class="card-header">
                                <h6 class="mb-0"><?php echo ucfirst($meal_type); ?> Limits</h6>
                            </div>
                            <div class="card-body">
                                <form action="staff-manage-meal-availability.php" method="post">
                                    <input type="hidden" name="action" value="update_meal_limits">
                                    <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Max Persons per Booking</label>
                                        <input type="number" class="form-control" name="max_persons_per_booking" 
                                               value="<?php echo htmlspecialchars($limits['max_persons_per_booking']); ?>" 
                                               min="1" max="50" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Advance Booking (Hours)</label>
                                        <input type="number" class="form-control" name="advance_booking_hours" 
                                               value="<?php echo htmlspecialchars($limits['advance_booking_hours']); ?>" 
                                               min="1" max="168" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Booking Cutoff (Hours)</label>
                                        <input type="number" class="form-control" name="cutoff_hours" 
                                               value="<?php echo htmlspecialchars($limits['cutoff_hours']); ?>" 
                                               min="0" max="24" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Start Time</label>
                                            <input type="time" class="form-control" name="start_time" 
                                                   value="<?php echo htmlspecialchars($limits['start_time']); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">End Time</label>
                                            <input type="time" class="form-control" name="end_time" 
                                                   value="<?php echo htmlspecialchars($limits['end_time']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Update Limits
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Daily Limits Management -->
        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Daily Booking Limits</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Set overall daily limits and emergency contact information.</p>
                <form action="staff-manage-meal-availability.php" method="post">
                    <input type="hidden" name="action" value="update_daily_limits">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Bookings per User</label>
                            <input type="number" class="form-control" name="max_bookings_per_user" 
                                   value="<?php echo htmlspecialchars($daily_limits['max_bookings_per_user']); ?>" 
                                   min="1" max="10" required>
                            <small class="text-muted">Maximum bookings one user can make per day</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Total Persons</label>
                            <input type="number" class="form-control" name="max_total_persons" 
                                   value="<?php echo htmlspecialchars($daily_limits['max_total_persons']); ?>" 
                                   min="1" max="<?php echo $mess_daily_capacity * 2; ?>" required>
                            <small class="text-muted">Maximum total persons for the day</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Contact</label>
                            <input type="tel" class="form-control" name="emergency_contact" 
                                   value="<?php echo htmlspecialchars($daily_limits['emergency_contact']); ?>" 
                                   placeholder="+91 9876543210">
                            <small class="text-muted">Contact for emergency situations</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Update Daily Limits
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
        });

        // Auto-refresh every 10 minutes
        setTimeout(function() {
            location.reload();
        }, 600000);
    </script>
</body>
</html>
