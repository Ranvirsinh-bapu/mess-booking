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
include 'staff-header.php';

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
        SELECT id, booking_status, mess_id, booking_date, meal_type, coupon_type
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
            // Check if checked_in_at column exists, if not use a simple update
            $column_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'checked_in_at'");
            if ($column_check->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'completed', checked_in_at = NOW() WHERE booking_id = ?");
            } else {
                $update_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'completed' WHERE booking_id = ?");
            }
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

// Fetch analytics data for charts
$today = date('Y-m-d');

// Today's stats
$today_stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        SUM(CASE WHEN booking_status = 'active' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(total_amount) as total_revenue
    FROM bookings 
    WHERE booking_date = ? AND mess_id = ?
");
$today_stats_query->bind_param("si", $today, $staff_mess_id);
$today_stats_query->execute();
$today_stats = $today_stats_query->get_result()->fetch_assoc();

// Check if checked_in_at column exists for hourly data
$column_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'checked_in_at'");
$has_checked_in_at = $column_check->num_rows > 0;

// Hourly check-ins for today (use created_at if checked_in_at doesn't exist)
if ($has_checked_in_at) {
    $hourly_checkins_query = $conn->prepare("
        SELECT 
            HOUR(checked_in_at) as hour,
            COUNT(*) as count
        FROM bookings 
        WHERE DATE(checked_in_at) = ? AND mess_id = ? AND booking_status = 'completed'
        GROUP BY HOUR(checked_in_at)
        ORDER BY HOUR(checked_in_at)
    ");
} else {
    // Fallback to using created_at for completed bookings
    $hourly_checkins_query = $conn->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM bookings 
        WHERE DATE(created_at) = ? AND mess_id = ? AND booking_status = 'completed'
        GROUP BY HOUR(created_at)
        ORDER BY HOUR(created_at)
    ");
}
$hourly_checkins_query->bind_param("si", $today, $staff_mess_id);
$hourly_checkins_query->execute();
$hourly_result = $hourly_checkins_query->get_result();

$hourly_data = array_fill(0, 24, 0);
while ($row = $hourly_result->fetch_assoc()) {
    if ($row['hour'] !== null) {
        $hourly_data[$row['hour']] = $row['count'];
    }
}

// Meal type distribution for today
$meal_type_query = $conn->prepare("
    SELECT 
        coupon_type,
        COUNT(*) as count
    FROM bookings 
    WHERE booking_date = ? AND mess_id = ?
    GROUP BY coupon_type
");
$meal_type_query->bind_param("si", $today, $staff_mess_id);
$meal_type_query->execute();
$meal_type_result = $meal_type_query->get_result();

$meal_types = [];
$meal_counts = [];
while ($row = $meal_type_result->fetch_assoc()) {
    $meal_types[] = ucfirst(str_replace('_', ' ', $row['coupon_type']));
    $meal_counts[] = $row['count'];
}

// Weekly trends (last 7 days)
$weekly_query = $conn->prepare("
    SELECT 
        DATE(booking_date) as date,
        COUNT(*) as bookings,
        SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as checkins
    FROM bookings 
    WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND mess_id = ?
    GROUP BY DATE(booking_date)
    ORDER BY DATE(booking_date)
");
$weekly_query->bind_param("i", $staff_mess_id);
$weekly_query->execute();
$weekly_result = $weekly_query->get_result();

$weekly_dates = [];
$weekly_bookings = [];
$weekly_checkins = [];
while ($row = $weekly_result->fetch_assoc()) {
    $weekly_dates[] = date('M d', strtotime($row['date']));
    $weekly_bookings[] = $row['bookings'];
    $weekly_checkins[] = $row['checkins'];
}

// Fetch today's bookings for the assigned mess
$bookings_query_sql = "
    SELECT b.*, m.name as mess_name 
    FROM bookings b 
    JOIN mess m ON b.mess_id = m.id 
    WHERE b.booking_date = ? ";
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
<style>
    .card-dashboard {
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .card-dashboard:hover {
        transform: translateY(-2px);
    }

    .status-badge-active {
        background-color: #0d6efd;
    }

    .status-badge-completed {
        background-color: #28a745;
    }

    .status-badge-cancelled {
        background-color: #dc3545;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-bottom: 20px;
    }

    .chart-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        padding: 20px;
        margin-bottom: 20px;
    }

    .stats-card {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border: none;
    }

    .stats-card-secondary {
        background: linear-gradient(135deg, #17a2b8, #20c997);
        color: white;
        border: none;
    }

    .stats-card-warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
        border: none;
    }

    .stats-card-info {
        background: linear-gradient(135deg, #6f42c1, #e83e8c);
        color: white;
        border: none;
    }

    .card-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .content {
            margin-left: 0;
        }

        .chart-container {
            height: 250px;
        }
    }
</style>

<div class="container mt-5">
    <div class="row">



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

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stats-card card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Today's Bookings</h6>
                            <h2 class="mb-0"><?php echo $today_stats['total_bookings'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-calendar-day card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-secondary card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Checked In</h6>
                            <h2 class="mb-0"><?php echo $today_stats['completed_bookings'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-check-circle card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-warning card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Pending</h6>
                            <h2 class="mb-0"><?php echo $today_stats['pending_bookings'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-clock card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-info card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Today's Revenue</h6>
                            <h2 class="mb-0">₹<?php echo number_format($today_stats['total_revenue'] ?? 0, 0); ?>
                            </h2>
                        </div>
                        <i class="fas fa-rupee-sign card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Weekly Booking Trends</h5>
                    <div class="chart-container">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Meal Type Distribution</h5>
                    <div class="chart-container">
                        <canvas id="mealTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Hourly Activity Today</h5>
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check-in Form -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-qrcode me-2"></i>Check-in Booking by ID</h4>
            </div>
            <div class="card-body">
                <form action="staff-dashboard.php" method="post" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label for="booking_id" class="visually-hidden">Booking ID</label>
                        <input type="text" class="form-control form-control-lg" id="booking_id" name="booking_id"
                            placeholder="Enter Booking ID (e.g., PU202507171234)" required>
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

        <!-- Today's Bookings Table -->
        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-list me-2"></i>Today's Bookings (<?php echo date('d M Y'); ?>)
                </h4>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $today_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['coupon_type']))); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['persons']); ?></td>
                                        <td>₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge
                                        <?php
                                        if ($booking['booking_status'] == 'active')
                                            echo 'status-badge-active';
                                        else if ($booking['booking_status'] == 'completed')
                                            echo 'status-badge-completed';
                                        else if ($booking['booking_status'] == 'cancelled')
                                            echo 'status-badge-cancelled';
                                        ?>">
                                                <?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i A', strtotime($booking['created_at'])); ?></td>
                                        <td>
                                            <?php if ($booking['booking_status'] == 'active'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="quickCheckIn('<?php echo htmlspecialchars($booking['booking_id']); ?>')">
                                                    <i class="fas fa-check"></i> Check In
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>No bookings for today at
                        <?php echo htmlspecialchars($mess_name); ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div> <!-- End content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Chart.js configurations
    Chart.defaults.font.family = 'Arial, sans-serif';
    Chart.defaults.color = '#666';

    // Weekly Trends Chart
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weekly_dates); ?>,
            datasets: [{
                label: 'Total Bookings',
                data: <?php echo json_encode($weekly_bookings); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Check-ins',
                data: <?php echo json_encode($weekly_checkins); ?>,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Count'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Meal Type Distribution Chart
    const mealTypeCtx = document.getElementById('mealTypeChart').getContext('2d');
    new Chart(mealTypeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($meal_types); ?>,
            datasets: [{
                data: <?php echo json_encode($meal_counts); ?>,
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545',
                    '#6f42c1'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });

    // Hourly Activity Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({ length: 24 }, (_, i) => i + ':00'),
            datasets: [{
                label: 'Activity',
                data: <?php echo json_encode($hourly_data); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: '#28a745',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hour of Day'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Quick check-in function
    function quickCheckIn(bookingId) {
        if (confirm('Check in booking: ' + bookingId + '?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'staff-dashboard.php';

            const bookingInput = document.createElement('input');
            bookingInput.type = 'hidden';
            bookingInput.name = 'booking_id';
            bookingInput.value = bookingId;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'check_in';

            form.appendChild(bookingInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Mobile sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
    });

    // Auto-refresh every 5 minutes
    setTimeout(function () {
        location.reload();
    }, 300000);
</script>