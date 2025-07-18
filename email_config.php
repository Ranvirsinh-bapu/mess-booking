<?php
// Load PHPMailer classes manually (adjust the path if needed)
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once __DIR__ . '/vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\QrCode;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email config constants
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'ranvirsinhbapu0@gmail.com');  // Your SMTP email
define('SMTP_PASSWORD', 'sckoimlsiiijqpgy');          // Your SMTP app password
define('SMTP_ENCRYPTION', 'tls');

define('FROM_EMAIL', 'noreply@pu.edu');
define('FROM_NAME', 'PU Mess Booking System');
define('REPLY_TO_EMAIL', 'support@pu.edu');

/**
 * Send email using PHPMailer
 */
function sendEmail($to_email, $to_name, $subject, $html_body, $text_body = '') {
    $mail = new PHPMailer(true);

    try {
        // SMTP server config
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        // Email addresses
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body ?: strip_tags($html_body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Format coupon type for display
 */
function formatCouponType($coupon_type, $meal_type = null) {
    switch ($coupon_type) {
        case 'in_campus_monthly':
            return 'In Campus Monthly (All meals included)';
        case 'out_campus_monthly':
            return 'Out Campus Monthly (Lunch only)';
        case 'single_meal':
            return ucfirst($meal_type) . ' - Single Meal';
        default:
            return 'Unknown';
    }
}

/**
 * Get meal time slots (not used here but you can extend)
 */
function getMealTime($meal_type) {
    $times = [
        'breakfast' => '6:00 AM - 7:30 AM',
        'lunch' => date('w') == 0 ? '8:00 AM - 12:30 PM' : '8:00 AM - 1:30 PM',
        'dinner' => '2:00 PM - 8:00 PM'
    ];

    return $times[$meal_type] ?? 'Not specified';
}

/**
 * Get booking confirmation email HTML body (inline template)
 */
function getBookingConfirmationTemplate($data) {
    return "
    <h2>Booking Confirmation</h2>
    <p><strong>Booking ID:</strong> {$data['booking_id']}</p>
    <p><strong>Customer Name:</strong> {$data['customer_name']}</p>
    <p><strong>Mess Name:</strong> {$data['mess_name']}</p>
    <p><strong>Location:</strong> {$data['mess_location']}</p>
    <p><strong>Contact:</strong> {$data['mess_contact']}</p>
    <p><strong>User Type:</strong> {$data['user_type']}</p>
    <p><strong>Coupon Type:</strong> {$data['coupon_type']}</p>
    <p><strong>Persons:</strong> {$data['persons']}</p>
    <p><strong>Booking Date:</strong> {$data['booking_date']}</p>
    <p><strong>Total Amount:</strong> {$data['total_amount']}</p>
    <p><strong>Booking Time:</strong> {$data['booking_time']}</p>
    <p><a href='{$data['download_link']}'>Download your ticket</a></p>
    ";
}

/**
 * Send booking confirmation email
 */
// function sendBookingConfirmationEmail($booking_data) {
//     $template_data = [
//         'booking_id' => $booking_data['booking_id'],
//         'customer_name' => $booking_data['user_name'],
//         'mess_name' => $booking_data['mess_name'],
//         'mess_location' => $booking_data['mess_location'],
//         'mess_contact' => $booking_data['mess_contact'],
//         'user_type' => ucfirst($booking_data['user_type']),
//         'coupon_type' => formatCouponType($booking_data['coupon_type'], $booking_data['meal_type']),
//         'persons' => $booking_data['persons'],
//         'booking_date' => date('d-m-Y', strtotime($booking_data['booking_date'])),
//         'total_amount' => number_format($booking_data['total_amount'], 2),
//         'booking_time' => date('d-m-Y H:i:s', strtotime($booking_data['created_at'])),
//         'download_link' => 'http://localhost/mess-booking/download_ticket.php?booking_id=' . $booking_data['booking_id']
//     ];

//     $html_body = getBookingConfirmationTemplate($template_data);
//     $subject = "Booking Confirmation - " . $booking_data['booking_id'];

//     return sendEmail(
//         $booking_data['user_email'],
//         $booking_data['user_name'],
//         $subject,
//         $html_body
//     );
// // }
// function sendBookingConfirmationEmailWithQRCode($booking_data) {
//     // Generate QR data (formatted)
//     $qrData = "Booking ID: {$booking_data['booking_id']}\n"
//         . "Name: {$booking_data['user_name']}\n"
//         . "User Type: " . ucfirst($booking_data['user_type']) . "\n"
//         . "Mess: {$booking_data['mess_name']}\n"
//         . "Meal: {$booking_data['meal_type']}\n"
//         . "Date: {$booking_data['booking_date']}\n"
//         . "Persons: {$booking_data['persons']}\n"
//         . "Amount: ₹" . number_format($booking_data['total_amount'], 2);

//     // Generate QR code using Endroid library
    
//     $writer = new \Endroid\QrCode\Writer\PngWriter();
//     $qrCode = \Endroid\QrCode\QrCode::create($qrData)
//         ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
//         ->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
//         ->setSize(300)
//         ->setMargin(10);

//     $qrResult = $writer->write($qrCode);
//     $qrImageString = $qrResult->getString(); // PNG binary

//     // Send email with QR code attached
//     $mail = new PHPMailer(true);
//     try {
//         $mail->isSMTP();
//         $mail->Host = SMTP_HOST;
//         $mail->SMTPAuth = true;
//         $mail->Username = SMTP_USERNAME;
//         $mail->Password = SMTP_PASSWORD;
//         $mail->SMTPSecure = SMTP_ENCRYPTION;
//         $mail->Port = SMTP_PORT;

//         $mail->setFrom(FROM_EMAIL, FROM_NAME);
//         $mail->addAddress($booking_data['user_email'], $booking_data['user_name']);
//         $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

//         $mail->isHTML(true);
//         $mail->Subject = "Your PU Mess QR Ticket - {$booking_data['booking_id']}";
//         $mail->Body = "<p>Please find your mess booking QR code attached.</p>";
//         $mail->AltBody = "Please find your mess booking QR code attached.";

//         // Attach QR code image from memory
//         $mail->addStringAttachment($qrImageString, 'pu-mess-ticket.png', 'base64', 'image/png');

//         $mail->send();
//         return true;
//     } catch (Exception $e) {
//         error_log("QR Email sending failed: {$mail->ErrorInfo}");
//         return false;
//     }
// }
function sendBookingConfirmationEmailWithQRCode($booking_data) {
    // Generate QR data
    $qrData = "Booking ID: {$booking_data['booking_id']}\n"
        . "Name: {$booking_data['user_name']}\n"
        . "User Type: " . ucfirst($booking_data['user_type']) . "\n"
        . "Mess: {$booking_data['mess_name']}\n"
        . "Meal: {$booking_data['meal_type']}\n"
        . "Date: {$booking_data['booking_date']}\n"
        . "Persons: {$booking_data['persons']}\n"
        . "Amount: ₹" . number_format($booking_data['total_amount'], 2);

    // Generate QR code using Endroid library
    $writer = new \Endroid\QrCode\Writer\PngWriter();
    $qrCode = \Endroid\QrCode\QrCode::create($qrData)
        ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
        ->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
        ->setSize(300)
        ->setMargin(10);

    $qrResult = $writer->write($qrCode);
    $qrImageString = $qrResult->getString(); // This is raw PNG data

    // (Optional) Save to check QR works
    // file_put_contents('test-qr.png', $qrImageString);

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($booking_data['user_email'], $booking_data['user_name']);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = "Your PU Mess QR Ticket - {$booking_data['booking_id']}";
        $mail->Body = "<p>Please scan your QR code below:</p><img src='cid:qrimg' />";
        $mail->AltBody = "Please scan your QR code attached.";

        // Embed QR in body and also attach (optional)
        $mail->addStringEmbeddedImage($qrImageString, 'qrimg', 'qr.png', 'base64', 'image/png');
        $mail->addStringAttachment($qrImageString, 'pu-mess-ticket.png', 'base64', 'image/png');

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("QR Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}
