<?php
header('Content-Type: application/json');
session_start();

// Include configuration and classes
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

// Check login
checkLogin();

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    $action = $_GET['action'] ?? '';
    
    if($action === 'get_urgent_reorders') {
        // Get urgent reorder recommendations
        $query = "SELECT 
                    r.id,
                    r.sku,
                    p.ชื่อ_สินค้า as product_name,
                    r.current_stock,
                    r.reorder_point,
                    r.recommended_quantity,
                    r.priority,
                    r.created_at
                  FROM reorder_recommendations r
                  LEFT JOIN master_sku_by_stock p ON r.sku = p.sku
                  WHERE r.status = 'pending' 
                  AND r.priority IN ('urgent', 'high')
                  ORDER BY FIELD(r.priority, 'urgent', 'high'), r.created_at ASC
                  LIMIT 10";
        
        $stmt = $db->query($query);
        $urgent_reorders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'urgent_reorders' => $urgent_reorders,
            'count' => count($urgent_reorders)
        ]);
        
    } elseif($action === 'mark_notification_read') {
        $reorder_id = $_POST['reorder_id'] ?? 0;
        
        // In a full implementation, you would track notification read status
        // For now, we'll just return success
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>