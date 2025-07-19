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
include('admin-header.php');
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

// Fetch data for charts
// Monthly bookings for the current year
$monthly_bookings_query = $conn->query("
    SELECT 
        MONTH(booking_date) as month,
        COUNT(*) as count
    FROM bookings 
    WHERE YEAR(booking_date) = YEAR(CURDATE())
    GROUP BY MONTH(booking_date)
    ORDER BY MONTH(booking_date)
");

$monthly_data = array_fill(1, 12, 0);
while ($row = $monthly_bookings_query->fetch_assoc()) {
    $monthly_data[$row['month']] = $row['count'];
}

// Booking status distribution
$status_query = $conn->query("
    SELECT booking_status, COUNT(*) as count 
    FROM bookings 
    GROUP BY booking_status
");

$status_data = [];
$status_labels = [];
while ($row = $status_query->fetch_assoc()) {
    $status_labels[] = ucfirst($row['booking_status']);
    $status_data[] = $row['count'];
}

// Top messes by bookings
$top_messes_query = $conn->query("
    SELECT m.name, COUNT(b.id) as booking_count
    FROM mess m
    LEFT JOIN bookings b ON m.id = b.mess_id
    WHERE m.status = 'active'
    GROUP BY m.id, m.name
    ORDER BY booking_count DESC
    LIMIT 5
");

$mess_names = [];
$mess_bookings = [];
while ($row = $top_messes_query->fetch_assoc()) {
    $mess_names[] = $row['name'];
    $mess_bookings[] = $row['booking_count'];
}

// Daily bookings for the last 7 days
$daily_bookings_query = $conn->query("
    SELECT 
        DATE(booking_date) as date,
        COUNT(*) as count
    FROM bookings 
    WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(booking_date)
    ORDER BY DATE(booking_date)
");

$daily_labels = [];
$daily_data = [];
while ($row = $daily_bookings_query->fetch_assoc()) {
    $daily_labels[] = date('M d', strtotime($row['date']));
    $daily_data[] = $row['count'];
}

// Revenue data
$revenue_query = $conn->query("
    SELECT 
        MONTH(booking_date) as month,
        SUM(total_amount) as revenue
    FROM bookings 
    WHERE YEAR(booking_date) = YEAR(CURDATE())
    GROUP BY MONTH(booking_date)
    ORDER BY MONTH(booking_date)
");

$revenue_data = array_fill(1, 12, 0);
while ($row = $revenue_query->fetch_assoc()) {
    $revenue_data[$row['month']] = $row['revenue'];
}

$conn->close();
?>




<!-- Stats Cards -->
<div class="container mt-5">
    <div class="row">
        <div class="row g-4 mb-4">
            <h1 class="mb-4 mt-4">Dashboard Overview</h1>
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <div class="card text-white bg-warning card-dashboard">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Total Revenue</h5>
                            <p class="card-text fs-2">₹<?php echo number_format(array_sum($revenue_data), 0); ?></p>
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
                    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Monthly Bookings & Revenue</h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Booking Status</h5>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Top Messes by Bookings</h5>
                    <div class="chart-container">
                        <canvas id="messChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-chart-area me-2"></i>Daily Bookings (Last 7 Days)</h5>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings Table -->
        <div class="card card-dashboard">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Bookings</h4>
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
                                <?php while ($booking = $recent_bookings_query->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['mess_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['coupon_type']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['persons']); ?></td>
                                        <td>₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
                                        <td><span
                                                class="badge bg-<?php echo $booking['booking_status'] == 'active' ? 'primary' : ($booking['booking_status'] == 'completed' ? 'success' : 'secondary'); ?>"><?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?></span>
                                        </td>
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
</div>

        <style>
            body {
                background-color: #f4f7f6;
            }

            .navbar {
                background-color: #2c3e50;
            }


            .card-dashboard {
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                transition: transform 0.3s ease;
            }

            .card-dashboard:hover {
                transform: translateY(-5px);
            }

            .card-dashboard .card-icon {
                font-size: 3rem;
                opacity: 0.7;
            }

            .card-dashboard.bg-primary {
                background-color: #667eea !important;
            }

            .card-dashboard.bg-success {
                background-color: #28a745 !important;
            }

            .card-dashboard.bg-info {
                background-color: #17a2b8 !important;
            }

            .card-dashboard.bg-warning {
                background-color: #ffc107 !important;
            }

            .chart-container {
                position: relative;
                height: 400px;
                margin-bottom: 20px;
            }

            .chart-card {
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                padding: 20px;
                margin-bottom: 20px;
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
                    height: 300px;
                }
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Chart.js configurations
            Chart.defaults.font.family = 'Arial, sans-serif';
            Chart.defaults.color = '#666';

            // Monthly Bookings & Revenue Chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Bookings',
                        data: <?php echo json_encode(array_values($monthly_data)); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'Revenue (₹)',
                        data: <?php echo json_encode(array_values($revenue_data)); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Bookings'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue (₹)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

            // Booking Status Pie Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($status_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($status_data); ?>,
                        backgroundColor: [
                            '#667eea',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#17a2b8'
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

            // Top Messes Bar Chart
            const messCtx = document.getElementById('messChart').getContext('2d');
            new Chart(messCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($mess_names); ?>,
                    datasets: [{
                        label: 'Bookings',
                        data: <?php echo json_encode($mess_bookings); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
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
                                text: 'Number of Bookings'
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

            // Daily Bookings Area Chart
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($daily_labels); ?>,
                    datasets: [{
                        label: 'Daily Bookings',
                        data: <?php echo json_encode($daily_data); ?>,
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.2)',
                        fill: true,
                        tension: 0.4
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
                                text: 'Bookings'
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

            // Mobile sidebar toggle
            document.getElementById('sidebarToggle')?.addEventListener('click', function () {
                const sidebar = document.getElementById('sidebar');
                sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
            });
        </script>
        <?php
        include('footer.php');
        ?>