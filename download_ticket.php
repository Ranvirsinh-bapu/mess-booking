<?php
require_once 'config.php';

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

// Update ticket downloaded status
$update_download = $conn->prepare("UPDATE bookings SET ticket_downloaded = TRUE WHERE booking_id = ?");
$update_download->bind_param("s", $booking_id);
$update_download->execute();

// Generate HTML content for the ticket
$html_content = generateTicketHTML($booking);

// Set headers for HTML download (not PDF)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="mess_ticket_' . $booking_id . '.html"');

echo $html_content;

function generateTicketHTML($booking)
{
    $coupon_type_display = '';
    if ($booking['coupon_type'] === 'in_campus_monthly') {
        $coupon_type_display = 'In Campus Monthly (All meals)';
    } elseif ($booking['coupon_type'] === 'out_campus_monthly') {
        $coupon_type_display = 'Out Campus Monthly (Lunch only)';
    } else {
        $coupon_type_display = ucfirst($booking['meal_type']) . ' - Single Meal';
    }

    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Ticket - ' . htmlspecialchars($booking['booking_id']) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5;
        }
        .ticket { 
            background: white;
            border: 3px solid #2c3e50; 
            padding: 30px; 
            max-width: 700px; 
            margin: 0 auto; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #34495e; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .logo { 
            font-size: 28px; 
            font-weight: bold; 
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .university { 
            font-size: 18px; 
            color: #7f8c8d; 
        }
        .details { 
            margin: 20px 0; 
        }
        .details table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .details td { 
            padding: 12px 8px; 
            border-bottom: 1px dotted #bdc3c7; 
            vertical-align: top;
        }
        .details td:first-child {
            font-weight: bold;
            color: #2c3e50;
            width: 40%;
        }
        .details td:last-child {
            color: #34495e;
        }
        .qr-section { 
            text-align: center; 
            margin: 30px 0; 
        }
        .qr-code { 
            border: 2px solid #34495e; 
            width: 120px; 
            height: 120px; 
            margin: 0 auto; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background-color: #ecf0f1;
            border-radius: 8px;
        }
        .important { 
            background-color: #fff3cd; 
            border: 2px solid #ffc107; 
            padding: 20px; 
            margin: 25px 0; 
            border-radius: 8px;
        }
        .important h4 {
            color: #856404;
            margin-top: 0;
        }
        .important ul {
            margin: 10px 0;
            padding-left: 25px;
        }
        .important li {
            margin: 8px 0;
            color: #856404;
        }
        .footer { 
            text-align: center; 
            padding: 20px 0; 
            border-top: 2px solid #34495e; 
            margin-top: 30px;
            color: #7f8c8d;
        }
        .footer p {
            margin: 5px 0;
        }
        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
        }
        .booking-id {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
            background-color: #fdf2f2;
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block;
        }
        @media print {
            body { background-color: white; }
            .ticket { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="logo">🍽️ PU MESS COUPON TICKET</div>
            <div class="university">Parul University</div>
            <div style="margin-top: 15px;">
                <span class="booking-id">' . htmlspecialchars($booking['booking_id']) . '</span>
            </div>
        </div>
        
        <div class="details">
            <table>
                <tr>
                    <td>Customer Name:</td>
                    <td>' . htmlspecialchars($booking['user_name']) . '</td>
                </tr>
                <tr>
                    <td>User Type:</td>
                    <td>' . ucfirst($booking['user_type']) . '</td>
                </tr>
                <tr>
                    <td>Mess Name:</td>
                    <td>' . htmlspecialchars($booking['mess_name']) . '</td>
                </tr>
                <tr>
                    <td>Location:</td>
                    <td>' . htmlspecialchars($booking['mess_location']) . '</td>
                </tr>
                <tr>
                    <td>Contact:</td>
                    <td>' . htmlspecialchars($booking['mess_contact']) . '</td>
                </tr>
                <tr>
                    <td>Coupon Type:</td>
                    <td>' . $coupon_type_display . '</td>
                </tr>
                <tr>
                    <td>Number of Persons:</td>
                    <td>' . $booking['persons'] . '</td>
                </tr>
                <tr>
                    <td>Valid Date:</td>
                    <td>' . date('d-m-Y', strtotime($booking['booking_date'])) . '</td>
                </tr>
                <tr>
                    <td>Amount Paid:</td>
                    <td><span class="amount">₹' . number_format($booking['total_amount'], 2) . '</span></td>
                </tr>
                <tr>
                    <td>Booking Time:</td>
                    <td>' . date('d-m-Y H:i:s', strtotime($booking['created_at'])) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="qr-section">
            <div class="qr-section">
    <h5>Your QR Code Ticket:</h5>
    <div class="qr-code">
        <img src="generate_qr.php?booking_id=' . htmlspecialchars($booking['booking_id']) . '" alt="QR Code" />
    </div>
    <p style="margin-top: 10px; color: #7f8c8d; font-size: 14px;">Scan this code at the mess counter</p>
</div>

            <p style="margin-top: 10px; color: #7f8c8d; font-size: 14px;">Scan this code at the mess counter</p>
        </div>
        
        <div class="important">
            <h4>📋 IMPORTANT INSTRUCTIONS:</h4>
            <ul>
                <li><strong>Non-refundable:</strong> This booking cannot be cancelled or refunded</li>
                <li><strong>Non-transferable:</strong> This ticket cannot be transferred to another person</li>
                <li><strong>Show ticket:</strong> Present this ticket at the mess counter before eating</li>
                <li><strong>Valid date:</strong> This ticket is only valid for the specified date</li>
                <li><strong>Timing:</strong> Please arrive during the specified meal timings</li>
                <li><strong>Keep safe:</strong> Keep this ticket until you finish your meal</li>
            </ul>
        </div>
        
        <div class="footer">
            <p><strong>Thank you for choosing PU Mess Services!</strong></p>
            <p>📧 Email: support@pu.edu | 📞 Phone: ' . htmlspecialchars($booking['mess_contact']) . '</p>
            <p style="font-size: 12px; margin-top: 15px;">
                This is a computer-generated ticket. No signature required.<br>
                Generated on: ' . date('d-m-Y H:i:s') . '
            </p>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>';
}

$conn->close();
?>
