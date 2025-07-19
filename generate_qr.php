<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if Composer autoload exists
if (!file_exists('vendor/autoload.php')) {
    // Fallback to simple QR code generation without external library
    generateSimpleQR();
    exit;
}

require_once 'vendor/autoload.php';
require_once 'config.php';

// Import QR Code classes
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

// Check for booking ID
if (!isset($_GET['booking_id'])) {
    http_response_code(400);
    echo "Booking ID missing";
    exit;
}

$booking_id = trim($_GET['booking_id']);
error_log("Generating QR for booking_id: " . $booking_id);

try {
    // Database connection
    $conn = getDBConnection();
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Query booking details
    $stmt = $conn->prepare("
        SELECT b.*, m.name as mess_name, m.location as mess_location, m.contact as mess_contact
        FROM bookings b
        JOIN mess m ON b.mess_id = m.id
        WHERE b.booking_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
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

    // Format coupon type for display
    function formatCouponType($coupon_type, $meal_type = '') {
        switch($coupon_type) {
            case 'in_campus_monthly':
                return 'Monthly (In Campus)';
            case 'out_campus_monthly':
                return 'Monthly (Out Campus)';
            case 'breakfast':
            case 'lunch':
            case 'dinner':
                return ucfirst($coupon_type);
            case 'single_meal':
                return ucfirst($meal_type);
            case 'full_day':
                return 'Full Day';
            default:
                return ucfirst(str_replace('_', ' ', $coupon_type));
        }
    }

    // Create comprehensive QR data
    $qrData = "=== PU MESS BOOKING TICKET ===\n";
    $qrData .= "Booking ID: " . $booking['booking_id'] . "\n";
    $qrData .= "Name: " . $booking['user_name'] . "\n";
    $qrData .= "Email: " . $booking['user_email'] . "\n";
    $qrData .= "Phone: " . $booking['user_phone'] . "\n";
    $qrData .= "User Type: " . ucfirst($booking['user_type']) . "\n";
    $qrData .= "Mess: " . $booking['mess_name'] . "\n";
    $qrData .= "Location: " . $booking['mess_location'] . "\n";
    $qrData .= "Coupon: " . formatCouponType($booking['coupon_type'], $booking['meal_type']) . "\n";
    $qrData .= "Date: " . date('d M Y', strtotime($booking['booking_date'])) . "\n";
    $qrData .= "Persons: " . $booking['persons'] . "\n";
    $qrData .= "Amount: ₹" . number_format($booking['total_amount'], 2) . "\n";
    $qrData .= "Status: " . ucfirst($booking['booking_status']) . "\n";
    $qrData .= "Booked: " . date('d M Y H:i', strtotime($booking['created_at'])) . "\n";
    $qrData .= "=== SHOW THIS AT MESS COUNTER ===";

    // Generate QR Code
    $writer = new PngWriter();
    $qrCode = QrCode::create($qrData)
        ->setEncoding(new Encoding('UTF-8'))
        ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
        ->setSize(300)
        ->setMargin(10)
        ->setForegroundColor(new Color(0, 0, 0))
        ->setBackgroundColor(new Color(255, 255, 255));

    $result = $writer->write($qrCode);

    // Set headers and output
    header('Content-Type: ' . $result->getMimeType());
    header('Content-Disposition: inline; filename="qr_' . $booking_id . '.png"');
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    
    echo $result->getString();

} catch (Exception $e) {
    error_log("QR Generation Error: " . $e->getMessage());
    
    // Fallback to simple QR generation
    generateSimpleQR($booking_id ?? 'ERROR');
}

function generateSimpleQR($booking_id = 'NO_ID') {
    // Simple fallback QR code generation using Google Charts API
    $qrData = urlencode("PU Mess Booking - ID: " . $booking_id . " - Show at counter");
    $qrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $qrData . "&choe=UTF-8";
    
    // Fetch the QR code image
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'PU Mess Booking System'
        ]
    ]);
    
    $qrImage = @file_get_contents($qrUrl, false, $context);
    
    if ($qrImage !== false) {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="qr_' . $booking_id . '.png"');
        echo $qrImage;
    } else {
        // Ultimate fallback - generate a simple text-based response
        header('Content-Type: text/plain');
        echo "QR Code generation failed. Booking ID: " . $booking_id;
    }
}

// Close database connection if it exists
if (isset($conn)) {
    $conn->close();
}
?>
