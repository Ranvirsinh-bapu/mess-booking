<?php
session_start();
$page_title = "Daily Report";
$current_page = "daily_report";
require_once 'staff-header.php';
require_once 'config.php';
$conn = getDBConnection();  

// Fetch data for charts specific to the assigned mess
$today_date = date('Y-m-d');

// Data for Daily Bookings Chart (e.g., last 7 days for this mess)
$booking_dates = [];
$booking_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $booking_dates[] = $date;

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE mess_id = ? AND booking_date = ?");
    $stmt->bind_param("is", $assigned_mess_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking_counts[] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Data for Booking Status Distribution (Today for this mess)
$status_labels = ['Confirmed', 'Pending', 'Attended', 'Cancelled'];
$status_counts = [];
foreach ($status_labels as $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE mess_id = ? AND booking_date = ? AND booking_status = ?");
    $lower_status = strtolower($status);
    $stmt->bind_param("iss", $assigned_mess_id, $today_date, $lower_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts[] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Data for Meal Capacity vs Booked (Today for this mess)
$capacity_data = [
    'total_capacity' => 0,
    'booked_persons' => 0,
    'available_capacity' => 0
];

// Get total capacity for today
$stmt = $conn->prepare("SELECT daily_capacity FROM mess WHERE id = ? AND created_at = ?");
$stmt->bind_param("is", $assigned_mess_id, $today_date);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $capacity_data['total_capacity'] = $result->fetch_assoc()['daily_capacity'];
}
$stmt->close();

// Get total booked persons for today
$stmt = $conn->prepare("SELECT SUM(persons) AS booked_persons 
                        FROM bookings 
                        WHERE mess_id = ? 
                        AND booking_date = ? 
                        AND booking_status IN ('active', 'used', 'expired')");
$stmt->bind_param("is", $assigned_mess_id, $today_date);
$stmt->execute();
$result = $stmt->get_result();
$booked_persons_sum = $result->fetch_assoc()['booked_persons'] ?? 0;
$capacity_data['booked_persons'] = $booked_persons_sum;
$stmt->close();


// Calculate available capacity
if ($capacity_data['total_capacity'] == 999999) { // Unlimited capacity
    $capacity_data['available_capacity'] = 999999; // Represent as unlimited
} else {
    $capacity_data['available_capacity'] = $capacity_data['total_capacity'] - $capacity_data['booked_persons'];
    if ($capacity_data['available_capacity'] < 0) $capacity_data['available_capacity'] = 0;
}

$capacity_chart_labels = ['Booked Persons', 'Available Capacity'];
$capacity_chart_data = [$capacity_data['booked_persons'], $capacity_data['available_capacity']];
if ($capacity_data['total_capacity'] == 999999) {
    $capacity_chart_labels = ['Booked Persons', 'Unlimited Capacity'];
    $capacity_chart_data = [$capacity_data['booked_persons'], 1]; // A small value to show "unlimited" slice
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Daily Report for <?php echo htmlspecialchars($mess_name); ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="staff-dashboard.php">Dashboard</a></li>
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
                    Meal Capacity vs Booked (Today: <?php echo htmlspecialchars($today_date); ?>)
                </div>
                <div class="card-body"><canvas id="mealCapacityChart" width="100%" height="40"></canvas></div>
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
    const capacityChartLabels = <?php echo json_encode($capacity_chart_labels); ?>;
    const capacityChartData = <?php echo json_encode($capacity_chart_data); ?>;
    const totalCapacity = <?php echo json_encode($capacity_data['total_capacity']); ?>;

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

    // Meal Capacity vs Booked Chart
    const mealCapacityCtx = document.getElementById('mealCapacityChart').getContext('2d');
    new Chart(mealCapacityCtx, {
        type: 'doughnut',
        data: {
            labels: capacityChartLabels,
            datasets: [{
                data: capacityChartData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)', // Red for Booked
                    'rgba(75, 192, 192, 0.8)'  // Green for Available
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
                                if (totalCapacity === 999999 && context.dataIndex === 1) {
                                    label += 'Unlimited';
                                } else {
                                    label += context.parsed;
                                }
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>
