<?php
session_start();
require_once 'config.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    // Registration
    if (isset($_POST['register'])) {
        $new_username = trim($_POST['new_username']);
        $new_password = trim($_POST['new_password']);

        if ($new_username && $new_password) {
            $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $checkStmt->bind_param("s", $new_username);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $error_message = "Username already exists.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $insertStmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $insertStmt->bind_param("ss", $new_username, $hashed_password);
                if ($insertStmt->execute()) {
                    $success_message = "Registration successful. You can now log in.";
                } else {
                    $error_message = "Registration failed. Try again.";
                }
            }
        } else {
            $error_message = "Both fields are required.";
        }
    }
    // Login
    else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $error_message = "Invalid username or password.";
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PU Mess Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <h2><i class="fas fa-user-shield"></i> Admin Login</h2>
            <p class="text-muted">Access the administration panel</p>
        </div>
        <form action="admin-login.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="input-group-text toggle-password" data-target="password" style="cursor:pointer;">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Login</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none text-muted"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
        </div>
        <div class="text-center mt-2">
            <a href="#" class="text-decoration-none text-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="fas fa-user-plus"></i> Register as Admin
            </a>
        </div>
    </div>

    <!-- Toasts -->
    <div class="toast-container">
        <?php if ($error_message): ?>
            <div class="toast align-items-center text-white bg-danger border-0 show" role="alert" id="errorToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="toast align-items-center text-white bg-success border-0 show" role="alert" id="successToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form action="admin-login.php" method="post" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="registerModalLabel"><i class="fas fa-user-plus"></i> Register Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="register" value="1">
            <div class="mb-3">
              <label for="new_username" class="form-label">Username</label>
              <input type="text" class="form-control" id="new_username" name="new_username" required>
            </div>
            <div class="mb-3">
              <label for="new_password" class="form-label">Password</label>
              <div class="input-group">
                  <input type="password" class="form-control" id="new_password" name="new_password" required>
                  <span class="input-group-text toggle-password" data-target="new_password" style="cursor:pointer;">
                      <i class="fas fa-eye"></i>
                  </span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Register</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle show/hide password
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = "password";
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Auto hide toasts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.toast').forEach(toast => {
                const bsToast = bootstrap.Toast.getOrCreateInstance(toast);
                bsToast.hide();
            });
        }, 5000);
    </script>
</body>
</html>
