<?php
session_start();

$type = $_GET['type'] ?? '';

if ($type === 'admin') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    header('Location: admin-login.php');
} elseif ($type === 'staff') {
    unset($_SESSION['staff_id']);
    unset($_SESSION['staff_username']);
    unset($_SESSION['staff_mess_id']);
    header('Location: staff-login.php');
} else {
    // Default logout or redirect to main page if type is not specified
    session_destroy();
    header('Location: index.php');
}
exit;
?>
