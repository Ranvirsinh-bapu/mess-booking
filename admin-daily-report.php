<?php
$page_title = "Daily Report";
$current_page = "daily_report";
require_once 'admin-header.php';
require_once 'config.php';
$conn = getDBConnection();
// Fetch data for charts
$today_date = date('Y-m-d');

// Data for Daily Bookings Chart (e.g., last 7 days)
$booking_dates = [];
$booking_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $booking_dates[] = $date;

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE booking_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking_counts[] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Data for Booking Status Distribution (Today)
$status_labels = ['Confirmed', 'Pending', 'Attended', 'Cancelled'];
$status_counts = [];
foreach ($status_labels as $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE booking_date = ? AND booking_status = ?");
    $lower_status = strtolower($status);
    $stmt->bind_param("ss", $today_date, $lower_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts[] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Data for Bookings by Mess (Today)
$date = '2025-07-18';

$stmt = $conn->prepare("SELECT m.name AS mess_name, COUNT(*) AS total_bookings 
                        FROM bookings b 
                        JOIN mess m ON b.mess_id = m.id 
                        WHERE b.booking_date = ? 
                        GROUP BY m.name 
                        ORDER BY m.name");

$stmt->bind_param("s", $date); // "s" = string
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

?>

<div class="container-fluid">
    <h1 class="mt-4">Daily Report</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Daily Report</li>
    </ol>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar mr-1"></i>
                    Daily Bookings (Last 7 Days)
                </div>
                <div class="card-body"><canvas id="dailyBookingsChart" width="100%" height="50"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie mr-1"></i>
                    Booking Status Distribution (Today: <?php echo htmlspecialchars($today_date); ?>)
                </div>
                <div class="card-body"><canvas id="bookingStatusChart" width="100%" height="50"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-area mr-1"></i>
                    Bookings by Mess (Today: <?php echo htmlspecialchars($today_date); ?>)
                </div>
                <div class="card-body"><canvas id="bookingsByMessChart" width="100%" height="40"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Data from PHP
    const bookingDates = <?php echo json_encode($booking_dates); ?>;
    const bookingCounts = <?php echo json_encode($booking_counts); ?>;
    const statusLabels = <?php echo json_encode($status_labels); ?>;
    const statusCounts = <?php echo json_encode($status_counts); ?>;
    const messNames = <?php echo json_encode($mess_names); ?>;
    const messBookingCounts = <?php echo json_encode($mess_booking_counts); ?>;

    // Daily Bookings Chart
    const dailyBookingsCtx = document.getElementById('dailyBookingsChart').getContext('2d');
    new Chart(dailyBookingsCtx, {
        type: 'bar',
        data: {
            labels: bookingDates,
            datasets: [{
                label: 'Bookings',
                data: bookingCounts,
                backgroundColor: 'rgba(52, 152, 219, 0.8)', // Blue
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Booking Status Distribution Chart
    const bookingStatusCtx = document.getElementById('bookingStatusChart').getContext('2d');
    new Chart(bookingStatusCtx, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)', // Success (Confirmed)
                    'rgba(255, 193, 7, 0.8)',  // Warning (Pending)
                    'rgba(52, 152, 219, 0.8)', // Primary (Attended)
                    'rgba(220, 53, 69, 0.8)'   // Danger (Cancelled)
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed;
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Bookings by Mess Chart
    const bookingsByMessCtx = document.getElementById('bookingsByMessChart').getContext('2d');
    new Chart(bookingsByMessCtx, {
        type: 'bar',
        data: {
            labels: messNames,
            datasets: [{
                label: 'Bookings',
                data: messBookingCounts,
                backgroundColor: 'rgba(23, 162, 184, 0.8)', // Info color
                borderColor: 'rgba(23, 162, 184, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>
