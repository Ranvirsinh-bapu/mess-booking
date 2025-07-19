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

// Format the coupon type for display
function formatCouponType($coupon_type) {
    switch($coupon_type) {
        case 'in_campus_monthly':
            return 'Monthly (In Campus)';
        case 'out_campus_monthly':
            return 'Monthly (Out Campus)';
        case 'breakfast':
            return 'Breakfast';
        case 'lunch':
            return 'Lunch';
        case 'dinner':
            return 'Dinner';
        case 'full_day':
            return 'Full Day';
        default:
            return ucfirst(str_replace('_', ' ', $coupon_type));
    }
}

// Format booking status
function getStatusBadge($status) {
    switch($status) {
        case 'active':
            return '<span class="badge bg-primary">Active</span>';
        case 'completed':
            return '<span class="badge bg-success">Completed</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">Cancelled</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful - PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 20px;
        }
        .success-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .success-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            overflow: hidden;
        }
        .success-header {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }
        .success-header h2 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
        }
        .success-header .check-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            animation: bounceIn 1s ease;
        }
        .booking-details-card {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .qr-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }
        .qr-code-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            display: inline-block;
            margin: 15px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .qr-code-container img {
            border-radius: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        .detail-label i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .detail-value {
            font-weight: 500;
            color: #34495e;
        }
        .total-amount {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
        }
        .action-buttons {
            text-align: center;
            padding: 30px;
        }
        .btn-custom {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-download {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-download:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a4190);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .btn-home {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
            color: white;
        }
        .btn-home:hover {
            background: linear-gradient(135deg, #4e9a2a, #96d4b5);
            transform: translateY(-2px);
            color: white;
        }
        .alert-custom {
            border: none;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        .alert-success-custom {
            background: linear-gradient(135deg, rgba(86, 171, 47, 0.1), rgba(168, 230, 207, 0.1));
            border-left: 4px solid #56ab2f;
        }
        .alert-warning-custom {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 235, 59, 0.1));
            border-left: 4px solid #ffc107;
        }
        .mess-info {
            background: rgba(23, 162, 184, 0.1);
            border-left: 4px solid #17a2b8;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        @media (max-width: 768px) {
            .success-header h2 {
                font-size: 2rem;
            }
            .success-header .check-icon {
                font-size: 3rem;
            }
            .booking-details-card {
                padding: 15px;
            }
            .qr-section {
                padding: 20px;
            }
        }
        .animate-fade-in {
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="success-container animate-fade-in">
            <div class="success-card">
                <!-- Success Header -->
                <div class="success-header">
                    <div class="check-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Booking Successful!</h2>
                    <p class="mb-0 fs-5">Your mess coupon has been booked successfully</p>
                </div>

                <div class="card-body p-4">
                    <!-- Success Message -->
                    <div class="alert alert-success-custom alert-custom">
                        <h5 class="mb-2">
                            <i class="fas fa-thumbs-up me-2"></i>Congratulations!
                        </h5>
                        <p class="mb-0">
                            Your booking has been confirmed. Please download your ticket and show it at the mess counter.
                        </p>
                    </div>

                    <!-- QR Code Section -->
                    <div class="qr-section">
                        <h4 class="mb-3">
                            <i class="fas fa-qrcode me-2"></i>Your Digital Ticket
                        </h4>
                        <div class="qr-code-container">
                            <img src="generate_qr.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>" 
                                 alt="QR Code Ticket" 
                                 style="width: 200px; height: 200px;" />
                        </div>
                        <p class="mb-0 mt-3">
                            <i class="fas fa-mobile-alt me-2"></i>
                            Show this QR code at the mess counter for quick check-in
                        </p>
                    </div>

                    <!-- Booking Details -->
                    <div class="booking-details-card">
                        <h5 class="mb-4 text-center">
                            <i class="fas fa-receipt me-2"></i>Booking Details
                        </h5>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-hashtag"></i>Booking ID
                            </div>
                            <div class="detail-value">
                                <strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-user"></i>Customer Name
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['user_name']); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-envelope"></i>Email
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['user_email']); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-phone"></i>Phone
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['user_phone']); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-user-tag"></i>User Type
                            </div>
                            <div class="detail-value">
                                <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($booking['user_type'])); ?></span>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-ticket-alt"></i>Coupon Type
                            </div>
                            <div class="detail-value">
                                <?php echo formatCouponType($booking['coupon_type']); ?>
                                <?php if (!empty($booking['meal_type'])): ?>
                                    <small class="text-muted">(<?php echo ucfirst($booking['meal_type']); ?>)</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-users"></i>Number of Persons
                            </div>
                            <div class="detail-value">
                                <strong><?php echo htmlspecialchars($booking['persons']); ?></strong>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-calendar-day"></i>Booking Date
                            </div>
                            <div class="detail-value">
                                <?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-clock"></i>Booked At
                            </div>
                            <div class="detail-value">
                                <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-info-circle"></i>Status
                            </div>
                            <div class="detail-value">
                                <?php echo getStatusBadge($booking['booking_status']); ?>
                            </div>
                        </div>

                        <!-- Total Amount -->
                        <div class="total-amount">
                            <h4 class="mb-0">
                                <i class="fas fa-rupee-sign me-2"></i>
                                Total Amount: ₹<?php echo number_format($booking['total_amount'], 2); ?>
                            </h4>
                        </div>
                    </div>

                    <!-- Mess Information -->
                    <div class="mess-info">
                        <h6 class="mb-3">
                            <i class="fas fa-utensils me-2"></i>Mess Information
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Name:</strong><br>
                                <?php echo htmlspecialchars($booking['mess_name']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Location:</strong><br>
                                <?php echo htmlspecialchars($booking['mess_location']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Contact:</strong><br>
                                <?php echo htmlspecialchars($booking['mess_contact']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Important Reminders -->
                    <div class="alert alert-warning-custom alert-custom">
                        <h6 class="mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>Important Reminders
                        </h6>
                        <ul class="mb-0">
                            <li>This coupon is <strong>non-refundable</strong> and <strong>non-transferable</strong></li>
                            <li>Please show this QR code ticket at the mess counter</li>
                            <li>Valid only for the booked date and meal time</li>
                            <li>Keep this ticket safe until you use it</li>
                            <li>Arrive during the designated meal timing</li>
                            <?php if ($booking['booking_status'] === 'active'): ?>
                                <li class="text-success"><strong>Your booking is confirmed and ready to use!</strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="download_ticket.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>" 
                           class="btn btn-download btn-custom btn-lg">
                            <i class="fas fa-download me-2"></i>Download Ticket
                        </a>
                        <a href="index.php" class="btn btn-home btn-custom btn-lg">
                            <i class="fas fa-home me-2"></i>Book Another Coupon
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary btn-custom">
                            <i class="fas fa-print me-2"></i>Print Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to top on page load
        window.addEventListener('load', function() {
            window.scrollTo(0, 0);
        });

        // Add some interactive feedback
        document.querySelectorAll('.btn-custom').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Show success message with animation
        setTimeout(function() {
            const successCard = document.querySelector('.success-card');
            successCard.style.transform = 'scale(1.02)';
            setTimeout(function() {
                successCard.style.transform = 'scale(1)';
            }, 200);
        }, 500);
    </script>
</body>
</html>

<?php $conn->close(); ?>
