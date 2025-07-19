<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to safely include files
function safe_include($file) {
    if (file_exists($file)) {
        try {
            include $file;
            return true;
        } catch (Exception $e) {
            echo "Error including $file: " . $e->getMessage();
            return false;
        }
    } else {
        echo "File not found: $file";
        return false;
    }
}

// Try to load config
try {
    require_once 'config.php';
} catch (Exception $e) {
    die("Config error: " . $e->getMessage());
}

// Try to get database connection
try {
    $conn = getDBConnection();
    if (!$conn) {
        die("Database connection failed");
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch pricing settings from database
$pricing_settings = [];
try {
    $settings_query = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'price_%' OR setting_key LIKE 'time_%'");
    if ($settings_query) {
        while ($row = $settings_query->fetch_assoc()) {
            $pricing_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    echo "Settings query error: " . $e->getMessage();
}

// Default pricing if not found in database
$default_prices = [
    'price_breakfast' => 30,
    'price_lunch_weekday' => 65,
    'price_lunch_sunday' => 80,
    'price_dinner_weekday' => 65,
    'price_dinner_sunday' => 50,
    'price_in_campus_monthly' => 3900,
    'price_out_campus_monthly' => 1690
];

// Default time slots if not found in database
$default_times = [
    'time_breakfast_start' => '06:00',
    'time_breakfast_end' => '10:00',
    'time_lunch_weekday_start' => '12:00',
    'time_lunch_weekday_end' => '15:00',
    'time_lunch_sunday_start' => '12:00',
    'time_lunch_sunday_end' => '15:00',
    'time_dinner_weekday_start' => '19:00',
    'time_dinner_weekday_end' => '22:00',
    'time_dinner_sunday_start' => '19:00',
    'time_dinner_sunday_end' => '22:00'
];

// Merge with defaults
$pricing_settings = array_merge($default_prices, $default_times, $pricing_settings);

// Get current pricing
function getPrice($key) {
    global $pricing_settings;
    return $pricing_settings[$key] ?? 0;
}

// Get current time settings
function getTime($key) {
    global $pricing_settings;
    return $pricing_settings[$key] ?? '00:00';
}

// Try to get mess data
try {
    $mess_list = $conn->query("SELECT * FROM mess WHERE status = 'active'");
    if (!$mess_list) {
        die("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    die("Mess query error: " . $e->getMessage());
}

// Initialize default meal limits first
$meal_limits = [
    'breakfast' => 10,
    'lunch' => 10,
    'dinner' => 10
];

// Fetch meal limits for person dropdown and merge with defaults
try {
    $limits_query = $conn->query("SELECT meal_type, max_persons_per_booking FROM mess_meal_limits");
    if ($limits_query && $limits_query->num_rows > 0) {
        while ($row = $limits_query->fetch_assoc()) {
            $meal_type = strtolower(trim($row['meal_type']));
            $max_persons = (int)$row['max_persons_per_booking'];
            
            // Map database meal types to our standard keys
            switch($meal_type) {
                case 'breakfast':
                    $meal_limits['breakfast'] = $max_persons;
                    break;
                case 'lunch':
                case 'lunch_weekday':
                case 'lunch_sunday':
                    $meal_limits['lunch'] = $max_persons;
                    break;
                case 'dinner':
                case 'dinner_weekday':
                case 'dinner_sunday':
                    $meal_limits['dinner'] = $max_persons;
                    break;
            }
        }
    }
} catch (Exception $e) {
    // If table doesn't exist or query fails, keep defaults
    error_log("Meal limits query error: " . $e->getMessage());
}

// Helper function to get meal limit safely
function getMealLimit($mealType) {
    global $meal_limits;
    return isset($meal_limits[$mealType]) ? (int)$meal_limits[$mealType] : 10;
}

// Helper functions
function isTimeSlotAvailable($slot) {
    $currentTime = date('H:i');
    $startTime = '';
    $endTime = '';
    
    switch($slot) {
        case 'breakfast':
            $startTime = getTime('time_breakfast_start');
            $endTime = getTime('time_breakfast_end');
            break;
        case 'lunch_weekday':
            if (!isSunday()) {
                $startTime = getTime('time_lunch_weekday_start');
                $endTime = getTime('time_lunch_weekday_end');
            }
            break;
        case 'lunch_sunday':
            if (isSunday()) {
                $startTime = getTime('time_lunch_sunday_start');
                $endTime = getTime('time_lunch_sunday_end');
            }
            break;
        case 'dinner_weekday':
            if (!isSunday()) {
                $startTime = getTime('time_dinner_weekday_start');
                $endTime = getTime('time_dinner_weekday_end');
            }
            break;
        case 'dinner_sunday':
            if (isSunday()) {
                $startTime = getTime('time_dinner_sunday_start');
                $endTime = getTime('time_dinner_sunday_end');
            }
            break;
        default:
            return false;
    }
    
    if (empty($startTime) || empty($endTime)) {
        return false;
    }
    
    return $currentTime >= $startTime && $currentTime <= $endTime;
}

function isSunday() {
    return date('w') == 0;
}

// Include header safely
safe_include('header/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        .mess-booking {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .booking-container {
            max-width: 650px;
            margin: 0 auto;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 10px;
        }
        .step-indicator div {
            flex: 1;
            text-align: center;
            padding: 12px 8px;
            background: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.7);
            border-radius: 25px;
            margin: 0 5px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .step-indicator div.active {
            background: rgba(255,255,255,0.9);
            color: #667eea;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .step {
            display: none;
            animation: fadeInUp 0.5s ease;
        }
        .step.active {
            display: block;
        }
        .form-check-label {
            cursor: pointer;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 15px;
            display: block;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-check-label:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }
        .form-check-input:checked + .form-check-label {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .pricing-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 25px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        .pricing-info ul {
            list-style: none;
            padding-left: 0;
        }
        .pricing-info li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pricing-info li:last-child {
            border-bottom: none;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        .card-body {
            padding: 30px;
        }
        .btn-custom {
            border-radius: 25px;
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
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .time-slot-info {
            background: rgba(255,193,7,0.1);
            border: 1px solid rgba(255,193,7,0.3);
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 12px;
        }
        .unavailable-slot {
            opacity: 0.5;
            pointer-events: none;
        }
        .meal-limit-info {
            background: rgba(23,162,184,0.1);
            border: 1px solid rgba(23,162,184,0.3);
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 11px;
            color: #17a2b8;
        }
        @media (max-width: 768px) {
            .step-indicator div {
                font-size: 11px;
                padding: 10px 5px;
            }
            .booking-container {
                padding: 15px;
            }
        }

/* Toast notification styles */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-notification {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
    padding: 15px 20px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(255, 65, 108, 0.3);
    margin-bottom: 10px;
    min-width: 300px;
    max-width: 400px;
    transform: translateX(100%);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-notification.success {
    background: linear-gradient(135deg, #56ab2f, #a8e6cf);
}

.toast-notification.warning {
    background: linear-gradient(135deg, #f7971e, #ffd200);
    color: #333;
}

.toast-notification .toast-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.toast-notification .toast-title {
    font-weight: 600;
    font-size: 14px;
}

.toast-notification .toast-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.3s ease;
}

.toast-notification .toast-close:hover {
    opacity: 1;
}

.toast-notification .toast-body {
    font-size: 13px;
    line-height: 1.4;
}

.toast-notification .toast-icon {
    margin-right: 8px;
    font-size: 16px;
}

/* Validation error styling */
.form-control.error, .form-select.error {
    border-color: #ff416c;
    box-shadow: 0 0 0 0.2rem rgba(255, 65, 108, 0.25);
}

.form-check-input.error + .form-check-label {
    border-color: #ff416c;
    background-color: rgba(255, 65, 108, 0.1);
}

/* Step indicator error state */
.step-indicator div.error {
    background: rgba(255, 65, 108, 0.2);
    color: #ff416c;
    border: 2px solid #ff416c;
}
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row p-0 m-0">
        <!-- Left Column -->
        <div class="col-lg-6 mess-booking p-0 h-100">
            <div class="p-4">
                <h1 class="text-white pt-4 px-3 animate__animated animate__fadeInLeft">
                    <i class="fas fa-utensils me-3"></i>PU Mess Coupon Booking
                </h1>
                
                <div class="alert alert-info mt-4 animate__animated animate__fadeInLeft animate__delay-1s" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white;">
                    <h5><i class="fas fa-info-circle me-2"></i>Important Information:</h5>
                    <ul class="mb-0">
                        <li>Coupons can be booked only for the present day</li>
                        <li>Coupons are non-refundable and non-transferable</li>
                        <li>Post booking, download ticket and show it at the mess</li>
                        <li>Booking limits apply per meal type</li>
                    </ul>
                </div>
                
                <div class="d-flex justify-content-between align-items-start">
                    
                <div class="pricing-info mt-3 text-white animate__animated animate__fadeInLeft animate__delay-2s">
                    <h5><i class="fas fa-rupee-sign me-2"></i>Current Pricing:</h5>
                    <ul>
                        <li>
                            <span><i class="fas fa-coffee me-2"></i>Breakfast:</span>
                            <strong>₹<?php echo number_format(getPrice('price_breakfast'), 0); ?>/meal</strong>
                        </li>
                        <li>
                            <span><i class="fas fa-utensils me-2"></i>Lunch (Weekday):</span>
                            <strong>₹<?php echo number_format(getPrice('price_lunch_weekday'), 0); ?>/meal</strong>
                        </li>
                        <li>
                            <span><i class="fas fa-utensils me-2"></i>Lunch (Sunday):</span>
                            <strong>₹<?php echo number_format(getPrice('price_lunch_sunday'), 0); ?>/meal</strong>
                        </li>
                        <li>
                            <span><i class="fas fa-moon me-2"></i>Dinner (Weekday):</span>
                            <strong>₹<?php echo number_format(getPrice('price_dinner_weekday'), 0); ?>/meal</strong>
                        </li>
                        <li>
                            <span><i class="fas fa-moon me-2"></i>Dinner (Sunday):</span>
                            <strong>₹<?php echo number_format(getPrice('price_dinner_sunday'), 0); ?>/meal</strong>
                        </li>
                        <li>
                            <span><i class="fas fa-building me-2"></i>Monthly (In Campus):</span>
                            <strong>₹<?php echo number_format(getPrice('price_in_campus_monthly'), 0); ?></strong>
                        </li>
                        <li>
                            <span><i class="fas fa-home me-2"></i>Monthly (Out Campus):</span>
                            <strong>₹<?php echo number_format(getPrice('price_out_campus_monthly'), 0); ?></strong>
                        </li>
                    </ul>
                </div>

                <div class="pricing-info mt-3 text-white animate__animated animate__fadeInLeft animate__delay-3s">
                    <h6><i class="fas fa-clock me-2"></i>Today's Meal Timings:</h6>
                    <ul>
                        <li>
                            <span>Breakfast:</span>
                            <strong><?php echo date('g:i A', strtotime(getTime('time_breakfast_start'))); ?> - <?php echo date('g:i A', strtotime(getTime('time_breakfast_end'))); ?></strong>
                        </li>
                        <li>
                            <span>Lunch:</span>
                            <strong><?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_lunch_sunday_start' : 'time_lunch_weekday_start'))); ?> - <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_lunch_sunday_end' : 'time_lunch_weekday_end'))); ?></strong>
                        </li>
                        <li>
                            <span>Dinner:</span>
                            <strong><?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_dinner_sunday_start' : 'time_dinner_weekday_start'))); ?> - <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_dinner_sunday_end' : 'time_dinner_weekday_end'))); ?></strong>
                        </li>
                    </ul>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
            <div class="booking-container w-100 p-4">
                <div class="step-indicator">
                    <div class="active" id="step1-indicator">1. Select Mess</div>
                    <div id="step2-indicator">2. Choose Options</div>
                    <div id="step3-indicator">3. Personal Details</div>
                    <div id="step4-indicator">4. Confirm</div>
                </div>
                
                <form id="bookingForm" action="process_booking.php" method="post">
                    <!-- Step 1: Choose Mess -->
                    <div class="step active" id="step1">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-4"><i class="fas fa-store me-2"></i>Choose a Mess</h3>
                                <?php if ($mess_list && $mess_list->num_rows > 0): ?>
                                    <?php while($mess = $mess_list->fetch_assoc()): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="mess_id" value="<?php echo $mess['id']; ?>" id="mess<?php echo $mess['id']; ?>" required>
                                        <label class="form-check-label" for="mess<?php echo $mess['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><i class="fas fa-utensils me-2"></i><?php echo htmlspecialchars($mess['name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($mess['location']); ?> |
                                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($mess['contact']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="badge bg-success">Active</small><br>
                                                    <small class="text-muted">Capacity: <?php echo htmlspecialchars($mess['daily_capacity'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No active mess found. Please contact administrator.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Booking Options -->
                    <div class="step" id="step2">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-4"><i class="fas fa-cogs me-2"></i>Select Options</h3>
                                
                                <!-- User Type -->
                                <div class="mb-4">
                                    <label class="form-label"><strong><i class="fas fa-user me-2"></i>User Type:</strong></label>
                                    <div class="row">
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="staff" id="staff" required>
                                                <label class="form-check-label text-center" for="staff">
                                                    <i class="fas fa-user-tie fa-2x d-block mb-2"></i>Staff
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="student" id="student" required>
                                                <label class="form-check-label text-center" for="student">
                                                    <i class="fas fa-user-graduate fa-2x d-block mb-2"></i>Student
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="visitor" id="visitor" required>
                                                <label class="form-check-label text-center" for="visitor">
                                                    <i class="fas fa-user-friends fa-2x d-block mb-2"></i>Visitor
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Coupon Type -->
                                <div class="mb-4">
                                    <label class="form-label"><strong><i class="fas fa-ticket-alt me-2"></i>Coupon Type:</strong></label>
                                    
                                    <!-- Monthly Options -->
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="in_campus_monthly" id="inCampusMonthly">
                                        <label class="form-check-label" for="inCampusMonthly">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-building me-2"></i>Monthly (In Campus)</span>
                                                <strong class="text-success">₹<?php echo number_format(getPrice('price_in_campus_monthly'), 0); ?></strong>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="out_campus_monthly" id="outCampusMonthly">
                                        <label class="form-check-label" for="outCampusMonthly">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-home me-2"></i>Monthly (Out Campus)</span>
                                                <strong class="text-success">₹<?php echo number_format(getPrice('price_out_campus_monthly'), 0); ?></strong>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <hr>
                                    <h6><i class="fas fa-utensils me-2"></i>Single Meal Options:</h6>
                                    
                                    <!-- Breakfast -->
                                    <?php 
                                    $breakfast_available = isTimeSlotAvailable('breakfast');
                                    $breakfast_class = $breakfast_available ? '' : 'unavailable-slot';
                                    ?>
                                    <div class="form-check mb-2 <?php echo $breakfast_class; ?>">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="breakfast" id="breakfast" <?php echo $breakfast_available ? '' : 'disabled'; ?>>
                                        <label class="form-check-label" for="breakfast">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-coffee me-2"></i>Breakfast</span>
                                                <strong class="text-success">₹<?php echo number_format(getPrice('price_breakfast'), 0); ?></strong>
                                            </div>
                                            <?php if (!$breakfast_available): ?>
                                                <div class="time-slot-info">
                                                    <i class="fas fa-clock me-1"></i>Available: <?php echo date('g:i A', strtotime(getTime('time_breakfast_start'))); ?> - <?php echo date('g:i A', strtotime(getTime('time_breakfast_end'))); ?>
                                                </div>
                                            <?php endif; ?>
                                          
                                        </label>
                                    </div>

                                    <!-- Lunch -->
                                    <?php 
                                    $lunch_available = isTimeSlotAvailable('lunch_weekday') || isTimeSlotAvailable('lunch_sunday');
                                    $lunch_class = $lunch_available ? '' : 'unavailable-slot';
                                    $lunch_price = isSunday() ? getPrice('price_lunch_sunday') : getPrice('price_lunch_weekday');
                                    ?>
                                    <div class="form-check mb-2 <?php echo $lunch_class; ?>">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="lunch" id="lunch" <?php echo $lunch_available ? '' : 'disabled'; ?>>
                                        <label class="form-check-label" for="lunch">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-utensils me-2"></i>Lunch <?php echo isSunday() ? '(Sunday)' : '(Weekday)'; ?></span>
                                                <strong class="text-success">₹<?php echo number_format($lunch_price, 0); ?></strong>
                                            </div>
                                            <?php if (!$lunch_available): ?>
                                                <div class="time-slot-info">
                                                    <i class="fas fa-clock me-1"></i>Available: <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_lunch_sunday_start' : 'time_lunch_weekday_start'))); ?> - <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_lunch_sunday_end' : 'time_lunch_weekday_end'))); ?>
                                                </div>
                                            <?php endif; ?>
                                           
                                        </label>
                                    </div>

                                    <!-- Dinner -->
                                    <?php 
                                    $dinner_available = isTimeSlotAvailable('dinner_weekday') || isTimeSlotAvailable('dinner_sunday');
                                    $dinner_class = $dinner_available ? '' : 'unavailable-slot';
                                    $dinner_price = isSunday() ? getPrice('price_dinner_sunday') : getPrice('price_dinner_weekday');
                                    ?>
                                    <div class="form-check mb-2 <?php echo $dinner_class; ?>">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="dinner" id="dinner" <?php echo $dinner_available ? '' : 'disabled'; ?>>
                                        <label class="form-check-label" for="dinner">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-moon me-2"></i>Dinner <?php echo isSunday() ? '(Sunday)' : '(Weekday)'; ?></span>
                                                <strong class="text-success">₹<?php echo number_format($dinner_price, 0); ?></strong>
                                            </div>
                                            <?php if (!$dinner_available): ?>
                                                <div class="time-slot-info">
                                                    <i class="fas fa-clock me-1"></i>Available: <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_dinner_sunday_start' : 'time_dinner_weekday_start'))); ?> - <?php echo date('g:i A', strtotime(getTime(isSunday() ? 'time_dinner_sunday_end' : 'time_dinner_weekday_end'))); ?>
                                                </div>
                                            <?php endif; ?>
                                          
                                        </label>
                                    </div>
                                </div>

                                <!-- Number of Persons -->
                                <div class="mb-3">
                                    <label for="persons" class="form-label"><strong><i class="fas fa-users me-2"></i>Number of Persons:</strong></label>
                                    <select name="persons" id="persons" class="form-select" required>
                                        <option value="">Select number of persons</option>
                                        <!-- Options will be populated by JavaScript based on meal selection -->
                                    </select>
                                    <small class="text-muted">Maximum persons allowed varies by meal type</small>
                                </div>

                                <input type="hidden" name="meal_type" id="mealType" value="">
                                <input type="hidden" name="booking_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Personal Details -->
                    <div class="step" id="step3">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-4"><i class="fas fa-user-edit me-2"></i>Personal Details</h3>
                                
                                <div class="mb-3">
                                    <label for="user_name" class="form-label"><i class="fas fa-user me-2"></i>Full Name *</label>
                                    <input type="text" class="form-control" id="user_name" name="user_name" placeholder="Enter your full name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="user_email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address *</label>
                                    <input type="email" class="form-control" id="user_email" name="user_email" placeholder="Enter your email address" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="user_phone" class="form-label"><i class="fas fa-phone me-2"></i>Phone Number *</label>
                                    <input type="tel" class="form-control" id="user_phone" name="user_phone" pattern="[0-9]{10}" placeholder="Enter 10-digit phone number" >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Confirmation -->
                    <div class="step" id="step4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-4"><i class="fas fa-check-circle me-2"></i>Booking Summary</h3>
                                <div id="bookingSummary"></div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                                    <label class="form-check-label" for="terms_accepted">
                                        <i class="fas fa-file-contract me-2"></i>I agree to the terms and conditions
                                    </label>
                                </div>
                                <input type="hidden" name="total_amount" id="totalAmountInput" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary btn-custom me-2" id="prevBtn" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn btn-primary-custom btn-custom" id="nextBtn">
                            Next<i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        <button type="submit" class="btn btn-success-custom btn-custom" id="submitBtn" style="display: none;">
                            <i class="fas fa-check me-2"></i>Confirm Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// PHP data passed to JavaScript - properly encoded
const phpData = <?php echo json_encode([
    'mealLimits' => $meal_limits,
    'pricing' => [
        'price_breakfast' => (float)getPrice('price_breakfast'),
        'price_lunch_weekday' => (float)getPrice('price_lunch_weekday'),
        'price_lunch_sunday' => (float)getPrice('price_lunch_sunday'),
        'price_dinner_weekday' => (float)getPrice('price_dinner_weekday'),
        'price_dinner_sunday' => (float)getPrice('price_dinner_sunday'),
        'price_in_campus_monthly' => (float)getPrice('price_in_campus_monthly'),
        'price_out_campus_monthly' => (float)getPrice('price_out_campus_monthly')
    ],
    'isSunday' => isSunday(),
    'currentDate' => date('l, F j, Y')
]); ?>;

let currentStep = 1;
const totalSteps = 4;

// Extract data from PHP
const mealLimits = phpData.mealLimits;
const pricing = phpData.pricing;
const isSunday = phpData.isSunday;
const currentDate = phpData.currentDate;

// Toast notification system
function showToast(message, type = 'error', duration = 5000) {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast_' + Date.now();
    
    const icons = {
        error: 'fas fa-exclamation-circle',
        success: 'fas fa-check-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    const titles = {
        error: 'Validation Error',
        success: 'Success',
        warning: 'Warning',
        info: 'Information'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.id = toastId;
    toast.innerHTML = `
        <div class="toast-header">
            <div class="toast-title">
                <i class="${icons[type]} toast-icon"></i>
                ${titles[type]}
            </div>
            <button class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
        </div>
        <div class="toast-body">${message}</div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        closeToast(toastId);
    }, duration);
    
    return toastId;
}

function closeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}

function clearValidationErrors() {
    // Remove error classes from all form elements
    document.querySelectorAll('.form-control.error, .form-select.error').forEach(el => {
        el.classList.remove('error');
    });
    
    document.querySelectorAll('.form-check-input.error').forEach(el => {
        el.classList.remove('error');
    });
    
    document.querySelectorAll('.step-indicator div.error').forEach(el => {
        el.classList.remove('error');
    });
}

function showFieldError(fieldName, message) {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (field) {
        field.classList.add('error');
        if (field.type !== 'radio' && field.type !== 'checkbox') {
            field.focus();
        }
    }
    
    // Mark step indicator as error
    const stepIndicator = document.getElementById(`step${currentStep}-indicator`);
    if (stepIndicator) {
        stepIndicator.classList.add('error');
    }
    
    showToast(message, 'error');
}

function showStep(step) {
    console.log('Showing step:', step);
    
    // Clear any previous validation errors
    clearValidationErrors();
    
    // Hide all steps
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    
    // Reset all step indicators
    document.querySelectorAll('.step-indicator div').forEach(s => s.classList.remove('active'));
    
    // Show current step
    const currentStepElement = document.getElementById('step' + step);
    if (currentStepElement) {
        currentStepElement.classList.add('active');
    }
    
    // Activate current step indicator only
    const currentIndicator = document.getElementById('step' + step + '-indicator');
    if (currentIndicator) {
        currentIndicator.classList.add('active');
    }
    
    // Update button visibility
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    if (prevBtn) prevBtn.style.display = step === 1 ? 'none' : 'inline-block';
    if (nextBtn) nextBtn.style.display = step === totalSteps ? 'none' : 'inline-block';
    if (submitBtn) submitBtn.style.display = step === totalSteps ? 'inline-block' : 'none';
}

function validateCurrentStep() {
    console.log('Validating step:', currentStep);
    
    // Clear previous errors
    clearValidationErrors();
    
    if (currentStep === 1) {
        // Validate mess selection
        const messSelected = document.querySelector('input[name="mess_id"]:checked');
        if (!messSelected) {
            showFieldError('mess_id', 'Please select a mess to continue');
            return false;
        }
        console.log('Step 1 validation passed');
        showToast('Mess selected successfully!', 'success', 2000);
        return true;
    }
    
    if (currentStep === 2) {
        // Validate user type
        const userTypeSelected = document.querySelector('input[name="user_type"]:checked');
        if (!userTypeSelected) {
            showFieldError('user_type', 'Please select your user type (Staff, Student, or Visitor)');
            return false;
        }
        
        // Validate coupon type
        const couponTypeSelected = document.querySelector('input[name="coupon_type"]:checked');
        if (!couponTypeSelected) {
            showFieldError('coupon_type', 'Please select a coupon type (Monthly or Single Meal)');
            return false;
        }
        
        // Validate persons
        const personsSelect = document.getElementById('persons');
        if (!personsSelect.value) {
            showFieldError('persons', 'Please select the number of persons for this booking');
            return false;
        }
        
        console.log('Step 2 validation passed');
        showToast('Booking options selected successfully!', 'success', 2000);
        return true;
    }
    
    if (currentStep === 3) {
        // Validate personal details
        const userName = document.getElementById('user_name');
        const userEmail = document.getElementById('user_email');
        const userPhone = document.getElementById('user_phone');
        
        if (!userName.value.trim()) {
            showFieldError('user_name', 'Please enter your full name');
            return false;
        }
        
        if (userName.value.trim().length < 2) {
            showFieldError('user_name', 'Name must be at least 2 characters long');
            return false;
        }
        
        if (!userEmail.value.trim()) {
            showFieldError('user_email', 'Please enter your email address');
            return false;
        }
        
        // Validate email format
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(userEmail.value)) {
            showFieldError('user_email', 'Please enter a valid email address');
            return false;
        }
        
        if (!userPhone.value.trim()) {
            showFieldError('user_phone', 'Please enter your phone number');
            return false;
        }
        
        // Validate phone number format
        const phonePattern = /^[0-9]{10}$/;
        if (!phonePattern.test(userPhone.value)) {
            showFieldError('user_phone', 'Please enter a valid 10-digit phone number (numbers only)');
            return false;
        }
        
        console.log('Step 3 validation passed');
        showToast('Personal details validated successfully!', 'success', 2000);
        return true;
    }
    
    if (currentStep === 4) {
        // Validate terms acceptance
        const termsAccepted = document.getElementById('terms_accepted');
        if (!termsAccepted.checked) {
            showFieldError('terms_accepted', 'Please accept the terms and conditions to proceed');
            return false;
        }
        
        console.log('Step 4 validation passed');
        showToast('Ready to submit booking!', 'success', 2000);
        return true;
    }
    
    return true;
}

function changeStep(direction) {
    console.log('changeStep called with direction:', direction, 'current step:', currentStep);
    
    if (direction === 1) {
        if (!validateCurrentStep()) {
            console.log('Validation failed for step:', currentStep);
            return;
        }
    }
    
    currentStep += direction;
    if (currentStep < 1) currentStep = 1;
    if (currentStep > totalSteps) currentStep = totalSteps;
    
    console.log('Moving to step:', currentStep);
    
    if (currentStep === 4) {
        updateBookingSummary();
    }
    
    showStep(currentStep);
}

function updatePersonsDropdown() {
    const couponTypeInputs = document.querySelectorAll('input[name="coupon_type"]');
    const personsSelect = document.getElementById('persons');
    let maxPersons = 10; // default
    
    couponTypeInputs.forEach(input => {
        if (input.checked) {
            if (input.value === 'single_meal') {
                const mealType = input.dataset.meal;
                maxPersons = mealLimits[mealType] || 10;
            } else {
                maxPersons = 10; // Monthly passes can have more flexibility
            }
        }
    });
    
    // Clear existing options except the first one
    personsSelect.innerHTML = '<option value="">Select number of persons</option>';
    
    // Add options based on limit
    for (let i = 1; i <= maxPersons; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + ' Person' + (i > 1 ? 's' : '');
        personsSelect.appendChild(option);
    }
    
    console.log('Updated persons dropdown with max:', maxPersons);
    
    // Show info toast about the limit
    if (maxPersons < 10) {
        showToast(`Maximum ${maxPersons} persons allowed for this meal type`, 'info', 3000);
    }
}

function updateBookingSummary() {
    const formData = new FormData(document.getElementById('bookingForm'));
    const couponType = formData.get('coupon_type');
    const persons = formData.get('persons');
    const userType = formData.get('user_type');
    const userName = formData.get('user_name');
    const userEmail = formData.get('user_email');
    const userPhone = formData.get('user_phone');
    
    let price = 0;
    let description = '';
    
    if (couponType === 'in_campus_monthly') {
        price = pricing.price_in_campus_monthly;
        description = 'Monthly (In Campus)';
    } else if (couponType === 'out_campus_monthly') {
        price = pricing.price_out_campus_monthly;
        description = 'Monthly (Out Campus)';
    } else if (couponType === 'single_meal') {
        const mealType = document.querySelector('input[name="coupon_type"]:checked').dataset.meal;
        document.getElementById('mealType').value = mealType;
        
        if (mealType === 'breakfast') {
            price = pricing.price_breakfast;
            description = 'Breakfast';
        } else if (mealType === 'lunch') {
            price = isSunday ? pricing.price_lunch_sunday : pricing.price_lunch_weekday;
            description = 'Lunch' + (isSunday ? ' (Sunday)' : ' (Weekday)');
        } else if (mealType === 'dinner') {
            price = isSunday ? pricing.price_dinner_sunday : pricing.price_dinner_weekday;
            description = 'Dinner' + (isSunday ? ' (Sunday)' : ' (Weekday)');
        }
    }
    
    const totalAmount = price * persons;
    document.getElementById('totalAmountInput').value = totalAmount;
    
    document.getElementById('bookingSummary').innerHTML = `
        <div class="table-responsive">
            <table class="table table-borderless">
                <tr><td><strong><i class="fas fa-user me-2"></i>Name:</strong></td><td>${userName}</td></tr>
                <tr><td><strong><i class="fas fa-envelope me-2"></i>Email:</strong></td><td>${userEmail}</td></tr>
                <tr><td><strong><i class="fas fa-phone me-2"></i>Phone:</strong></td><td>${userPhone}</td></tr>
                <tr><td><strong><i class="fas fa-user-tag me-2"></i>User Type:</strong></td><td>${userType.charAt(0).toUpperCase() + userType.slice(1)}</td></tr>
                <tr><td><strong><i class="fas fa-ticket-alt me-2"></i>Coupon Type:</strong></td><td>${description}</td></tr>
                <tr><td><strong><i class="fas fa-users me-2"></i>Persons:</strong></td><td>${persons}</td></tr>
                <tr><td><strong><i class="fas fa-rupee-sign me-2"></i>Price per Person:</strong></td><td>₹${price.toLocaleString()}</td></tr>
                <tr class="table-success"><td><strong><i class="fas fa-calculator me-2"></i>Total Amount:</strong></td><td><strong>₹${totalAmount.toLocaleString()}</strong></td></tr>
                <tr><td><strong><i class="fas fa-calendar me-2"></i>Booking Date:</strong></td><td>${currentDate}</td></tr>
            </table>
        </div>
    `;
    
    showToast('Booking summary generated successfully!', 'success', 3000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    console.log('PHP Data loaded:', phpData);
    
    // Update persons dropdown when coupon type changes
    document.querySelectorAll('input[name="coupon_type"]').forEach(input => {
        input.addEventListener('change', function() {
            console.log('Coupon type changed to:', this.value);
            updatePersonsDropdown();
        });
    });
    
    // Initialize persons dropdown with default values
    const personsSelect = document.getElementById('persons');
    if (personsSelect) {
        personsSelect.innerHTML = '<option value="">Select number of persons</option>';
        for (let i = 1; i <= 10; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = i + ' Person' + (i > 1 ? 's' : '');
            personsSelect.appendChild(option);
        }
    }
    
    // Add click event listeners to buttons
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Next button clicked');
            changeStep(1);
        });
        console.log('Next button event listener added');
    } else {
        console.error('Next button not found!');
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Previous button clicked');
            changeStep(-1);
        });
        console.log('Previous button event listener added');
    }
    
    // Add real-time validation for form fields
    const userNameField = document.getElementById('user_name');
    if (userNameField) {
        userNameField.addEventListener('blur', function() {
            if (this.value.trim() && this.value.trim().length < 2) {
                showToast('Name must be at least 2 characters long', 'warning', 3000);
            }
        });
    }
    
    const userEmailField = document.getElementById('user_email');
    if (userEmailField) {
        userEmailField.addEventListener('blur', function() {
            if (this.value.trim()) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(this.value)) {
                    showToast('Please enter a valid email address', 'warning', 3000);
                }
            }
        });
    }
    
    const userPhoneField = document.getElementById('user_phone');
    if (userPhoneField) {
        userPhoneField.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
            
            if (this.value.length > 0 && this.value.length < 10) {
                showToast(`Phone number must be exactly 10 digits (${this.value.length}/10)`, 'info', 2000);
            }
        });
    }
    
    // Initialize first step
    showStep(1);
    showToast('Welcome! Please select a mess to start booking.', 'info', 4000);
    console.log('Initialization complete');
});
</script>

<?php 
if (isset($conn)) {
    $conn->close();
}
?>
