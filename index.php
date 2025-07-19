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

// Try to get mess data
try {
    $mess_list = $conn->query("SELECT * FROM mess WHERE status = 'active'");
    if (!$mess_list) {
        die("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    die("Mess query error: " . $e->getMessage());
}

// Helper functions
function isTimeSlotAvailable($slot) {
    $currentHour = (int) date('H');
    $currentMinute = (int) date('i');
    $currentMinutes = $currentHour * 60 + $currentMinute;
    
    switch($slot) {
        case 'breakfast':
            return $currentMinutes >= 360 && $currentMinutes <= 450; // 6:00 AM to 7:30 AM
        case 'lunch_weekday':
            return !isSunday() && $currentMinutes >= 480 && $currentMinutes <= 810; // 8:00 AM to 1:30 PM
        case 'lunch_sunday':
            return isSunday() && $currentMinutes >= 480 && $currentMinutes <= 750; // 8:00 AM to 12:30 PM
        case 'dinner_weekday':
            return !isSunday() && $currentMinutes >= 840 && $currentMinutes <= 1200; // 2:00 PM to 8:00 PM
        case 'dinner_sunday':
            return isSunday() && $currentMinutes >= 840 && $currentMinutes <= 1200; // 2:00 PM to 8:00 PM
        default:
            return false;
    }
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
                    </ul>
                </div>
                
                <div class="pricing-info mt-4 text-white">
                    <h5>Pricing Information:</h5>
                    <ul>
                        <li><strong>Breakfast:</strong> ₹30/meal</li>
                        <li><strong>Lunch:</strong> ₹65/meal (₹80 on Sunday)</li>
                        <li><strong>Dinner:</strong> ₹65/meal (₹50 on Sunday)</li>
                        <li><strong>Monthly (In Campus):</strong> ₹3900</li>
                        <li><strong>Monthly (Out Campus):</strong> ₹1690</li>
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
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="mess_id" value="<?php echo $mess['id']; ?>" id="mess<?php echo $mess['id']; ?>" required>
                                        <label class="form-check-label" for="mess<?php echo $mess['id']; ?>">
                                            <strong><?php echo htmlspecialchars($mess['name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($mess['location']); ?> | 
                                                <?php echo htmlspecialchars($mess['contact']); ?>
                                            </small>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
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
                                            Monthly (In Campus) - ₹3900
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="out_campus_monthly" id="outCampusMonthly">
                                        <label class="form-check-label" for="outCampusMonthly">
                                            Monthly (Out Campus) - ₹1690
                                        </label>
                                    </div>

                                    <hr>
                                    <h6>Single Meal Options:</h6>
                                    
                                    <!-- Breakfast -->
                                    <?php if (isTimeSlotAvailable('breakfast')): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="breakfast" id="breakfast">
                                        <label class="form-check-label" for="breakfast">Breakfast - ₹30</label>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Lunch -->
                                    <?php if (isTimeSlotAvailable('lunch_weekday') || isTimeSlotAvailable('lunch_sunday')): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="lunch" id="lunch">
                                        <label class="form-check-label" for="lunch">
                                            Lunch - ₹<?php echo isSunday() ? 80 : 65; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Dinner -->
                                    <?php if (isTimeSlotAvailable('dinner_weekday') || isTimeSlotAvailable('dinner_sunday')): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="coupon_type" value="single_meal" data-meal="dinner" id="dinner">
                                        <label class="form-check-label" for="dinner">
                                            Dinner - ₹<?php echo isSunday() ? 50 : 65; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Number of Persons -->
                                <div class="mb-3">
                                    <label for="persons" class="form-label"><strong>Number of Persons:</strong></label>
                                    <select name="persons" id="persons" class="form-select" required>
                                        <?php for($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?></option>
                                        <?php endfor; ?>
                                    </select>
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
        price = 3900;
        description = 'Monthly (In Campus)';
    } else if (couponType === 'out_campus_monthly') {
        price = 1690;
        description = 'Monthly (Out Campus)';
    } else if (couponType === 'single_meal') {
        const mealType = document.querySelector('input[name="coupon_type"]:checked').dataset.meal;
        document.getElementById('mealType').value = mealType;
        
        if (mealType === 'breakfast') {
            price = 30;
            description = 'Breakfast';
        } else if (mealType === 'lunch') {
            price = <?php echo isSunday() ? 80 : 65; ?>;
            description = 'Lunch';
        } else if (mealType === 'dinner') {
            price = <?php echo isSunday() ? 50 : 65; ?>;
            description = 'Dinner';
        }
    }
    
    const totalAmount = price * persons;
    document.getElementById('totalAmountInput').value = totalAmount;
    
    document.getElementById('bookingSummary').innerHTML = `
        <table class="table">
            <tr><td><strong>User Type:</strong></td><td>${userType}</td></tr>
            <tr><td><strong>Coupon Type:</strong></td><td>${description}</td></tr>
            <tr><td><strong>Persons:</strong></td><td>${persons}</td></tr>
            <tr><td><strong>Price per Person:</strong></td><td>₹${price}</td></tr>
            <tr><td><strong>Total Amount:</strong></td><td><strong>₹${totalAmount}</strong></td></tr>
        </table>
    `;
}

showStep(1);
</script>

</body>
</html>

<?php 
if (isset($conn)) {
    $conn->close();
}
?>
