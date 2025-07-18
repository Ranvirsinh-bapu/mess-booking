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

// Get current date for capacity checking
$current_date = date('Y-m-d');

// Function to get mess available capacity
function getMessAvailableCapacity($mess_id, $booking_date) {
    $conn = getDBConnection();
    
    // Get mess capacity settings
    $capacity_query = $conn->prepare("SELECT daily_capacity, capacity_enabled FROM mess WHERE id = ? AND status = 'active'");
    $capacity_query->bind_param("i", $mess_id);
    $capacity_query->execute();
    $capacity_result = $capacity_query->get_result();
    
    if ($capacity_row = $capacity_result->fetch_assoc()) {
        if (!$capacity_row['capacity_enabled']) {
            return ['available' => 999, 'total' => 999, 'used' => 0]; // No limit
        }
        
        $total_capacity = $capacity_row['daily_capacity'];
        
        // Get used capacity for the date
        $used_query = $conn->prepare("SELECT COALESCE(SUM(persons), 0) as used_capacity FROM bookings WHERE mess_id = ? AND booking_date = ? AND booking_status IN ('active', 'completed')");
        $used_query->bind_param("is", $mess_id, $booking_date);
        $used_query->execute();
        $used_result = $used_query->get_result();
        $used_capacity = $used_result->fetch_assoc()['used_capacity'];
        
        return [
            'available' => max(0, $total_capacity - $used_capacity),
            'total' => $total_capacity,
            'used' => $used_capacity
        ];
    }
    
    return ['available' => 0, 'total' => 0, 'used' => 0];
}

