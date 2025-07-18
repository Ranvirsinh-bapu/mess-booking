<?php
session_start();
require_once 'config.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id, username, password, mess_id FROM staff WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();

    if ($staff && password_verify($password, $staff['password'])) {
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['staff_username'] = $staff['username'];
        $_SESSION['staff_mess_id'] = $staff['mess_id']; // Store mess_id for staff
        header('Location: staff-dashboard.php');
        exit;
    } else {
        $error_message = "Invalid username or password.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%); /* Green gradient for staff */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            background-color: #fff;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .login-header h2 {
            font-weight: 700;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
            border-color: #28a745;
        }
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #218838;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-users"></i> Staff Login</h2>
            <p class="text-muted">Access the mess management panel</p>
        </div>
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <form action="staff-login.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Login</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none text-muted"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
