<?php
session_start();
require_once 'config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.php');
    exit;
}

$staff_username = $_SESSION['staff_username'];
$staff_mess_id = $_SESSION['staff_mess_id']; // Get the mess ID assigned to the staff

$conn = getDBConnection();

// Fetch mess name for the staff's assigned mess
$mess_name = 'N/A';
if ($staff_mess_id) {
    $mess_query = $conn->prepare("SELECT name FROM mess WHERE id = ?");
    $mess_query->bind_param("i", $staff_mess_id);
    $mess_query->execute();
    $mess_result = $mess_query->get_result();
    if ($mess_row = $mess_result->fetch_assoc()) {
        $mess_name = $mess_row['name'];
    }
}

// Handle booking check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_in') {
    $booking_id_to_check = trim($_POST['booking_id']);
    
    // Validate booking ID and ensure it belongs to the staff's mess and is for today
    $check_stmt = $conn->prepare("
        SELECT id, booking_status, mess_id, booking_date 
        FROM bookings 
        WHERE booking_id = ?
    ");
    $check_stmt->bind_param("s", $booking_id_to_check);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $booking_to_update = $check_result->fetch_assoc();

    if ($booking_to_update) {
        if ($booking_to_update['mess_id'] != $staff_mess_id && $staff_mess_id !== null) {
            $_SESSION['check_in_message'] = ['type' => 'danger', 'text' => 'Error: This booking is not for your assigned mess.'];
        } elseif ($booking_to_update['booking_date'] != date('Y-m-d')) {
            $_SESSION['check_in_message'] = ['type' => 'danger', 'text' => 'Error: This booking is not valid for today.'];
        } elseif ($booking_to_update['booking_status'] === 'completed') {
            $_SESSION['check_in_message'] = ['type' => 'warning', 'text' => 'Warning: This booking has already been checked in.'];
        } else {
            $update_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'completed', checked_in_at = NOW() WHERE booking_id = ?");
            $update_stmt->bind_param("s", $booking_id_to_check);
            if ($update_stmt->execute()) {
                $_SESSION['check_in_message'] = ['type' => 'success', 'text' => 'Booking ' . htmlspecialchars($booking_id_to_check) . ' checked in successfully!'];
            } else {
                $_SESSION['check_in_message'] = ['type' => 'danger', 'text' => 'Error checking in booking: ' . $conn->error];
            }
        }
    } else {
        $_SESSION['check_in_message'] = ['type' => 'danger', 'text' => 'Error: Booking ID not found.'];
    }
    header('Location: staff-dashboard.php'); // Redirect to clear POST data
    exit;
}

// Fetch today's bookings for the assigned mess
$today = date('Y-m-d');
$bookings_query_sql = "
    SELECT b.*, m.name as mess_name 
    FROM bookings b 
    JOIN mess m ON b.mess_id = m.id 
    WHERE b.booking_date = ? 
";
$params = [$today];
$types = "s";

if ($staff_mess_id) {
    $bookings_query_sql .= " AND b.mess_id = ?";
    $params[] = $staff_mess_id;
    $types .= "i";
}
$bookings_query_sql .= " ORDER BY b.created_at DESC";

$bookings_stmt = $conn->prepare($bookings_query_sql);
$bookings_stmt->bind_param($types, ...$params);
$bookings_stmt->execute();
$today_bookings = $bookings_stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - PU Mess Booking</title>
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
        .status-badge-active { background-color: #0d6efd; }
        .status-badge-completed { background-color: #28a745; }
        .status-badge-cancelled { background-color: #dc3545; }
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
                    <a href="staff-dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
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
        <h1 class="mb-4">Staff Dashboard</h1>

        <?php 
        if (isset($_SESSION['check_in_message'])) {
            $msg = $_SESSION['check_in_message'];
            echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($msg['text']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['check_in_message']); // Clear the message after displaying
        }
        ?>

        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0">Check-in Booking by ID</h4>
            </div>
            <div class="card-body">
                <form action="staff-dashboard.php" method="post" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label for="booking_id" class="visually-hidden">Booking ID</label>
                        <input type="text" class="form-control form-control-lg" id="booking_id" name="booking_id" placeholder="Enter Booking ID (e.g., PU202507171234)" required>
                    </div>
                    <div class="col-md-4">
                        <input type="hidden" name="action" value="check_in">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check-circle me-2"></i> Check In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0">Today's Bookings (<?php echo date('d M Y'); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if ($today_bookings->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Coupon Type</th>
                                <th>Persons</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Booked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $today_bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($booking['coupon_type'])); ?></td>
                                <td><?php echo htmlspecialchars($booking['persons']); ?></td>
                                <td>₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            if ($booking['booking_status'] == 'active') echo 'status-badge-active';
                                            else if ($booking['booking_status'] == 'completed') echo 'status-badge-completed';
                                            else if ($booking['booking_status'] == 'cancelled') echo 'status-badge-cancelled';
                                        ?>">
                                        <?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('H:i A', strtotime($booking['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No bookings for today at <?php echo htmlspecialchars($mess_name); ?>.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
