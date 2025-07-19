<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Include required files
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Product.php';
require_once '../classes/Location.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Initialize classes
    $product = new Product($db);
    $location = new Location($db);
    
    $notifications = [];
    
    // Check for low stock products
    $low_stock_products = $product->getLowStockProducts();
    foreach($low_stock_products as $item) {
        $notifications[] = [
            'id' => 'low_stock_' . $item['sku'],
            'type' => 'warning',
            'icon' => 'fas fa-exclamation-triangle text-warning',
            'message' => 'สินค้า ' . $item['sku'] . ' ใกล้หมดสต็อก',
            'description' => 'เหลือ ' . number_format($item['จำนวนถุง_ปกติ']) . ' ถุง (ขั้นต่ำ: ' . number_format($item['min_stock']) . ')',
            'url' => '../modules/receive/?sku=' . urlencode($item['sku']),
            'time' => timeAgo($item['updated_at']),
            'priority' => 'high'
        ];
    }
    
    // Check for products expiring soon (within 7 days)
    $expiring_products = $location->getExpiringSoon(7);
    foreach($expiring_products as $item) {
        $days_to_expire = ceil(($item['expiration_date'] - time()) / (60 * 60 * 24));
        
        $notifications[] = [
            'id' => 'expiring_' . $item['location_id'],
            'type' => 'danger',
            'icon' => 'fas fa-calendar-times text-danger',
            'message' => 'สินค้าใกล้หมดอายุ ใน Location ' . $item['location_id'],
            'description' => 'SKU: ' . $item['sku'] . ' หมดอายุใน ' . $days_to_expire . ' วัน',
            'url' => '../modules/picking/?location=' . urlencode($item['location_id']),
            'time' => 'หมดอายุใน ' . $days_to_expire . ' วัน',
            'priority' => $days_to_expire <= 3 ? 'critical' : 'high'
        ];
    }
    
    // Check for high stock products (over maximum)
    $high_stock_products = $product->getHighStockProducts();
    foreach($high_stock_products as $item) {
        $notifications[] = [
            'id' => 'high_stock_' . $item['sku'],
            'type' => 'info',
            'icon' => 'fas fa-arrow-up text-info',
            'message' => 'สินค้า ' . $item['sku'] . ' สต็อกสูงกว่าปกติ',
            'description' => 'มี ' . number_format($item['จำนวนถุง_ปกติ']) . ' ถุง (ขั้นสูง: ' . number_format($item['max_stock']) . ')',
            'url' => '../modules/picking/?sku=' . urlencode($item['sku']),
            'time' => timeAgo($item['updated_at']),
            'priority' => 'medium'
        ];
    }
    
    // Check location utilization
    $location_utilization = $location->getLocationUtilization();
    foreach($location_utilization as $zone_info) {
        if($zone_info['utilization_percent'] > 90) {
            $notifications[] = [
                'id' => 'zone_full_' . md5($zone_info['zone']),
                'type' => 'warning',
                'icon' => 'fas fa-warehouse text-warning',
                'message' => 'Zone ' . $zone_info['zone'] . ' เต็มเกือบหมด',
                'description' => 'ใช้งานแล้ว ' . $zone_info['utilization_percent'] . '%',
                'url' => '../modules/movement/',
                'time' => 'ตอนนี้',
                'priority' => 'medium'
            ];
        }
    }
    
    // Sort notifications by priority
    usort($notifications, function($a, $b) {
        $priority_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        $a_priority = $priority_order[$a['priority']] ?? 5;
        $b_priority = $priority_order[$b['priority']] ?? 5;
        return $a_priority - $b_priority;
    });
    
    // Limit to 10 most important notifications
    $notifications = array_slice($notifications, 0, 10);
    
    echo json_encode([
        'success' => true,
        'count' => count($notifications),
        'notifications' => $notifications,
        'timestamp' => time()
    ]);

} catch(Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการโหลดการแจ้งเตือน'
    ]);
}

// Helper function for time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $current_time = time();
    $time_diff = $current_time - $timestamp;
    
    if($time_diff < 60) {
        return 'เพิ่งเกิดขึ้น';
    } elseif($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return $minutes . ' นาทีที่แล้ว';
    } elseif($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return $hours . ' ชั่วโมงที่แล้ว';
    } elseif($time_diff < 2592000) {
        $days = floor($time_diff / 86400);
        return $days . ' วันที่แล้ว';
    } else {
        return date('d/m/Y', $timestamp);
    }
}
?>