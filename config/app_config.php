<?php
// Single source of truth for all configurations
// File: config/app_config.php

// Prevent multiple inclusions
if (defined('WMS_CONFIG_LOADED')) {
    return;
}
define('WMS_CONFIG_LOADED', true);

// Session Configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);
session_set_cookie_params(7200);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration (only define once)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'wms_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// Application Settings
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Austam Good WMS');
    define('APP_VERSION', '1.0.0');
    define('BASE_URL', '/wms-uft/');
    define('SESSION_TIMEOUT', 7200);
    define('FEFO_ENABLED', true);
    define('BARCODE_ENABLED', true);
    define('AUTO_STOCK_UPDATE', true);
}

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error Reporting
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0); // Hide warnings in production

// CSRF Protection
if (!defined('CSRF_TOKEN')) {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    define('CSRF_TOKEN', $_SESSION['csrf_token']);
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Helper function for base URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . BASE_URL;
}

// Database connection function
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }
    
    return $pdo;
}

// Check login function
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

// Check permission function
function checkPermission($required_role = 'worker') {
    checkLogin();
    
    $role_hierarchy = [
        'worker' => 1,
        'office' => 2,
        'admin' => 3
    ];
    
    $user_level = $role_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    if ($user_level < $required_level) {
        header('Location: ' . getBaseUrl() . 'index.php?error=permission_denied');
        exit;
    }
}

// Format functions
function formatNumber($number) {
    return number_format($number, 0, '.', ',');
}

function formatWeight($weight) {
    return number_format($weight, 2, '.', ',') . ' กก.';
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i:s', strtotime($datetime));
}

// CSRF token validation
function validateCSRF($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Generate CSRF token input
function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(CSRF_TOKEN) . '">';
}

// Additional utility functions needed by the application
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function logActivity($action, $description, $user_id = null) {
    if(!$user_id && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $log_dir = dirname(__DIR__) . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " - User: $user_id, Action: $action, Description: $description\n";
    file_put_contents($log_dir . '/activity.log', $log_entry, FILE_APPEND);
}

function getTransactionTypeColor($transaction_type) {
    $colors = [
        // Thai transaction types
        'เบิก' => 'danger',
        'รับ' => 'success',
        'ย้าย' => 'warning',
        'ปรับ' => 'info',
        'แปลง' => 'primary',
        'premium' => 'secondary',
        'return' => 'dark',
        // English transaction types
        'รับสินค้า' => 'success',
        'จัดเตรียมสินค้า' => 'primary',
        'ย้ายสินค้า' => 'info',
        'ปรับสต็อก' => 'warning',
        'การแปลง' => 'primary',
        'ออนไลน์' => 'info',
        'รีแพ็ค' => 'warning',
        // Additional types
        'picking' => 'primary',
        'receiving' => 'success',
        'moving' => 'warning',
        'adjustment' => 'info',
        'conversion' => 'primary'
    ];
    
    return $colors[strtolower($transaction_type)] ?? 'secondary';
}

function getTransactionIcon($transaction_type) {
    $icons = [
        'เบิก' => 'fas fa-arrow-down',
        'รับ' => 'fas fa-arrow-up',
        'ย้าย' => 'fas fa-exchange-alt',
        'ปรับ' => 'fas fa-edit',
        'แปลง' => 'fas fa-recycle',
        'premium' => 'fas fa-star',
        'return' => 'fas fa-undo'
    ];
    
    return $icons[strtolower($transaction_type)] ?? 'fas fa-box';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if($time < 60) return 'เมื่อสักครู่';
    if($time < 3600) return floor($time/60) . ' นาทีที่แล้ว';
    if($time < 86400) return floor($time/3600) . ' ชั่วโมงที่แล้ว';
    if($time < 2592000) return floor($time/86400) . ' วันที่แล้ว';
    if($time < 31536000) return floor($time/2592000) . ' เดือนที่แล้ว';
    
    return floor($time/31536000) . ' ปีที่แล้ว';
}
?>