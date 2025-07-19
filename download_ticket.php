<?php
require_once 'config.php';

// Check if booking ID is provided
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Ticket - <?php echo htmlspecialchars($booking['booking_id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 20px; }
            .ticket-container { box-shadow: none !important; border: 2px solid #000 !important; }
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .ticket-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .ticket-body {
            padding: 30px;
        }
        
        .qr-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        
        .total-amount {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .btn-custom {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ticket-container">
            <!-- Ticket Header -->
            <div class="ticket-header">
                <h2><i class="fas fa-utensils me-2"></i>PU Mess Booking Ticket</h2>
                <p class="mb-0">Digital Coupon</p>
            </div>
            
            <!-- Ticket Body -->
            <div class="ticket-body">
                <!-- QR Code Section -->
                <div class="qr-section">
                    <h5 class="mb-3">Scan QR Code at Mess Counter</h5>
                    <img src="generate_qr.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>" 
                         alt="QR Code" style="width: 200px; height: 200px;" />
                    <p class="text-muted mt-2">Show this code for quick check-in</p>
                </div>
                
                <!-- Booking Details -->
                <div class="booking-details">
                    <h5 class="mb-3 text-center">Booking Information</h5>
                    
                    <div class="detail-row">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value"><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Customer Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['user_name']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['user_email']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['user_phone']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">User Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(ucfirst($booking['user_type'])); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Mess:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['mess_name']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['mess_location']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Contact:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['mess_contact']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Coupon Type:</span>
                        <span class="detail-value"><?php echo formatCouponType($booking['coupon_type'], $booking['meal_type']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Number of Persons:</span>
                        <span class="detail-value"><strong><?php echo htmlspecialchars($booking['persons']); ?></strong></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Booking Date:</span>
                        <span class="detail-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Booked At:</span>
                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="badge bg-<?php echo $booking['booking_status'] == 'active' ? 'primary' : ($booking['booking_status'] == 'completed' ? 'success' : 'secondary'); ?>">
                                <?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Total Amount -->
                <div class="total-amount">
                    <h4 class="mb-0">
                        <i class="fas fa-rupee-sign me-2"></i>
                        Total Amount: ₹<?php echo number_format($booking['total_amount'], 2); ?>
                    </h4>
                </div>
                
                <!-- Instructions -->
                <div class="instructions">
                    <h6><i class="fas fa-info-circle me-2"></i>Instructions:</h6>
                    <ul class="mb-0">
                        <li>Show this ticket (QR code) at the mess counter</li>
                        <li>Valid only for the booked date and meal</li>
                        <li>Non-refundable and non-transferable</li>
                        <li>Keep this ticket until you use it</li>
                    </ul>
                </div>
                
                <!-- Action Buttons -->
                <div class="text-center mt-4 no-print">
                    <button onclick="window.print()" class="btn btn-primary btn-custom">
                        <i class="fas fa-print me-2"></i>Print Ticket
                    </button>
                    <a href="booking-success.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>" 
                       class="btn btn-secondary btn-custom">
                        <i class="fas fa-arrow-left me-2"></i>Back to Booking
                    </a>
                    <a href="index.php" class="btn btn-success btn-custom">
                        <i class="fas fa-home me-2"></i>New Booking
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-print functionality (optional)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            setTimeout(() => {
                window.print();
            }, 1000);
        }
        
        // Add print styles dynamically
        const printStyles = `
            @media print {
                body { margin: 0; padding: 10px; background: white !important; }
                .ticket-container { 
                    box-shadow: none !important; 
                    border: 2px solid #000 !important;
                    page-break-inside: avoid;
                }
                .no-print { display: none !important; }
                .ticket-header { 
                    background: #333 !important; 
                    -webkit-print-color-adjust: exact;
                    color-adjust: exact;
                }
                .total-amount {
                    background: #666 !important;
                    -webkit-print-color-adjust: exact;
                    color-adjust: exact;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>

<?php $conn->close(); ?>
