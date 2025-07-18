<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reminder</title>
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
            background-color: #ff6b35;
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
        .reminder-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .booking-summary {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ff6b35;
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
        .download-button {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .download-button:hover {
            background-color: #218838;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #6c757d;
            color: white;
            border-radius: 0 0 5px 5px;
            font-size: 14px;
        }
        .reminder-icon {
            font-size: 48px;
            color: #ffc107;
            margin-bottom: 10px;
        }
        .time-highlight {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="reminder-icon">🔔</div>
        <h1>Meal Reminder</h1>
        <p>Don't forget about your mess booking today!</p>
    </div>
    
    <div class="content">
        <h2>Hello <?php echo htmlspecialchars($customer_name); ?>!</h2>
        
        <div class="reminder-box">
            <h3>⏰ Your meal is ready today!</h3>
            <p>This is a friendly reminder about your mess booking for today. Don't miss your meal!</p>
        </div>
        
        <div class="booking-summary">
            <h3>Your Booking Details</h3>
            
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
                <span class="detail-label">Meal Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($coupon_type); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking_date); ?></span>
            </div>
        </div>
        
        <div class="time-highlight">
            <h4>🕐 Meal Timing:</h4>
            <p><strong><?php echo htmlspecialchars($meal_time); ?></strong></p>
            <p><em>Please arrive within the specified time to avoid disappointment.</em></p>
        </div>
        
        <div style="text-align: center;">
            <a href="<?php echo htmlspecialchars($download_link); ?>" class="download-button">
                📱 Get Your Ticket
            </a>
        </div>
        
        <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h4>📝 Quick Checklist:</h4>
            <ul style="margin: 10px 0;">
                <li>✅ Download your ticket (if not already done)</li>
                <li>✅ Check the meal timing above</li>
                <li>✅ Locate the mess: <?php echo htmlspecialchars($mess_location); ?></li>
                <li>✅ Bring your ticket (digital or printed)</li>
                <li>✅ Arrive on time</li>
            </ul>
        </div>
        
        <p><strong>Need help?</strong> Contact the mess directly or reply to this email if you have any questions.</p>
        
        <p>We hope you enjoy your meal at <?php echo htmlspecialchars($mess_name); ?>!</p>
    </div>
    
    <div class="footer">
        <p><strong>PU Mess Booking System</strong></p>
        <p>Parul University | Email: support@pu.edu</p>
        <p>This is an automated reminder. Please do not reply directly to this message.</p>
    </div>
</body>
</html>
