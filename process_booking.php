<?php
require_once 'config.php';
require_once 'email_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$conn = getDBConnection();

try {
    // Get and validate input data
    $user_name = trim($_POST['user_name']);
    $user_email = trim($_POST['user_email']);
    $user_phone = trim($_POST['user_phone']);
    $user_type = $_POST['user_type'];
    $mess_id = intval($_POST['mess_id']);
    $coupon_type = $_POST['coupon_type'];
    $meal_type = $_POST['meal_type'] ?? null; // This is the specific meal type (breakfast, lunch, dinner)
    $persons = intval($_POST['persons']);
    $total_amount = floatval($_POST['total_amount']);
    $booking_date = $_POST['booking_date'] ?? date('Y-m-d');

    // Basic validation
    if (
        empty($user_name) || empty($user_email) || empty($user_phone) ||
        empty($user_type) || $mess_id <= 0 || empty($coupon_type) ||
        $persons <= 0 || $total_amount <= 0
    ) {
        throw new Exception("All required fields must be filled");
    }

    // Validate email
    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }

    // Validate phone
    if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
        throw new Exception("Invalid phone number");
    }

    // Check if mess exists
    $mess_check = $conn->prepare("SELECT name, location, contact FROM mess WHERE id = ? AND status = 'active'");
    $mess_check->bind_param("i", $mess_id);
    $mess_check->execute();
    $mess_result = $mess_check->get_result();

    if ($mess_result->num_rows === 0) {
        throw new Exception("Selected mess is not available");
    }
    $mess_info = $mess_result->fetch_assoc();

    // --- NEW: Check meal availability based on staff settings ---
    if ($coupon_type === 'single_meal' && $meal_type) {
        if (!isMealTypeAvailableForMess($mess_id, $booking_date, $meal_type)) {
            throw new Exception("Sorry, " . ucfirst($meal_type) . " is currently unavailable at " . $mess_info['name'] . " for today.");
        }
    }
    // --- END NEW CHECK ---

    // Generate unique booking ID
    $booking_id = 'PU' . date('Ymd') . sprintf('%04d', rand(1000, 9999));

    // Insert booking
    $insert_query = $conn->prepare("
        INSERT INTO bookings (
            booking_id, user_name, user_email, user_phone, user_type, 
            mess_id, coupon_type, meal_type, persons, booking_date, 
            total_amount, payment_status, booking_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'active', NOW())
    ");

    $insert_query->bind_param(
        "sssssissssd",
        $booking_id,
        $user_name,
        $user_email,
        $user_phone,
        $user_type,
        $mess_id,
        $coupon_type,
        $meal_type,
        $persons,
        $booking_date,
        $total_amount
    );

    if (!$insert_query->execute()) {
        throw new Exception("Failed to create booking");
    }

    // Send confirmation email
    $email_sent = true;
    try {
        $email_sent = sendBookingConfirmationEmailWithQRCode([
            'booking_id' => $booking_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'user_phone' => $user_phone,
            'user_type' => $user_type,
            'mess_name' => $mess_info['name'],
            'mess_location' => $mess_info['location'],
            'mess_contact' => $mess_info['contact'],
            'coupon_type' => $coupon_type,
            'meal_type' => $meal_type,
            'persons' => $persons,
            'booking_date' => $booking_date,
            'total_amount' => $total_amount,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
    }


    // Redirect to success page
    $redirect_url = "booking_success.php?booking_id=" . urlencode($booking_id);
    if ($email_sent) {
        $redirect_url .= "&email_sent=1";
    }

    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    error_log("Booking error: " . $e->getMessage());
    header("Location: booking_failed.php?error=" . urlencode($e->getMessage()));
    exit;
}

$conn->close();
?>
