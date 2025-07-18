<?php
require_once 'vendor/autoload.php';
require_once 'config.php'; // Assuming config.php contains getDBConnection()

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel; // Corrected import for v4+
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Writer\Writer;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
// use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

// Check for booking ID
if (!isset($_GET['booking_id'])) {
    http_response_code(400);
    echo "Booking ID missing";
    exit;
}
$booking_id = trim($_GET['booking_id']);
error_log("Received booking_id: " . $booking_id);

// DB connection
// Ensure getDBConnection() function is defined in config.php and returns a mysqli connection
$conn = getDBConnection();
if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo "Database connection failed";
    exit;
}

// Query booking
$stmt = $conn->prepare("
    SELECT b.*, m.name as mess_name
    FROM bookings b
    JOIN mess m ON b.mess_id = m.id
    WHERE b.booking_id = ?
");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo "Failed to prepare query";
    exit;
}
$stmt->bind_param("s", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    http_response_code(404);
    echo "Booking not found";
    exit;
}

// QR data
// It's good practice to ensure all values are strings before json_encode
$qrData = json_encode([
    "Booking ID" => (string)$booking['booking_id'],
    "Name" => (string)$booking['user_name'],
    "User Type" => (string)$booking['user_type'],
    "Mess" => (string)$booking['mess_name'],
    "Meal" => (string)$booking['meal_type'],
    "Date" => (string)$booking['booking_date'],
    "Persons" => (int)$booking['persons'], // Cast to int if it's an integer value
    "Amount" => (float)$booking['total_amount'] // Cast to float if it's a decimal value
]);
$qrData = "Booking ID: " . $booking['booking_id'] . "\n"
        . "Name: " . $booking['user_name'] . "\n"
        . "User Type: " . $booking['user_type'] . "\n"
        . "Mess: " . $booking['mess_name'] . "\n"
        . "Meal: " . $booking['meal_type'] . "\n"
        . "Date: " . $booking['booking_date'] . "\n"
        . "Persons: " . $booking['persons'] . "\n"
        . "Amount: ₹" . number_format($booking['total_amount'], 2);


// Build QR Code using syntax compatible with Endroid QR Code library v4.x/v5.x/v6.x
$writer = new PngWriter();

$qrCode = QrCode::create($qrData)
    ->setEncoding(new Encoding('UTF-8'))
    ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
    ->setSize(300)
    ->setMargin(10);


$result = $writer->write($qrCode);

// Output image
header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
exit;
