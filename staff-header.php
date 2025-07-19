<?php
session_start();
// require_once 'config.php';
require_once 'config.php';
$conn = getDBConnection();
// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff-login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_username'];
$assigned_mess_id = $_SESSION['mess_id'];

// Fetch mess details for the assigned mess
$mess_name = "N/A";
$mess_location = "N/A";
$mess_contact = "N/A";

$stmt = $conn->prepare("SELECT name, location, contact FROM mess WHERE id = ?");
$stmt->bind_param("i", $assigned_mess_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $mess_data = $result->fetch_assoc();
    $mess_name = $mess_data['mess_name'];
    $mess_location = $mess_data['mess_location'];
    $mess_contact = $mess_data['mess_contact'];
}
$stmt->close();

// Default page title if not set by the including file
if (!isset($page_title)) {
    $page_title = "Staff Dashboard";
}

// Default current page for sidebar highlighting
if (!isset($current_page)) {
    $current_page = "dashboard";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Staff Panel</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #2c3e50; /* Dark blue-gray */
            border-bottom: 3px solid #3498db; /* Blue accent */
            padding: 0.75rem 1rem;
        }
        .navbar-brand {
            color: #ecf0f1 !important; /* Light gray */
            font-weight: bold;
            font-size: 1.5rem;
        }
        .navbar-nav .nav-link {
            color: #ecf0f1 !important;
            padding: 0.5rem 1rem;
            transition: background-color 0.3s ease;
        }
        .navbar-nav .nav-link:hover {
            background-color: #34495e; /* Slightly lighter dark blue-gray */
            border-radius: 5px;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #34495e; /* Darker blue-gray */
            padding-top: 65px; /* Adjust for navbar height */
            color: #ecf0f1;
            transition: all 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 15px;
            display: block;
            transition: background-color 0.3s ease, color 0.3s ease;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background-color: #2c3e50;
            color: #3498db;
            border-left-color: #3498db;
        }
        .sidebar .nav-link.active {
            background-color: #2c3e50;
            color: #3498db;
            border-left-color: #3498db;
            font-weight: bold;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px; /* Fixed width for icons */
            text-align: center;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.75em;
            font-weight: bold;
            color: white;
        }
        .status-badge-primary { background-color: #3498db; }
        .status-badge-success { background-color: #28a745; }
        .status-badge-warning { background-color: #ffc107; color: #333; }
        .status-badge-danger { background-color: #dc3545; }
        .status-badge-info { background-color: #17a2b8; }
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: #777;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <a class="navbar-brand" href="staff-dashboard.php">Mess Booking Staff</a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($mess_name); ?>)</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>" href="staff-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_meal_availability') ? 'active' : ''; ?>" href="staff-manage-meal-availability.php">
                    <i class="fas fa-calendar-alt"></i> Manage Meal Availability
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'daily_report') ? 'active' : ''; ?>" href="staff-daily-report.php">
                    <i class="fas fa-chart-line"></i> Daily Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
