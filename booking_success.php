<?php
require_once 'config.php';
include('header/header.php');

if (!isset($_GET['booking_id'])) {
    header('Location: index.php');
    exit;
}

$booking_id = $_GET['booking_id'];
$conn = getDBConnection();

// Get booking details
$booking_query = $conn->prepare("
    SELECT b.*, m.name as mess_name, m.location as mess_location, m.contact as mess_contact
    FROM bookings b 
    JOIN mess m ON b.mess_id = m.id 
    WHERE b.booking_id = ?
");
$booking_query->bind_param("s", $booking_id);
$booking_query->execute();
$booking = $booking_query->get_result()->fetch_assoc();

if (!$booking) {
    die('Booking not found');
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white text-center">
                    <h3><i class="fas fa-check-circle"></i> Booking Successful!</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <strong>Congratulations!</strong> Your mess coupon has been booked successfully.
                        Please download your ticket and show it at the mess.
                    </div>

                    <div class="booking-details text-center ">
                          <h5>Your QR Code Ticket:</h5>
                    <img src="generate_qr.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>"
                        alt="QR Code" />

                    <p class="text-muted">Please show this QR code at the mess counter</p>
                    </div>

                    <div class="alert alert-warning">
                        <h6>Important Reminders:</h6>
                        <ul class="mb-0">
                            <li>This coupon is non-refundable and non-transferable</li>
                            <li>Please show this ticket at the mess counter</li>
                            <li>Valid only for the booked date and meal time</li>
                            <li>Keep this ticket safe until you use it</li>
                        </ul>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="download_ticket.php?booking_id=<?php echo $booking['booking_id']; ?>"
                            class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-download"></i> Download Ticket
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Book Another Coupon
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); ?>
