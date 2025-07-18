<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border: 1px solid #dee2e6;
        }
        .booking-details {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        .detail-value {
            color: #212529;
        }
        .total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        .download-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .download-button:hover {
            background-color: #0056b3;
        }
        .important-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #6c757d;
            color: white;
            border-radius: 0 0 5px 5px;
            font-size: 14px;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="success-icon">✓</div>
        <h1>Booking Confirmed!</h1>
        <p>Your mess coupon has been successfully booked</p>
    </div>
    
    <div class="content">
        <h2>Hello <?php echo htmlspecialchars($customer_name); ?>!</h2>
        
        <p>Thank you for booking with PU Mess Services. Your booking has been confirmed and is ready for use.</p>
        
        <div class="booking-details">
            <h3>Booking Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Booking ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking_id); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Mess Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($mess_name); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Location:</span>
                <span class="detail-value"><?php echo htmlspecialchars($mess_location); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Contact:</span>
                <span class="detail-value"><?php echo htmlspecialchars($mess_contact); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">User Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user_type); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Coupon Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($coupon_type); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Number of Persons:</span>
                <span class="detail-value"><?php echo htmlspecialchars($persons); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Valid Date:</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking_date); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value total-amount">₹<?php echo htmlspecialchars($total_amount); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Booking Time:</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking_time); ?></span>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="<?php echo htmlspecialchars($download_link); ?>" class="download-button">
                📥 Download Your Ticket
            </a>
        </div>
        
        <div class="important-info">
            <h4>📋 Important Reminders:</h4>
            <ul>
                <li><strong>Non-refundable:</strong> This booking cannot be cancelled or refunded</li>
                <li><strong>Non-transferable:</strong> This ticket cannot be transferred to another person</li>
                <li><strong>Show ticket:</strong> Please present your downloaded ticket at the mess counter</li>
                <li><strong>Valid date:</strong> This ticket is only valid for the specified date</li>
                <li><strong>Timing:</strong> Please arrive during the specified meal timings</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px;">
            <h4>What's Next?</h4>
            <ol>
                <li>Download your ticket using the button above</li>
                <li>Save the ticket on your phone or take a printout</li>
                <li>Visit the mess during meal time</li>
                <li>Show your ticket at the counter</li>
                <li>Enjoy your meal!</li>
            </ol>
        </div>
        
        <p>If you have any questions or need assistance, please contact us at <strong><?php echo htmlspecialchars($mess_contact); ?></strong> or reply to this email.</p>
        
        <p>Thank you for choosing PU Mess Services!</p>
    </div>
    
    <div class="footer">
        <p><strong>PU Mess Booking System</strong></p>
        <p>Parul University | Email: support@pu.edu | Phone: <?php echo htmlspecialchars($mess_contact); ?></p>
        <p>This is an automated email. Please do not reply directly to this message.</p>
    </div>
</body>
</html>
