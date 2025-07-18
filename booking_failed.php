<?php
require_once 'config.php';
include('header/header.php');

$error = $_GET['error'] ?? 'unknown';

$error_messages = [
    'sold_out' => 'Sorry, the selected meal coupons are sold out for today. Please try a different meal or come back tomorrow.',
    'database' => 'There was a technical error processing your booking. Please try again.',
    'invalid_time' => 'The selected meal time slot is no longer available.',
    'unknown' => 'An unexpected error occurred. Please try again.'
];

$error_message = $error_messages[$error] ?? $error_messages['unknown'];
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white text-center">
                    <h3><i class="fas fa-exclamation-triangle"></i> Booking Failed</h3>
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Try Again
                        </a>
                        <a href="mailto:support@pu.edu" class="btn btn-secondary">
                            <i class="fas fa-envelope"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