// Try to get mess data with capacity information
try {
    $mess_query = "
        SELECT 
            m.*,
            CASE 
                WHEN m.capacity_enabled = 1 THEN 
                    GREATEST(0, m.daily_capacity - COALESCE((
                        SELECT SUM(b.persons) 
                        FROM bookings b 
                        WHERE b.mess_id = m.id 
                        AND b.booking_date = CURDATE() 
                        AND b.booking_status IN ('active', 'completed')
                    ), 0))
                ELSE 999
            END as available_capacity,
            m.daily_capacity as total_capacity,
            COALESCE((
                SELECT SUM(b.persons) 
                FROM bookings b 
                WHERE b.mess_id = m.id 
                AND b.booking_date = CURDATE() 
                AND b.booking_status IN ('active', 'completed')
            ), 0) as used_capacity
        FROM mess m 
        WHERE m.status = 'active'
        ORDER BY m.name
    ";
    
    $mess_list = $conn->query($mess_query);
    if (!$mess_list) {
        die("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    die("Mess query error: " . $e->getMessage());
}

// Get current pricing from database (using the global variables from config.php)
global $COUPON_PRICES, $TIME_SLOTS;

// Helper functions
function isTimeSlotAvailable($slot) {
    global $TIME_SLOTS;
    
    $currentHour = (int) date('H');
    $currentMinute = (int) date('i');
    $currentMinutes = $currentHour * 60 + $currentMinute;
    
    // Determine the correct slot based on day
    $current_day = date('w'); // 0 = Sunday, 1 = Monday, etc.
    
    if ($current_day == 0) { // Sunday
        if ($slot == 'lunch_weekday') $slot = 'lunch_sunday';
        if ($slot == 'dinner_weekday') $slot = 'dinner_sunday';
    }
    
    if (!isset($TIME_SLOTS[$slot])) return false;
    
    // Convert time strings to minutes
    $start_time = $TIME_SLOTS[$slot]['start'];
    $end_time = $TIME_SLOTS[$slot]['end'];
    
    list($start_hour, $start_min) = explode(':', $start_time);
    list($end_hour, $end_min) = explode(':', $end_time);
    
    $start_minutes = ($start_hour * 60) + $start_min;
    $end_minutes = ($end_hour * 60) + $end_min;
    
    return $currentMinutes >= $start_minutes && $currentMinutes <= $end_minutes;
}

function isSunday() {
    return date('w') == 0;
}

// Get current date for special pricing
$current_date = date('Y-m-d');

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
    <style>
        .mess-booking {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .booking-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        .step-indicator div {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #e9ecef;
            color: #6c757d;
            border-radius: 5px;
            margin: 0 5px;
            font-size: 14px;
            font-weight: 500;
        }
        .step-indicator div.active {
            background: #007bff;
            color: white;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .form-check-label {
            cursor: pointer;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            display: block;
            transition: all 0.3s;
        }
        .form-check-input:checked + .form-check-label {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        .form-check-input:disabled + .form-check-label {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .pricing-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .pricing-info ul {
            list-style: none;
            padding-left: 0;
        }
        .pricing-info li {
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .capacity-info {
            font-size: 0.85em;
            margin-top: 5px;
        }
        .capacity-full {
            color: #dc3545;
            font-weight: bold;
        }
        .capacity-low {
            color: #fd7e14;
            font-weight: bold;
        }
        .capacity-good {
            color: #198754;
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row p-0 m-0">
        <!-- Left Column -->
        <div class="col-md-6 mess-booking p-0">
            <div class="p-4">
                <h1 class="text-white pt-5 px-3">PU Mess Coupon Booking</h1>
                <div class="alert alert-info mt-4">
                    <h5>Important Information:</h5>
                    <ul class="mb-0">
                        <li>Coupons can be booked only for the present day</li>
                        <li>Coupons are non-refundable and non-transferable</li>
                        <li>Post booking, download ticket and show it at the mess</li>
                        <li><strong>Limited capacity available - book early!</strong></li>
                    </ul>
                </div>
                
                <div class="pricing-info mt-4 text-white">
                    <h5>Pricing Information:</h5>
                    <ul>
                        <li><strong>Breakfast:</strong> ₹<?php echo number_format(getEffectivePrice('breakfast', $current_date), 0); ?>/meal</li>
                        <li><strong>Lunch:</strong> ₹<?php echo number_format(getEffectivePrice(isSunday() ? 'lunch_sunday' : 'lunch_weekday', $current_date), 0); ?>/meal <?php echo isSunday() ? '(Sunday)' : '(Weekday)'; ?></li>
                        <li><strong>Dinner:</strong> ₹<?php echo number_format(getEffectivePrice(isSunday() ? 'dinner_sunday' : 'dinner_weekday', $current_date), 0); ?>/meal <?php echo isSunday() ? '(Sunday)' : '(Weekday)'; ?></li>
                        <li><strong>Monthly (In Campus):</strong> ₹<?php echo number_format($COUPON_PRICES['in_campus_monthly'], 0); ?></li>
                        <li><strong>Monthly (Out Campus):</strong> ₹<?php echo number_format($COUPON_PRICES['out_campus_monthly'], 0); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-md-6 d-flex align-items-center justify-content-center">
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
                                <h3 class="mb-3">Choose a Mess</h3>
                                <?php if ($mess_list && $mess_list->num_rows > 0): ?>
                                    <?php while($mess = $mess_list->fetch_assoc()): ?>
                                    <?php 
                                        $is_full = $mess['available_capacity'] <= 0 && $mess['total_capacity'] < 999;
                                        $is_low = $mess['available_capacity'] <= 5 && $mess['available_capacity'] > 0 && $mess['total_capacity'] < 999;
                                        $capacity_class = $is_full ? 'capacity-full' : ($is_low ? 'capacity-low' : 'capacity-good');
                                    ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="mess_id" value="<?php echo $mess['id']; ?>" 
                                               id="mess<?php echo $mess['id']; ?>" 
                                               data-capacity="<?php echo $mess['available_capacity']; ?>"
                                               <?php echo $is_full ? 'disabled' : ''; ?> required>
                                        <label class="form-check-label" for="mess<?php echo $mess['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <strong><?php echo htmlspecialchars($mess['name']); ?></strong>
                                                  <small class="text-muted">
                                                        <?php echo htmlspecialchars($mess['location']); ?> | 
                                                        <?php echo htmlspecialchars($mess['contact']); ?>
                                                    </small>
                                                
                                            </div>
                                        
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php 
                                    // Check if all mess are full
                                    $mess_list->data_seek(0);
                                    $all_full = true;
                                    while($mess = $mess_list->fetch_assoc()) {
                                        if ($mess['available_capacity'] > 0 || $mess['total_capacity'] >= 999) {
                                            $all_full = false;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($all_full): ?>
                                    <div class="alert alert-danger mt-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>All mess are currently full!</strong> Please try again later or contact the administrator.
                                    </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="alert alert-warning">No active mess found. Please contact administrator.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Booking Options -->
                    <div class="step" id="step2">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-3">Select Options</h3>
                                
                                <!-- User Type -->
                                <div class="mb-4">
                                    <label class="form-label"><strong>User Type:</strong></label>
                                    <div class="row">
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="staff" id="staff" required>
                                                <label class="form-check-label" for="staff">Staff</label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="student" id="student" required>
                                                <label class="form-check-label" for="student">Student</label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="user_type" value="visitor" id="visitor" required>
                                                <label class="form-check-label" for="visitor">Visitor</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Coupon Type -->
                                <div class="mb-4">
                                    <label class="form-label"><strong>Coupon Type:</strong></label>
                                    
                                    <!-- Monthly Options -->
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="in_campus_monthly" id="inCampusMonthly">
                                        <label class="form-check-label" for="inCampusMonthly">
                                            Monthly (In Campus) - ₹<?php echo number_format($COUPON_PRICES['in_campus_monthly'], 0); ?>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="out_campus_monthly" id="outCampusMonthly">
                                        <label class="form-check-label" for="outCampusMonthly">
                                            Monthly (Out Campus) - ₹<?php echo number_format($COUPON_PRICES['out_campus_monthly'], 0); ?>
                                        </label>
                                    </div>

                                    <hr>
                                    <h6>Single Meal Options:</h6>
                                    
                                    <!-- Breakfast -->
                                    <?php if (isTimeSlotAvailable('breakfast')): ?>
                                    <?php $breakfast_price = getEffectivePrice('breakfast', $current_date); ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="breakfast" id="breakfast">
                                        <label class="form-check-label" for="breakfast">
                                            Breakfast - ₹<?php echo number_format($breakfast_price, 0); ?>
                                            <?php if ($breakfast_price != $COUPON_PRICES['breakfast']): ?>
                                                <span class="badge bg-warning text-dark ms-1">Special Price</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Lunch -->
                                    <?php if (isTimeSlotAvailable('lunch_weekday') || isTimeSlotAvailable('lunch_sunday')): ?>
                                    <?php 
                                    $lunch_meal_type = isSunday() ? 'lunch_sunday' : 'lunch_weekday';
                                    $lunch_price = getEffectivePrice($lunch_meal_type, $current_date);
                                    ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="lunch" id="lunch">
                                        <label class="form-check-label" for="lunch">
                                            Lunch - ₹<?php echo number_format($lunch_price, 0); ?>
                                            <?php if ($lunch_price != $COUPON_PRICES[$lunch_meal_type]): ?>
                                                <span class="badge bg-warning text-dark ms-1">Special Price</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Dinner -->
                                    <?php if (isTimeSlotAvailable('dinner_weekday') || isTimeSlotAvailable('dinner_sunday')): ?>
                                    <?php 
                                    $dinner_meal_type = isSunday() ? 'dinner_sunday' : 'dinner_weekday';
                                    $dinner_price = getEffectivePrice($dinner_meal_type, $current_date);
                                    ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="dinner" id="dinner">
                                        <label class="form-check-label" for="dinner">
                                            Dinner - ₹<?php echo number_format($dinner_price, 0); ?>
                                            <?php if ($dinner_price != $COUPON_PRICES[$dinner_meal_type]): ?>
                                                <span class="badge bg-warning text-dark ms-1">Special Price</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Number of Persons -->
                                <div class="mb-3">
                                    <label for="persons" class="form-label"><strong>Number of Persons:</strong></label>
                                    <select name="persons" id="persons" class="form-select" required>
                                        <?php for($i = 1; $i <= $capacity_query; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text" id="capacityWarning" style="display: none;">
                                        <i class="fas fa-exclamation-triangle text-warning"></i>
                                        <span id="capacityMessage"></span>
                                    </div>
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
                                <h3 class="mb-3">Personal Details</h3>
                                
                                <div class="mb-3">
                                    <label for="user_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="user_name" name="user_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="user_email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="user_email" name="user_email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="user_phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="user_phone" name="user_phone" pattern="[0-9]{10}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Confirmation -->
                    <div class="step" id="step4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-3">Booking Summary</h3>
                                <div id="bookingSummary"></div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                                    <label class="form-check-label" for="terms_accepted">
                                        I agree to the terms and conditions
                                    </label>
                                </div>

                                <input type="hidden" name="total_amount" id="totalAmountInput" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display: none;">Previous</button>
                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">Next</button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">Confirm Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentStep = 1;
const totalSteps = 4;
let selectedMessCapacity = 0;

// Dynamic pricing data from PHP
const pricingData = {
    in_campus_monthly: <?php echo $COUPON_PRICES['in_campus_monthly']; ?>,
    out_campus_monthly: <?php echo $COUPON_PRICES['out_campus_monthly']; ?>,
    breakfast: <?php echo getEffectivePrice('breakfast', $current_date); ?>,
    lunch: <?php echo getEffectivePrice(isSunday() ? 'lunch_sunday' : 'lunch_weekday', $current_date); ?>,
    dinner: <?php echo getEffectivePrice(isSunday() ? 'dinner_sunday' : 'dinner_weekday', $current_date); ?>
};

const isSunday = <?php echo isSunday() ? 'true' : 'false'; ?>;

// Track selected mess capacity
document.addEventListener('DOMContentLoaded', function() {
    const messRadios = document.querySelectorAll('input[name="mess_id"]');
    messRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            selectedMessCapacity = parseInt(this.dataset.capacity);
            updatePersonsDropdown();
        });
    });
    
    // Update capacity warning when persons selection changes
    document.getElementById('persons').addEventListener('change', updateCapacityWarning);
});

function updatePersonsDropdown() {
    const personsSelect = document.getElementById('persons');
    const currentValue = personsSelect.value;
    
    // Clear existing options
    personsSelect.innerHTML = '';
    
    // Add options based on available capacity
    const maxPersons = Math.min(10, selectedMessCapacity);
    
    for (let i = 1; i <= maxPersons; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + ' Person' + (i > 1 ? 's' : '');
        personsSelect.appendChild(option);
    }
    
    // Restore previous value if still valid
    if (currentValue && currentValue <= maxPersons) {
        personsSelect.value = currentValue;
    }
    
    updateCapacityWarning();
}

function updateCapacityWarning() {
    const personsSelect = document.getElementById('persons');
    const capacityWarning = document.getElementById('capacityWarning');
    const capacityMessage = document.getElementById('capacityMessage');
    
    const selectedPersons = parseInt(personsSelect.value);
    
    if (selectedMessCapacity < 999) { // Only show warning if capacity is limited
        if (selectedPersons > selectedMessCapacity) {
            capacityWarning.style.display = 'block';
            capacityMessage.textContent = `Only ${selectedMessCapacity} spots available. Please select a different mess or reduce number of persons.`;
            capacityMessage.className = 'text-danger';
        } else if (selectedPersons > selectedMessCapacity * 0.8) {
            capacityWarning.style.display = 'block';
            capacityMessage.textContent = `Limited availability! Only ${selectedMessCapacity} spots remaining.`;
            capacityMessage.className = 'text-warning';
        } else {
            capacityWarning.style.display = 'none';
        }
    } else {
        capacityWarning.style.display = 'none';
    }
}

function showStep(step) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.step-indicator div').forEach(s => s.classList.remove('active'));
    
    document.getElementById('step' + step).classList.add('active');
    document.getElementById('step' + step + '-indicator').classList.add('active');
    
    document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
    document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'inline-block';
    document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';
}

function changeStep(direction) {
    if (direction === 1 && !validateCurrentStep()) {
        return;
    }
    
    currentStep += direction;
    if (currentStep < 1) currentStep = 1;
    if (currentStep > totalSteps) currentStep = totalSteps;
    
    if (currentStep === 4) {
        updateBookingSummary();
    }
    
    showStep(currentStep);
}

function validateCurrentStep() {
    const step = document.getElementById('step' + currentStep);
    const requiredFields = step.querySelectorAll('[required]');
    
    for (let field of requiredFields) {
        if (field.type === 'radio') {
            const radioGroup = step.querySelectorAll('input[name="' + field.name + '"]');
            const isChecked = Array.from(radioGroup).some(radio => radio.checked);
            if (!isChecked) {
                alert('Please select ' + field.name.replace('_', ' '));
                return false;
            }
        } else if (field.type === 'checkbox') {
            if (!field.checked) {
                alert('Please accept the terms and conditions');
                return false;
            }
        } else if (!field.value.trim()) {
            field.focus();
            alert('Please fill in all required fields');
            return false;
        }
    }
    
    // Additional validation for capacity
    if (currentStep === 2) {
        const selectedPersons = parseInt(document.getElementById('persons').value);
        if (selectedPersons > selectedMessCapacity && selectedMessCapacity < 999) {
            alert(`Sorry, only ${selectedMessCapacity} spots are available. Please select a different mess or reduce the number of persons.`);
            return false;
        }
    }
    
    return true;
}

function updateBookingSummary() {
    const formData = new FormData(document.getElementById('bookingForm'));
    const couponType = formData.get('coupon_type');
    const persons = formData.get('persons');
    const userType = formData.get('user_type');
    
    let price = 0;
    let description = '';
    
    if (couponType === 'in_campus_monthly') {
        price = pricingData.in_campus_monthly;
        description = 'Monthly (In Campus)';
    } else if (couponType === 'out_campus_monthly') {
        price = pricingData.out_campus_monthly;
        description = 'Monthly (Out Campus)';
    } else if (couponType === 'single_meal') {
        const mealType = document.querySelector('input[name="coupon_type"]:checked').dataset.meal;
        document.getElementById('mealType').value = mealType;
        
        if (mealType === 'breakfast') {
            price = pricingData.breakfast;
            description = 'Breakfast';
        } else if (mealType === 'lunch') {
            price = pricingData.lunch;
            description = 'Lunch';
        } else if (mealType === 'dinner') {
            price = pricingData.dinner;
            description = 'Dinner';
        }
    }
    
    const totalAmount = price * persons;
    document.getElementById('totalAmountInput').value = totalAmount;
    
    // Get selected mess name
    const selectedMess = document.querySelector('input[name="mess_id"]:checked');
    const messName = selectedMess ? selectedMess.nextElementSibling.querySelector('strong').textContent : '';
    
    document.getElementById('bookingSummary').innerHTML = `
        <table class="table">
            <tr><td><strong>Mess:</strong></td><td>${messName}</td></tr>
            <tr><td><strong>User Type:</strong></td><td>${userType}</td></tr>
            <tr><td><strong>Coupon Type:</strong></td><td>${description}</td></tr>
            <tr><td><strong>Persons:</strong></td><td>${persons}</td></tr>
            <tr><td><strong>Price per Person:</strong></td><td>₹${price.toFixed(0)}</td></tr>
            <tr><td><strong>Total Amount:</strong></td><td><strong>₹${totalAmount.toFixed(0)}</strong></td></tr>
        </table>
        ${selectedMessCapacity < 999 ? `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Capacity Info:</strong> ${selectedMessCapacity - parseInt(persons)} spots will remain after your booking.
        </div>
        ` : ''}
    `;
}

showStep(1);

// Auto-refresh capacity every 30 seconds
setInterval(function() {
    if (currentStep === 1) {
        location.reload();
    }
}, 30000);
</script>

</body>
</html>

<?php 
if (isset($conn)) {
    $conn->close();
}
?>
