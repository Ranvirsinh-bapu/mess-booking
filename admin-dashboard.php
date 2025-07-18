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

// Fetch total bookings
$total_bookings_query = $conn->query("SELECT COUNT(*) AS total FROM bookings");
$total_bookings = $total_bookings_query->fetch_assoc()['total'];

// Fetch today's bookings
$today = date('Y-m-d');
$today_bookings_query = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE booking_date = ?");
$today_bookings_query->bind_param("s", $today);
$today_bookings_query->execute();
$today_bookings = $today_bookings_query->get_result()->fetch_assoc()['total'];

// Fetch active messes
$active_messes_query = $conn->query("SELECT COUNT(*) AS total FROM mess WHERE status = 'active'");
$active_messes = $active_messes_query->fetch_assoc()['total'];

// Fetch recent bookings (e.g., last 5)
$recent_bookings_query = $conn->query("
    SELECT b.*, m.name as mess_name 
    FROM bookings b 
    JOIN mess m ON b.mess_id = m.id 
    ORDER BY b.created_at DESC 
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PU Mess Booking</title>
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
            transition: transform 0.3s ease;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        .card-dashboard .card-icon {
            font-size: 3rem;
            opacity: 0.7;
        }
        .card-dashboard.bg-primary { background-color: #667eea !important; }
        .card-dashboard.bg-success { background-color: #28a745 !important; }
        .card-dashboard.bg-info { background-color: #17a2b8 !important; }
        .card-dashboard.bg-warning { background-color: #ffc107 !important; }
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
                    <a href="admin-dashboard.php" class="nav-link active">
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
                    <a href="#" class="nav-link text-white">
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
        <h1 class="mb-4">Dashboard Overview</h1>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Total Bookings</h5>
                            <p class="card-text fs-2"><?php echo $total_bookings; ?></p>
                        </div>
                        <i class="fas fa-receipt card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Today's Bookings</h5>
                            <p class="card-text fs-2"><?php echo $today_bookings; ?></p>
                        </div>
                        <i class="fas fa-calendar-day card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Active Messes</h5>
                            <p class="card-text fs-2"><?php echo $active_messes; ?></p>
                        </div>
                        <i class="fas fa-store card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0">Recent Bookings</h4>
            </div>
            <div class="card-body">
                <?php if ($recent_bookings_query->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Mess</th>
                                <th>Coupon Type</th>
                                <th>Persons</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $recent_bookings_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['mess_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['coupon_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['persons']); ?></td>
                                <td>₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
                                <td><span class="badge bg-<?php echo $booking['booking_status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No recent bookings found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
