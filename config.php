<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'new-code');


// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Helper function to get setting value from database
function getSettingValue($key, $default = '') {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $conn->close(); // Close connection after use
        return $row['setting_value'];
    }
    $conn->close(); // Close connection even if not found
    return $default;
}

// Dynamic coupon pricing configuration - now loaded from database
// These are global defaults, can be overridden by special_pricing
$COUPON_PRICES = [
    'in_campus_monthly' => (float)getSettingValue('price_in_campus_monthly', '3900'),
    'out_campus_monthly' => (float)getSettingValue('price_out_campus_monthly', '1690'),
    'breakfast' => (float)getSettingValue('price_breakfast', '30'),
    'lunch_weekday' => (float)getSettingValue('price_lunch_weekday', '65'),
    'dinner_weekday' => (float)getSettingValue('price_dinner_weekday', '65'),
    'lunch_sunday' => (float)getSettingValue('price_lunch_sunday', '80'),
    'dinner_sunday' => (float)getSettingValue('price_dinner_sunday', '50')
];

// Dynamic time slots configuration - now loaded from database
$TIME_SLOTS = [
    'breakfast' => [
        'start' => getSettingValue('time_breakfast_start', '06:00'), 
        'end' => getSettingValue('time_breakfast_end', '07:30')
    ],
    'lunch_weekday' => [
        'start' => getSettingValue('time_lunch_weekday_start', '08:00'), 
        'end' => getSettingValue('time_lunch_weekday_end', '13:30')
    ],
    'lunch_sunday' => [
        'start' => getSettingValue('time_lunch_sunday_start', '08:00'), 
        'end' => getSettingValue('time_lunch_sunday_end', '12:30')
    ],
    'dinner_weekday' => [
        'start' => getSettingValue('time_dinner_weekday_start', '14:00'), 
        'end' => getSettingValue('time_dinner_weekday_end', '20:00')
    ],
    'dinner_sunday' => [
        'start' => getSettingValue('time_dinner_sunday_start', '14:00'), 
        'end' => getSettingValue('time_dinner_sunday_end', '20:00')
    ]
];

// User types
$USER_TYPES = ['staff', 'student', 'visitor'];

// Helper function to check if a meal type is available for a specific mess on a given date
function isMealTypeAvailableForMess($mess_id, $date, $meal_type_generic) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT is_available FROM mess_meal_availability WHERE mess_id = ? AND date = ? AND meal_type = ?");
    $stmt->bind_param("iss", $mess_id, $date, $meal_type_generic);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_available = true; // Default to available if no override exists
    if ($row = $result->fetch_assoc()) {
        $is_available = (bool)$row['is_available'];
    }
    $conn->close();
    return $is_available;
}


// Helper functions
if (!function_exists('isTimeSlotAvailable')) {
    function isTimeSlotAvailable($slot_type) {
        global $TIME_SLOTS;
        
        $current_time = date('H:i');
        $current_day = date('w'); // 0 = Sunday, 1 = Monday, etc.
        
        // Check if it's Sunday and adjust slot type
        if ($current_day == 0) {
            if ($slot_type == 'lunch_weekday') $slot_type = 'lunch_sunday';
            if ($slot_type == 'dinner_weekday') $slot_type = 'dinner_sunday';
        }
        
        if (!isset($TIME_SLOTS[$slot_type])) return false;
        
        $start_time = $TIME_SLOTS[$slot_type]['start'];
        $end_time = $TIME_SLOTS[$slot_type]['end'];
        
        return ($current_time >= $start_time && $current_time <= $end_time);
    }
}

// Function to get special pricing for a specific date and meal type
function getSpecialPrice($date, $meal_type) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT price FROM special_pricing WHERE date = ? AND meal_type = ?");
    $stmt->bind_param("ss", $date, $meal_type);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $conn->close();
        return (float)$row['price'];
    }
    $conn->close();
    return null;
}

// Function to get effective price (special price if available, otherwise regular price)
function getEffectivePrice($meal_type, $date = null) {
    global $COUPON_PRICES;
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Check for special pricing first
    $special_price = getSpecialPrice($date, $meal_type);
    if ($special_price !== null) {
        return $special_price;
    }
    
    // Return regular price
    return isset($COUPON_PRICES[$meal_type]) ? $COUPON_PRICES[$meal_type] : 0;
}

function isTodayOnly() {
    return true; // Coupons can only be booked for present day
}

function isWeekday() {
    $day = date('w');
    return ($day >= 1 && $day <= 6); // Monday to Saturday
}

function isPublicHoliday() {
    // Add your public holiday logic here
    $holidays = [
        '2025-01-26', // Republic Day
        '2025-08-15', // Independence Day
        '2025-10-02', // Gandhi Jayanti
        // Add more holidays as needed
    ];
    
    return in_array(date('Y-m-d'), $holidays);
}
?>
