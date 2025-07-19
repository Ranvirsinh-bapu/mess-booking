<?php
// Ensure session is started and admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Admin Dashboard'; ?> - PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #34495e;
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
            background-color: #2c3e50;
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background-color: #3498db;
            color: white;
            border-left: 4px solid #e74c3c;
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
            background-color: #2c3e50;
            border-bottom: 1px solid #34495e;
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
            background: #34495e;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #2c3e50;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #1a252f;
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
            <a class="navbar-brand" href="admin-dashboard.php">
                <i class="fas fa-university me-2"></i>PU Mess Admin
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="notificationDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Low mess capacity</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-clock text-info me-2"></i>Pending bookings</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-chart-line text-success me-2"></i>Daily report ready</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($admin_username); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>System Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php?type=admin"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>Admin Panel</h5>
            <small>Management System</small>
        </div>
        
        <div class="d-flex flex-column p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link <?php echo ($current_page == 'admin-dashboard.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-manage-bookings.php" class="nav-link <?php echo ($current_page == 'admin-manage-bookings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-book me-2"></i> Manage Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-manage-messes.php" class="nav-link <?php echo ($current_page == 'admin-manage-messes.php') ? 'active' : ''; ?>">
                        <i class="fas fa-utensils me-2"></i> Manage Messes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-manage-pricing.php" class="nav-link <?php echo ($current_page == 'admin-manage-pricing.php') ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign me-2"></i> Manage Pricing
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-manage-staff.php" class="nav-link <?php echo ($current_page == 'admin-manage-staff.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog me-2"></i> Manage Staff
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-reports.php" class="nav-link <?php echo ($current_page == 'admin-reports.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar me-2"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-qr-scanner.php" class="nav-link <?php echo ($current_page == 'admin-qr-scanner.php') ? 'active' : ''; ?>">
                        <i class="fas fa-qrcode me-2"></i> QR Scanner
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-notifications.php" class="nav-link <?php echo ($current_page == 'admin-notifications.php') ? 'active' : ''; ?>">
                        <i class="fas fa-bell me-2"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-settings.php" class="nav-link <?php echo ($current_page == 'admin-settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cogs me-2"></i> System Settings
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
