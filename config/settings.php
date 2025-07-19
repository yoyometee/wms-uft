<?php
// Application settings
define('APP_NAME', 'Austam Good WMS');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/wms-uft');

// Security settings
define('SESSION_TIMEOUT', 7200); // 2 hours
define('PASSWORD_MIN_LENGTH', 6);

// WMS specific settings
define('DEFAULT_ZONE', 'Selective Rack');
define('FEFO_ENABLED', true);
define('BARCODE_ENABLED', true);

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'uploads/');
?>