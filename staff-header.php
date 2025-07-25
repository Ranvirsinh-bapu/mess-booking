<?php
// Ensure session is started and staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.php');
    exit;
}

$staff_username = $_SESSION['staff_username'] ?? 'Staff';
$staff_mess_id = $_SESSION['staff_mess_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// Get mess name for the staff's assigned mess
$mess_name = 'N/A';
if ($staff_mess_id && isset($conn)) {
    $mess_query = $conn->prepare("SELECT name FROM mess WHERE id = ?");
    $mess_query->bind_param("i", $staff_mess_id);
    $mess_query->execute();
    $mess_result = $mess_query->get_result();
    if ($mess_row = $mess_result->fetch_assoc()) {
        $mess_name = $mess_row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Staff Dashboard'; ?> - PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #28a745;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #218838;
            padding-top: 56px;
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            transition: all 0.3s ease;
            border-radius: 0;
            margin: 2px 10px;
        }
        .sidebar .nav-link:hover {
            background-color: #1e7e34;
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background-color: #20c997;
            color: white;
            border-left: 4px solid #ffc107;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .navbar-text {
            font-size: 0.9rem;
        }
        .sidebar-header {
            padding: 20px;
            background-color: #1e7e34;
            border-bottom: 1px solid #218838;
            text-align: center;
        }
        .sidebar-header h5 {
            color: #ecf0f1;
            margin: 0;
            font-weight: 600;
        }
        .sidebar-header small {
            color: #bdc3c7;
        }
        .mess-info {
            background-color: #155724;
            padding: 10px;
            margin: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .mess-info .mess-name {
            font-weight: bold;
            color: #d4edda;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                margin-left: 0;
            }
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #218838;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #1e7e34;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #155724;
        }
    </style>
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <button class="btn btn-outline-light me-3 d-md-none" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="staff-dashboard.php">
                <i class="fas fa-utensils me-2"></i>PU Mess Staff
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="notificationDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-warning">2</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-clock text-warning me-2"></i>New booking received</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-circle text-info me-2"></i>Meal capacity updated</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($staff_username); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Assigned to: <?php echo htmlspecialchars($mess_name); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php?type=staff"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>Staff Panel</h5>
            <small>Mess Management</small>
        </div>
        
        <div class="mess-info">
            <div class="mess-name"><?php echo htmlspecialchars($mess_name); ?></div>
            <small>Assigned Mess</small>
        </div>
        
        <div class="d-flex flex-column p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="staff-dashboard.php" class="nav-link <?php echo ($current_page == 'staff-dashboard.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-manage-bookings.php" class="nav-link <?php echo ($current_page == 'staff-manage-bookings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-book me-2"></i> Today's Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-manage-meal-availability.php" class="nav-link <?php echo ($current_page == 'staff-manage-meal-availability.php') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check me-2"></i> Meal Availability
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-qr-scanner.php" class="nav-link <?php echo ($current_page == 'staff-qr-scanner.php') ? 'active' : ''; ?>">
                        <i class="fas fa-qrcode me-2"></i> QR Scanner
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-booking-history.php" class="nav-link <?php echo ($current_page == 'staff-booking-history.php') ? 'active' : ''; ?>">
                        <i class="fas fa-history me-2"></i> Booking History
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-daily-report.php" class="nav-link <?php echo ($current_page == 'staff-reports.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line me-2"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff-feedback.php" class="nav-link <?php echo ($current_page == 'staff-feedback.php') ? 'active' : ''; ?>">
                        <i class="fas fa-comments me-2"></i> Customer Feedback
                    </a>
                </li>
            </ul>
            
            <hr class="text-white">
            
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Last login: <?php echo date('M d, Y H:i'); ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
