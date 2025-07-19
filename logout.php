<?php
// Include new configuration system
require_once 'config/app_config.php';

// Log the logout activity
if(isset($_SESSION['user_id'])) {
    logActivity('LOGOUT', 'User logged out', $_SESSION['user_id']);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit;
?>