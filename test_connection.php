<?php
// ไฟล์ทดสอบการเชื่อมต่อฐานข้อมูล
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 ทดสอบการเชื่อมต่อฐานข้อมูล MySQL</h2>";

// ทดสอบการเชื่อมต่อแบบต่างๆ
$test_configs = [
    [
        'host' => 'localhost',
        'port' => '3306',
        'user' => 'root',
        'pass' => '',
        'description' => 'MySQL บน port 3306 (XAMPP default)'
    ],
    [
        'host' => 'localhost',
        'port' => '3307',
        'user' => 'root',
        'pass' => '',
        'description' => 'MySQL บน port 3307 (alternative)'
    ],
    [
        'host' => '127.0.0.1',
        'port' => '3306',
        'user' => 'root',
        'pass' => '',
        'description' => 'MySQL บน 127.0.0.1:3306'
    ]
];

foreach($test_configs as $index => $config) {
    echo "<h3>ทดสอบที่ " . ($index + 1) . ": {$config['description']}</h3>";
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "✅ <span style='color: green;'>เชื่อมต่อสำเร็จ!</span><br>";
        
        // ทดสอบสร้างฐานข้อมูล
        $pdo->exec("CREATE DATABASE IF NOT EXISTS wms_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ <span style='color: green;'>สร้างฐานข้อมูล wms_system สำเร็จ!</span><br>";
        
        // ทดสอบเลือกฐานข้อมูล
        $pdo->exec("USE wms_system");
        echo "✅ <span style='color: green;'>เลือกฐานข้อมูล wms_system สำเร็จ!</span><br>";
        
        // ตรวจสอบตาราง
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if(count($tables) > 0) {
            echo "✅ <span style='color: green;'>พบตาราง " . count($tables) . " ตาราง: " . implode(', ', array_slice($tables, 0, 5)) . (count($tables) > 5 ? '...' : '') . "</span><br>";
        } else {
            echo "⚠️ <span style='color: orange;'>ไม่พบตาราง - ต้อง import database_schema.sql</span><br>";
        }
        
        echo "<p style='background: #e8f5e8; padding: 10px; border-radius: 5px;'>";
        echo "<strong>🎯 การตั้งค่าที่ใช้:</strong><br>";
        echo "Host: {$config['host']}<br>";
        echo "Port: {$config['port']}<br>";
        echo "User: {$config['user']}<br>";
        echo "Password: " . (empty($config['pass']) ? '(ว่าง)' : '***') . "<br>";
        echo "</p>";
        
        // ใช้การตั้งค่านี้
        $working_config = $config;
        break;
        
    } catch(PDOException $e) {
        echo "❌ <span style='color: red;'>เชื่อมต่อไม่สำเร็จ: " . $e->getMessage() . "</span><br>";
    }
    
    echo "<hr>";
}

if(isset($working_config)) {
    echo "<h2>🚀 ขั้นตอนถัดไป:</h2>";
    echo "<ol>";
    echo "<li><strong>อัปเดตไฟล์ config/database.php</strong> ให้ใช้การตั้งค่าที่ทำงาน</li>";
    
    if(count($tables) == 0) {
        echo "<li><strong>Import ฐานข้อมูล:</strong> ไปที่ phpMyAdmin แล้ว import ไฟล์ database_schema.sql</li>";
    }
    
    echo "<li><strong>ทดสอบระบบ:</strong> ไปที่ <a href='login.php' target='_blank'>login.php</a></li>";
    echo "</ol>";
    
    // สร้างไฟล์ config ที่ถูกต้อง
    $new_config = "<?php
// Database configuration - Updated by test_connection.php
define('DB_HOST', '{$working_config['host']}');
define('DB_PORT', '{$working_config['port']}');
define('DB_NAME', 'wms_system');
define('DB_USER', '{$working_config['user']}');
define('DB_PASS', '{$working_config['pass']}');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>";

    echo "<h3>📝 ไฟล์ config/database.php ที่แนะนำ:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($new_config);
    echo "</pre>";
    
} else {
    echo "<h2>❌ ไม่สามารถเชื่อมต่อได้</h2>";
    echo "<p>ลองตรวจสอบ:</p>";
    echo "<ul>";
    echo "<li>MySQL service ทำงานหรือไม่</li>";
    echo "<li>Port ที่ถูกต้อง</li>";
    echo "<li>Username/Password</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.php'>← กลับไปหน้าหลัก</a> | <a href='login.php'>ไปหน้า Login</a></p>";
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}

h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}

pre {
    overflow-x: auto;
    font-size: 14px;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>