<?php
/**
 * Daily reminder script - Run this via cron job
 * Cron job example: 0 7 * * * /usr/bin/php /path/to/send_daily_reminders.php
 * This will run daily at 7:00 AM
 */

require_once 'config.php';
require_once 'email_config.php';

$conn = getDBConnection();

// Get today's bookings that need reminders
$today = date('Y-m-d');
$reminder_query = $conn->prepare("
    SELECT b.*, m.name as mess_name, m.location as mess_location, m.contact as mess_contact
    FROM bookings b 
    JOIN mess m ON b.mess_id = m.id 
    LEFT JOIN email_logs el ON b.booking_id = el.booking_id AND el.email_type = 'reminder'
    WHERE b.booking_date = ? 
    AND b.booking_status = 'active' 
    AND b.coupon_type = 'single_meal'
    AND el.id IS NULL
    ORDER BY b.meal_type, b.created_at
");

$reminder_query->bind_param("s", $today);
$reminder_query->execute();
$bookings = $reminder_query->get_result();

$sent_count = 0;
$failed_count = 0;

echo "Starting daily reminder process for " . date('Y-m-d H:i:s') . "\n";
echo "Found " . $bookings->num_rows . " bookings to send reminders for.\n\n";

while ($booking = $bookings->fetch_assoc()) {
    echo "Processing booking: " . $booking['booking_id'] . " for " . $booking['user_name'] . "\n";
    
    // Send reminder email
    $email_sent = sendBookingReminderEmail($booking);
    
    // Log email status
    $email_status = $email_sent ? 'sent' : 'failed';
    $log_email = $conn->prepare("
        INSERT INTO email_logs (booking_id, email_type, recipient_email, status, sent_at) 
        VALUES (?, 'reminder', ?, ?, NOW())
    ");
    $log_email->bind_param("sss", $booking['booking_id'], $booking['user_email'], $email_status);
    $log_email->execute();
    
    if ($email_sent) {
        $sent_count++;
        echo "✓ Reminder sent successfully to " . $booking['user_email'] . "\n";
    } else {
        $failed_count++;
        echo "✗ Failed to send reminder to " . $booking['user_email'] . "\n";
    }
    
    // Small delay to avoid overwhelming the email server
    usleep(500000); // 0.5 second delay
}

echo "\n=== Daily Reminder Summary ===\n";
echo "Total reminders sent: $sent_count\n";
echo "Total failures: $failed_count\n";
echo "Process completed at: " . date('Y-m-d H:i:s') . "\n";

$conn->close();
?>
