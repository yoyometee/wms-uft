<?php
session_start();

// Include configuration and classes
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

// Check login and permissions
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

checkPermission('office');

// Initialize database connection
$database = new Database();
$db = $database->connect();

header('Content-Type: application/json; charset=utf-8');

try {
    // Check if report_exports table exists, if not create it
    $create_table_query = "CREATE TABLE IF NOT EXISTS report_exports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_type VARCHAR(100) NOT NULL,
        format VARCHAR(20) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        file_size BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_report_type (report_type)
    )";
    
    $db->exec($create_table_query);
    
    // Get recent exports for current user (last 20)
    $query = "SELECT 
                r.report_type,
                r.format,
                r.filename,
                r.file_size,
                r.created_at,
                CASE 
                    WHEN r.report_type = 'abc-analysis' THEN 'ABC Analysis'
                    WHEN r.report_type = 'stock-aging' THEN 'Stock Aging'
                    WHEN r.report_type = 'inventory-valuation' THEN 'Inventory Valuation'
                    WHEN r.report_type = 'low-stock' THEN 'Low Stock Alert'
                    WHEN r.report_type = 'transaction-history' THEN 'Transaction History'
                    WHEN r.report_type = 'pick-efficiency' THEN 'Pick Efficiency'
                    WHEN r.report_type = 'movement-summary' THEN 'Movement Summary'
                    WHEN r.report_type = 'fefo-compliance' THEN 'FEFO Compliance'
                    WHEN r.report_type = 'space-utilization' THEN 'Space Utilization'
                    WHEN r.report_type = 'productivity-analysis' THEN 'Productivity Analysis'
                    ELSE r.report_type
                END as report_name
              FROM report_exports r
              WHERE r.user_id = :user_id
              ORDER BY r.created_at DESC
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted_exports = [];
    foreach($exports as $export) {
        $file_path = '../../exports/reports/' . $export['filename'];
        $file_exists = file_exists($file_path);
        
        $formatted_exports[] = [
            'report_name' => $export['report_name'],
            'format' => $export['format'],
            'filename' => $export['filename'],
            'file_size' => formatFileSize($export['file_size']),
            'created_at' => formatDate($export['created_at']),
            'file_url' => $file_exists ? 'exports/reports/' . $export['filename'] : '',
            'file_exists' => $file_exists
        ];
    }
    
    echo json_encode($formatted_exports);

} catch(Exception $e) {
    error_log("Get recent exports error: " . $e->getMessage());
    echo json_encode([]);
}

function formatFileSize($size) {
    if($size == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    while($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 2) . ' ' . $units[$unit];
}
?>