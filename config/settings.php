<?php
// WMS Application Settings
define('APP_NAME', 'Austam Good WMS');
define('APP_VERSION', '1.0.0');
define('APP_URL', '/wms-uft');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wms_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session Configuration  
define('SESSION_TIMEOUT', 7200); // 2 hours
define('PASSWORD_MIN_LENGTH', 6);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

// Security Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Feature Flags
define('FEFO_ENABLED', true);
define('BARCODE_ENABLED', true);
define('AUTO_STOCK_UPDATE', true);
define('PWA_ENABLED', true);
define('NOTIFICATIONS_ENABLED', true);

// System Settings
define('DEFAULT_TIMEZONE', 'Asia/Bangkok');
define('DEFAULT_LANGUAGE', 'th');
define('ITEMS_PER_PAGE', 25);

// Warehouse Configuration
define('DEFAULT_ZONE', 'Selective Rack');
define('MAX_PALLET_WEIGHT', 1000);
define('MAX_LOCATION_HEIGHT', 1800);

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'DEBUG');
define('LOG_PATH', __DIR__ . '/../logs/');

// Environment Setup
date_default_timezone_set('Asia/Bangkok');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// UTF-8 Settings
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>