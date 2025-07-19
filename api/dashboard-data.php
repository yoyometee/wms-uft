<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Include required files
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Product.php';
require_once '../classes/Location.php';
require_once '../classes/Transaction.php';

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
    $transaction = new Transaction($db);
    
    // Get dashboard statistics
    $stats = getDashboardStats($db);
    
    // Get chart data
    $charts = getDashboardCharts($db);
    
    // Get recent activities
    $activities = getRecentActivities($db);
    
    // Get alerts
    $alerts = getDashboardAlerts($db, $product, $location);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'charts' => $charts,
            'activities' => $activities,
            'alerts' => $alerts,
            'timestamp' => time()
        ]
    ]);

} catch(Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '@4I-4%2C2#B+%I-!9% Dashboard'
    ]);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats($db) {
    $stats = [];
    
    try {
        // Total products
        $stmt = $db->query("SELECT COUNT(*) as count FROM master_products");
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Total locations
        $stmt = $db->query("SELECT COUNT(*) as count FROM msaster_location_by_stock");
        $stats['total_locations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Occupied locations
        $stmt = $db->query("SELECT COUNT(*) as count FROM msaster_location_by_stock WHERE status = '@G*4I2'");
        $stats['occupied_locations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Today's transactions
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM transaction_product_flow 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['today_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Low stock items
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM master_products 
            WHERE 3'8_4 <= min_stock AND min_stock > 0
        ");
        $stats['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // High stock items
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM master_products 
            WHERE 3'8_4 >= max_stock AND max_stock > 0
        ");
        $stats['high_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Available locations
        $stmt = $db->query("SELECT COUNT(*) as count FROM msaster_location_by_stock WHERE status = ''H2'");
        $stats['available_locations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Total stock value (approximate)
        $stmt = $db->query("
            SELECT SUM(3'8_4 * COALESCE(cost_per_unit, 0)) as total_value 
            FROM master_products
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_stock_value'] = $result['total_value'] ?? 0;
        
        // Location utilization percentage
        $total_locations = $stats['total_locations'];
        $occupied_locations = $stats['occupied_locations'];
        $stats['utilization_percent'] = $total_locations > 0 ? round(($occupied_locations / $total_locations) * 100, 2) : 0;
        
        // This week's transaction count
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM transaction_product_flow 
            WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ");
        $stats['week_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Active users today
        $stmt = $db->query("
            SELECT COUNT(DISTINCT created_by) as count 
            FROM transaction_product_flow 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['active_users_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
    } catch(Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Get dashboard chart data
 */
function getDashboardCharts($db) {
    $charts = [];
    
    try {
        // Stock level distribution
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN 3'8_4 = 0 THEN '+!'
                    WHEN 3'8_4 <= min_stock THEN 'H3'
                    WHEN 3'8_4 >= max_stock THEN '*9'
                    ELSE '4'
                END as level,
                COUNT(*) as count
            FROM master_products
            GROUP BY level
        ");
        
        $stockLevels = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stockLevels[$row['level']] = $row['count'];
        }
        
        $charts['stockLevel'] = [
            'labels' => ['4', 'H3', '*9', '+!'],
            'datasets' => [{
                'data' => [
                    $stockLevels['4'] ?? 0,
                    $stockLevels['H3'] ?? 0,
                    $stockLevels['*9'] ?? 0,
                    $stockLevels['+!'] ?? 0
                ],
                'backgroundColor' => ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
            ]]
        ];
        
        // Transaction trend (last 7 days)
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as date,
                #0@ +%1 as type,
                COUNT(*) as count
            FROM transaction_product_flow 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), #0@ +%1
            ORDER BY date
        ");
        
        $trendData = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['date'];
            $type = $row['type'];
            $count = $row['count'];
            
            if(!isset($trendData[$date])) {
                $trendData[$date] = ['#1@I2' => 0, 'H2"--' => 0];
            }
            
            if($type === '#1@I2') {
                $trendData[$date]['#1@I2'] = $count;
            } elseif($type === 'H2"--') {
                $trendData[$date]['H2"--'] = $count;
            }
        }
        
        $dates = array_keys($trendData);
        $receive_data = array_values(array_column($trendData, '#1@I2'));
        $issue_data = array_values(array_column($trendData, 'H2"--'));
        
        $charts['transactionTrend'] = [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => '#1@I2',
                    'data' => $receive_data,
                    'borderColor' => '#007bff',
                    'backgroundColor' => 'rgba(0, 123, 255, 0.1)'
                ],
                [
                    'label' => 'H2"--',
                    'data' => $issue_data,
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)'
                ]
            ]
        ];
        
        // Zone utilization
        $stmt = $db->query("
            SELECT 
                zone,
                COUNT(*) as total,
                SUM(CASE WHEN status = '@G*4I2' THEN 1 ELSE 0 END) as occupied
            FROM msaster_location_by_stock 
            GROUP BY zone
        ");
        
        $zoneData = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $utilization = $row['total'] > 0 ? round(($row['occupied'] / $row['total']) * 100, 1) : 0;
            $zoneData[] = [
                'zone' => $row['zone'],
                'utilization' => $utilization,
                'total' => $row['total'],
                'occupied' => $row['occupied']
            ];
        }
        
        $charts['zoneUtilization'] = [
            'labels' => array_column($zoneData, 'zone'),
            'datasets' => [{
                'label' => '2#C
I2 (%)',
                'data' => array_column($zoneData, 'utilization'),
                'backgroundColor' => ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6610f2']
            }]
        ];
        
    } catch(Exception $e) {
        error_log("Error getting dashboard charts: " . $e->getMessage());
    }
    
    return $charts;
}

/**
 * Get recent activities
 */
function getRecentActivities($db) {
    $activities = [];
    
    try {
        $stmt = $db->query("
            SELECT 
                t.*,
                u.
7H-_*8% as user_name
            FROM transaction_product_flow t
            LEFT JOIN master_users u ON t.created_by = u.user_id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = [
                'id' => $row['id'],
                'type' => $row['#0@ +%1'],
                'sku' => $row['sku'],
                'quantity' => $row['
4I'],
                'location' => $row['location_id'] ?? '-',
                'user' => $row['user_name'] ?? 'Unknown',
                'timestamp' => $row['created_at'],
                'pallet_id' => $row['pallet_id'] ?? '-'
            ];
        }
        
    } catch(Exception $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
    }
    
    return $activities;
}

/**
 * Get dashboard alerts
 */
function getDashboardAlerts($db, $product, $location) {
    $alerts = [];
    
    try {
        // Low stock alerts
        $low_stock = $product->getLowStockProducts();
        if(count($low_stock) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '*4I2C%I+!*G-',
                'message' => '!5*4I2 ' . count($low_stock) . ' #2"2#5HC%I+!*G-',
                'count' => count($low_stock),
                'action_url' => 'modules/reports/stock.php?filter=low'
            ];
        }
        
        // High stock alerts
        $high_stock = $product->getHighStockProducts();
        if(count($high_stock) > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => '*4I2*G-*9',
                'message' => '!5*4I2 ' . count($high_stock) . ' #2"2#5H!5*G-*9@43+',
                'count' => count($high_stock),
                'action_url' => 'modules/reports/stock.php?filter=high'
            ];
        }
        
        // Expiring items
        $expiring = $location->getExpiringSoon(30);
        if(count($expiring) > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => '*4I2C%I+!-2"8',
                'message' => '!5*4I2 ' . count($expiring) . ' #2"2#5H0+!-2"8C 30 '1',
                'count' => count($expiring),
                'action_url' => 'modules/reports/locations.php?expiry=expiring_soon'
            ];
        }
        
        // Location utilization warning
        $utilization = $location->getLocationUtilization();
        $high_util_zones = array_filter($utilization, function($zone) {
            return $zone['utilization_percent'] > 90;
        });
        
        if(count($high_util_zones) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Zone @G!@7-+!',
                'message' => '!5 ' . count($high_util_zones) . ' Zone 5HC
I2A%I'@4 90%',
                'count' => count($high_util_zones),
                'action_url' => 'modules/reports/locations.php'
            ];
        }
        
    } catch(Exception $e) {
        error_log("Error getting dashboard alerts: " . $e->getMessage());
    }
    
    return $alerts;
}
?>