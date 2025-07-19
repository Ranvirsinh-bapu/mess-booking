<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
include('admin-header.php');
$admin_username = $_SESSION['admin_username'];
$conn = getDBConnection();

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_global_prices' || $action === 'update_time_slots') {
        $updates = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'price_') === 0 || strpos($key, 'time_') === 0) {
                $updates[$key] = trim($value);
            }
        }

        $conn->begin_transaction();
        try {
            foreach ($updates as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            $conn->commit();
            $message = 'Settings updated successfully!';
            $message_type = 'success';
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = 'Error updating settings: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'add_special_price' || $action === 'edit_special_price') {
        $date = trim($_POST['date']);
        $meal_type = trim($_POST['meal_type']);
        $price = floatval($_POST['price']);
        $description = trim($_POST['description'] ?? '');

        if (empty($date) || empty($meal_type) || $price <= 0) {
            $message = 'Date, Meal Type, and Price are required for special pricing.';
            $message_type = 'danger';
        } else {
            if ($action === 'add_special_price') {
                $stmt = $conn->prepare("INSERT INTO special_pricing (date, meal_type, price, description) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssds", $date, $meal_type, $price, $description);
                if ($stmt->execute()) {
                    $message = 'Special price added successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding special price (might already exist for this date/meal type): ' . $conn->error;
                    $message_type = 'danger';
                }
            } elseif ($action === 'edit_special_price') {
                $special_price_id = intval($_POST['special_price_id']);
                $stmt = $conn->prepare("UPDATE special_pricing SET date = ?, meal_type = ?, price = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssdsi", $date, $meal_type, $price, $description, $special_price_id);
                if ($stmt->execute()) {
                    $message = 'Special price updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating special price: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'delete_special_price') {
        $special_price_id = intval($_POST['special_price_id']);
        $stmt = $conn->prepare("DELETE FROM special_pricing WHERE id = ?");
        $stmt->bind_param("i", $special_price_id);
        if ($stmt->execute()) {
            $message = 'Special price deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting special price: ' . $conn->error;
            $message_type = 'danger';
        }
    } elseif ($action === 'bulk_update_prices') {
        $percentage = floatval($_POST['bulk_percentage']);
        $price_type = $_POST['price_type'];

        if ($percentage != 0) {
            $conn->begin_transaction();
            try {
                if ($price_type === 'all' || $price_type === 'meals') {
                    $meal_prices = ['price_breakfast', 'price_lunch_weekday', 'price_dinner_weekday', 'price_lunch_sunday', 'price_dinner_sunday'];
                    foreach ($meal_prices as $price_key) {
                        $stmt = $conn->prepare("UPDATE settings SET setting_value = ROUND(setting_value * (1 + ? / 100), 2) WHERE setting_key = ?");
                        $stmt->bind_param("ds", $percentage, $price_key);
                        $stmt->execute();
                    }
                }

                if ($price_type === 'all' || $price_type === 'monthly') {
                    $monthly_prices = ['price_in_campus_monthly', 'price_out_campus_monthly'];
                    foreach ($monthly_prices as $price_key) {
                        $stmt = $conn->prepare("UPDATE settings SET setting_value = ROUND(setting_value * (1 + ? / 100), 2) WHERE setting_key = ?");
                        $stmt->bind_param("ds", $percentage, $price_key);
                        $stmt->execute();
                    }
                }

                $conn->commit();
                $message = 'Bulk price update completed successfully!';
                $message_type = 'success';
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $message = 'Error in bulk update: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }

    // Store message in session to display after redirect
    $_SESSION['admin_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: admin-manage-pricing.php');
    exit;
}

// Retrieve messages from session
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message']['text'];
    $message_type = $_SESSION['admin_message']['type'];
    unset($_SESSION['admin_message']);
}

// --- Fetch Data for Display ---
// Fetch all settings
$settings_data = [];
$settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $settings_result->fetch_assoc()) {
    $settings_data[$row['setting_key']] = $row['setting_value'];
}

// Fetch all special pricing
$special_pricing_list_query = $conn->query("SELECT * FROM special_pricing ORDER BY date DESC, meal_type ASC");

// Calculate pricing statistics
$total_revenue_query = $conn->query("SELECT SUM(total_amount) as total_revenue FROM bookings WHERE booking_status IN ('active', 'completed')");
$total_revenue = $total_revenue_query->fetch_assoc()['total_revenue'] ?? 0;

$monthly_revenue_query = $conn->query("SELECT SUM(total_amount) as monthly_revenue FROM bookings WHERE booking_status IN ('active', 'completed') AND MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())");
$monthly_revenue = $monthly_revenue_query->fetch_assoc()['monthly_revenue'] ?? 0;

$conn->close();

// Helper to get setting value for display
function getSetting($key, $default = '')
{
    global $settings_data;
    return htmlspecialchars($settings_data[$key] ?? $default);
}
?>

<style>
    .card-dashboard {
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: none;
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.95);
        transition: all 0.3s ease;
    }

    .card-dashboard:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 20px 20px 0 0 !important;
        padding: 20px 25px;
        border: none;
    }

    .stats-card {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
    }

    .stats-card .stats-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.8;
    }

    .stats-card h3 {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .price-input-group {
        position: relative;
        margin-bottom: 20px;
    }

    .price-input-group .form-label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .price-input-group .form-control {
        border-radius: 15px;
        border: 2px solid #e9ecef;
        padding: 12px 20px;
        transition: all 0.3s ease;
    }

    .price-input-group .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-custom {
        border-radius: 15px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .btn-primary-custom:hover {
        background: linear-gradient(135deg, #5a6fd8, #6a4190);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-success-custom {
        background: linear-gradient(135deg, #56ab2f, #a8e6cf);
        color: white;
    }

    .btn-success-custom:hover {
        background: linear-gradient(135deg, #4e9a2a, #96d4b5);
        transform: translateY(-2px);
    }

    .btn-danger-custom {
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
        color: white;
    }

    .btn-danger-custom:hover {
        background: linear-gradient(135deg, #e63946, #e63946);
        transform: translateY(-2px);
    }

    .table-custom {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .table-custom thead {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .table-custom tbody tr {
        transition: all 0.3s ease;
    }

    .table-custom tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.1);
        transform: scale(1.02);
    }

    .modal-content {
        border-radius: 20px;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 20px 20px 0 0;
        border: none;
    }

    .time-slot-card {
        background: linear-gradient(135deg, #ffecd2, #fcb69f);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .time-slot-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(252, 182, 159, 0.3);
    }

    .special-price-badge {
        background: linear-gradient(135deg, #ff9a9e, #fecfef);
        color: #333;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
    }

    .bulk-update-section {
        background: linear-gradient(135deg, #a8edea, #fed6e3);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            width: 250px;
        }

        .content {
            margin-left: 0;
            padding: 20px;
        }

        .stats-card {
            margin-bottom: 20px;
        }
    }

    .animate-fade-in {
        animation: fadeInUp 0.6s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
</head>

<div class="container mt-5">
    <div class="row">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-dark animate-fade-in">
                <i class="fas fa-dollar-sign me-3"></i>Pricing & Time Management
            </h1>
            <div class="d-flex gap-2">
                <button class="btn btn-success-custom btn-custom" data-bs-toggle="modal"
                    data-bs-target="#bulkUpdateModal">
                    <i class="fas fa-percentage me-2"></i>Bulk Update
                </button>
                <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal"
                    data-bs-target="#addSpecialPriceModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Special Price
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show animate-fade-in"
                role="alert">
                <i
                    class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Revenue Statistics -->
        <div class="row mb-4 animate-fade-in">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>₹<?php echo number_format($total_revenue, 0); ?></h3>
                    <p class="mb-0">Total Revenue</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar-month"></i>
                    </div>
                    <h3>₹<?php echo number_format($monthly_revenue, 0); ?></h3>
                    <p class="mb-0">This Month</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3><?php echo $special_pricing_list_query->num_rows; ?></h3>
                    <p class="mb-0">Special Prices</p>
                </div>
            </div>
        </div>

        <!-- Global Pricing Settings -->
        <div class="card card-dashboard mb-4 animate-fade-in">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-rupee-sign me-2"></i>Global Coupon Prices
                </h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="update_global_prices">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="price-input-group">
                                <label for="price_in_campus_monthly" class="form-label">
                                    <i class="fas fa-building me-2"></i>In Campus Monthly
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_in_campus_monthly"
                                        name="price_in_campus_monthly"
                                        value="<?php echo getSetting('price_in_campus_monthly'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="price-input-group">
                                <label for="price_out_campus_monthly" class="form-label">
                                    <i class="fas fa-home me-2"></i>Out Campus Monthly
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_out_campus_monthly"
                                        name="price_out_campus_monthly"
                                        value="<?php echo getSetting('price_out_campus_monthly'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="price-input-group">
                                <label for="price_breakfast" class="form-label">
                                    <i class="fas fa-coffee me-2"></i>Breakfast
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_breakfast"
                                        name="price_breakfast" value="<?php echo getSetting('price_breakfast'); ?>"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="price-input-group">
                                <label for="price_lunch_weekday" class="form-label">
                                    <i class="fas fa-utensils me-2"></i>Weekday Lunch
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_lunch_weekday"
                                        name="price_lunch_weekday"
                                        value="<?php echo getSetting('price_lunch_weekday'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="price-input-group">
                                <label for="price_dinner_weekday" class="form-label">
                                    <i class="fas fa-moon me-2"></i>Weekday Dinner
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_dinner_weekday"
                                        name="price_dinner_weekday"
                                        value="<?php echo getSetting('price_dinner_weekday'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="price-input-group">
                                <label for="price_lunch_sunday" class="form-label">
                                    <i class="fas fa-sun me-2"></i>Sunday Lunch
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_lunch_sunday"
                                        name="price_lunch_sunday"
                                        value="<?php echo getSetting('price_lunch_sunday'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="price-input-group">
                                <label for="price_dinner_sunday" class="form-label">
                                    <i class="fas fa-star me-2"></i>Sunday Dinner
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" id="price_dinner_sunday"
                                        name="price_dinner_sunday"
                                        value="<?php echo getSetting('price_dinner_sunday'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary-custom btn-custom btn-lg">
                                <i class="fas fa-save me-2"></i>Update All Prices
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Global Time Slot Settings -->
        <div class="card card-dashboard mb-4 animate-fade-in">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Global Meal Time Slots
                </h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="update_time_slots">
                    <div class="row g-4">
                        <!-- Breakfast Times -->
                        <div class="col-md-12">
                            <div class="time-slot-card">
                                <h5 class="mb-3"><i class="fas fa-coffee me-2"></i>Breakfast Timing</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="time_breakfast_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="time_breakfast_start"
                                            name="time_breakfast_start"
                                            value="<?php echo getSetting('time_breakfast_start'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="time_breakfast_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="time_breakfast_end"
                                            name="time_breakfast_end"
                                            value="<?php echo getSetting('time_breakfast_end'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lunch Times -->
                        <div class="col-md-6">
                            <div class="time-slot-card">
                                <h5 class="mb-3"><i class="fas fa-utensils me-2"></i>Weekday Lunch</h5>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="time_lunch_weekday_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="time_lunch_weekday_start"
                                            name="time_lunch_weekday_start"
                                            value="<?php echo getSetting('time_lunch_weekday_start'); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="time_lunch_weekday_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="time_lunch_weekday_end"
                                            name="time_lunch_weekday_end"
                                            value="<?php echo getSetting('time_lunch_weekday_end'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="time-slot-card">
                                <h5 class="mb-3"><i class="fas fa-sun me-2"></i>Sunday Lunch</h5>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="time_lunch_sunday_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="time_lunch_sunday_start"
                                            name="time_lunch_sunday_start"
                                            value="<?php echo getSetting('time_lunch_sunday_start'); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="time_lunch_sunday_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="time_lunch_sunday_end"
                                            name="time_lunch_sunday_end"
                                            value="<?php echo getSetting('time_lunch_sunday_end'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dinner Times -->
                        <div class="col-md-6">
                            <div class="time-slot-card">
                                <h5 class="mb-3"><i class="fas fa-moon me-2"></i>Weekday Dinner</h5>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="time_dinner_weekday_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="time_dinner_weekday_start"
                                            name="time_dinner_weekday_start"
                                            value="<?php echo getSetting('time_dinner_weekday_start'); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="time_dinner_weekday_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="time_dinner_weekday_end"
                                            name="time_dinner_weekday_end"
                                            value="<?php echo getSetting('time_dinner_weekday_end'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="time-slot-card">
                                <h5 class="mb-3"><i class="fas fa-star me-2"></i>Sunday Dinner</h5>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="time_dinner_sunday_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="time_dinner_sunday_start"
                                            name="time_dinner_sunday_start"
                                            value="<?php echo getSetting('time_dinner_sunday_start'); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="time_dinner_sunday_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="time_dinner_sunday_end"
                                            name="time_dinner_sunday_end"
                                            value="<?php echo getSetting('time_dinner_sunday_end'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary-custom btn-custom btn-lg">
                                <i class="fas fa-clock me-2"></i>Update Time Slots
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Special Pricing Management -->
        <div class="card card-dashboard animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-tags me-2"></i>Special Pricing Overrides
                </h4>
                <button type="button" class="btn btn-light btn-custom" data-bs-toggle="modal"
                    data-bs-target="#addSpecialPriceModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Special Price
                </button>
            </div>
            <div class="card-body">
                <?php if ($special_pricing_list_query->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-calendar me-2"></i>Date</th>
                                    <th><i class="fas fa-utensils me-2"></i>Meal Type</th>
                                    <th><i class="fas fa-rupee-sign me-2"></i>Price</th>
                                    <th><i class="fas fa-comment me-2"></i>Description</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($price = $special_pricing_list_query->fetch_assoc()): ?>
                                    <tr>
                                        <td><span
                                                class="special-price-badge"><?php echo htmlspecialchars($price['id']); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($price['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $price['meal_type']))); ?>
                                            </span>
                                        </td>
                                        <td><strong>₹<?php echo number_format($price['price'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($price['description'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#editSpecialPriceModal"
                                                    data-id="<?php echo $price['id']; ?>"
                                                    data-date="<?php echo htmlspecialchars($price['date']); ?>"
                                                    data-mealtype="<?php echo htmlspecialchars($price['meal_type']); ?>"
                                                    data-price="<?php echo htmlspecialchars($price['price']); ?>"
                                                    data-description="<?php echo htmlspecialchars($price['description'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="admin-manage-pricing.php" method="post" class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this special price?');">
                                                    <input type="hidden" name="action" value="delete_special_price">
                                                    <input type="hidden" name="special_price_id"
                                                        value="<?php echo $price['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No special pricing overrides found</h5>
                        <p class="text-muted">Add special prices for holidays or events</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="bulk_update_prices">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkUpdateModalLabel">
                            <i class="fas fa-percentage me-2"></i>Bulk Price Update
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bulkPercentage" class="form-label">Percentage Change</label>
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" id="bulkPercentage"
                                    name="bulk_percentage" placeholder="10" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Use positive numbers to increase, negative to decrease</small>
                        </div>
                        <div class="mb-3">
                            <label for="priceType" class="form-label">Apply To</label>
                            <select class="form-select" id="priceType" name="price_type" required>
                                <option value="">-- Select Price Type --</option>
                                <option value="all">All Prices</option>
                                <option value="meals">Meal Prices Only</option>
                                <option value="monthly">Monthly Passes Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom">Apply Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Special Price Modal -->
    <div class="modal fade" id="addSpecialPriceModal" tabindex="-1" aria-labelledby="addSpecialPriceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="add_special_price">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSpecialPriceModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add New Special Price
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="addDate" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="addMealType" class="form-label">Meal Type</label>
                            <select class="form-select" id="addMealType" name="meal_type" required>
                                <option value="">-- Select Meal Type --</option>
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch_weekday">Lunch (Weekday)</option>
                                <option value="dinner_weekday">Dinner (Weekday)</option>
                                <option value="lunch_sunday">Lunch (Sunday)</option>
                                <option value="dinner_sunday">Dinner (Sunday)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="addPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" id="addPrice" name="price" min="0"
                                    required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="addDescription" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="addDescription" name="description" rows="3"
                                placeholder="Special occasion, holiday, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary-custom">Add Special Price</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Special Price Modal -->
    <div class="modal fade" id="editSpecialPriceModal" tabindex="-1" aria-labelledby="editSpecialPriceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="edit_special_price">
                    <input type="hidden" name="special_price_id" id="editSpecialPriceId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSpecialPriceModalLabel">
                            <i class="fas fa-edit me-2"></i>Edit Special Price
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="editDate" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="editMealType" class="form-label">Meal Type</label>
                            <select class="form-select" id="editMealType" name="meal_type" required>
                                <option value="">-- Select Meal Type --</option>
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch_weekday">Lunch (Weekday)</option>
                                <option value="dinner_weekday">Dinner (Weekday)</option>
                                <option value="lunch_sunday">Lunch (Sunday)</option>
                                <option value="dinner_sunday">Dinner (Sunday)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" id="editPrice" name="price"
                                    min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"
                                placeholder="Special occasion, holiday, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary-custom">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
    });

    // Populate edit special price modal
    var editSpecialPriceModal = document.getElementById('editSpecialPriceModal');
    editSpecialPriceModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var date = button.getAttribute('data-date');
        var mealType = button.getAttribute('data-mealtype');
        var price = button.getAttribute('data-price');
        var description = button.getAttribute('data-description');

        var modalIdInput = editSpecialPriceModal.querySelector('#editSpecialPriceId');
        var modalDateInput = editSpecialPriceModal.querySelector('#editDate');
        var modalMealTypeSelect = editSpecialPriceModal.querySelector('#editMealType');
        var modalPriceInput = editSpecialPriceModal.querySelector('#editPrice');
        var modalDescriptionInput = editSpecialPriceModal.querySelector('#editDescription');

        modalIdInput.value = id;
        modalDateInput.value = date;
        modalMealTypeSelect.value = mealType;
        modalPriceInput.value = price;
        modalDescriptionInput.value = description;
    });

    // Add animation to cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.card-dashboard').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
</script>

<?php
// Include footer
include 'footer.php';
?>