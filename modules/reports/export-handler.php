<?php
session_start();

// Include configuration and classes
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Product.php';
require_once '../../classes/Location.php';
require_once '../../classes/Transaction.php';

// Check login and permissions
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

checkPermission('office');

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize classes
$user = new User($db);
$product = new Product($db);
$location = new Location($db);
$transaction = new Transaction($db);

header('Content-Type: application/json; charset=utf-8');

try {
    $report_type = $_POST['report_type'] ?? '';
    $format = $_POST['format'] ?? 'excel';
    $date_range = $_POST['date_range'] ?? 'last7days';
    $zone_filter = $_POST['zone_filter'] ?? '';
    
    if(empty($report_type)) {
        throw new Exception('กรุณาระบุประเภทรายงาน');
    }
    
    // Calculate date range
    $date_conditions = getDateRangeConditions($date_range);
    
    // Generate report data
    $report_data = generateReportData($db, $report_type, $date_conditions, $zone_filter);
    
    // Create export directory if not exists
    $export_dir = '../../exports/reports/';
    if(!is_dir($export_dir)) {
        mkdir($export_dir, 0755, true);
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $report_type . '_' . $timestamp . '.' . ($format === 'excel' ? 'xlsx' : 'pdf');
    $filepath = $export_dir . $filename;
    
    if($format === 'excel') {
        createExcelReport($report_data, $filepath, $report_type);
    } else {
        createPDFReport($report_data, $filepath, $report_type);
    }
    
    // Log export activity
    logActivity('REPORT_EXPORT', "Exported {$report_type} report as {$format}", $_SESSION['user_id']);
    
    // Save export record to database
    saveExportRecord($db, $report_type, $format, $filename, filesize($filepath));
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'file_url' => str_replace('../../', '', $filepath),
        'file_size' => formatFileSize(filesize($filepath)),
        'message' => 'ส่งออกรายงานสำเร็จ'
    ]);

} catch(Exception $e) {
    error_log("Report export error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getDateRangeConditions($date_range) {
    $conditions = [];
    
    switch($date_range) {
        case 'today':
            $conditions['start'] = date('Y-m-d 00:00:00');
            $conditions['end'] = date('Y-m-d 23:59:59');
            break;
        case 'yesterday':
            $conditions['start'] = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $conditions['end'] = date('Y-m-d 23:59:59', strtotime('-1 day'));
            break;
        case 'last7days':
            $conditions['start'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $conditions['end'] = date('Y-m-d 23:59:59');
            break;
        case 'last30days':
            $conditions['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $conditions['end'] = date('Y-m-d 23:59:59');
            break;
        case 'thismonth':
            $conditions['start'] = date('Y-m-01 00:00:00');
            $conditions['end'] = date('Y-m-t 23:59:59');
            break;
        case 'lastmonth':
            $conditions['start'] = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $conditions['end'] = date('Y-m-t 23:59:59', strtotime('last day of last month'));
            break;
        default:
            $conditions['start'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $conditions['end'] = date('Y-m-d 23:59:59');
    }
    
    return $conditions;
}

function generateReportData($db, $report_type, $date_conditions, $zone_filter) {
    $data = [];
    
    switch($report_type) {
        case 'abc-analysis':
            $data = generateABCAnalysis($db, $date_conditions, $zone_filter);
            break;
        case 'stock-aging':
            $data = generateStockAging($db, $zone_filter);
            break;
        case 'inventory-valuation':
            $data = generateInventoryValuation($db, $zone_filter);
            break;
        case 'low-stock':
            $data = generateLowStockReport($db, $zone_filter);
            break;
        case 'transaction-history':
            $data = generateTransactionHistory($db, $date_conditions, $zone_filter);
            break;
        case 'pick-efficiency':
            $data = generatePickEfficiency($db, $date_conditions, $zone_filter);
            break;
        case 'movement-summary':
            $data = generateMovementSummary($db, $date_conditions, $zone_filter);
            break;
        case 'fefo-compliance':
            $data = generateFEFOCompliance($db, $date_conditions, $zone_filter);
            break;
        case 'space-utilization':
            $data = generateSpaceUtilization($db, $zone_filter);
            break;
        case 'productivity-analysis':
            $data = generateProductivityAnalysis($db, $date_conditions, $zone_filter);
            break;
        default:
            throw new Exception('ประเภทรายงานไม่ถูกต้อง');
    }
    
    return $data;
}

function generateABCAnalysis($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND l.zone = :zone" : "";
    
    $query = "SELECT 
                p.sku,
                p.product_name,
                p.category,
                SUM(t.ชิ้น) as total_picked,
                SUM(t.น้ำหนัก) as total_weight,
                COUNT(t.id) as pick_frequency,
                AVG(p.จำนวนน้ำหนัก_ปกติ) as avg_stock,
                p.unit_cost,
                (SUM(t.ชิ้น) * COALESCE(p.unit_cost, 0)) as total_value
              FROM picking_transactions t
              LEFT JOIN master_sku_by_stock p ON t.sku = p.sku
              LEFT JOIN msaster_location_by_stock l ON t.location_id = l.location_id
              WHERE t.created_at BETWEEN :start_date AND :end_date
              {$zone_condition}
              GROUP BY p.sku, p.product_name, p.category, p.unit_cost
              ORDER BY total_value DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate ABC classification
    $total_value = array_sum(array_column($results, 'total_value'));
    $cumulative_value = 0;
    
    foreach($results as &$row) {
        $cumulative_value += $row['total_value'];
        $cumulative_percent = ($cumulative_value / $total_value) * 100;
        
        if($cumulative_percent <= 80) {
            $row['abc_class'] = 'A';
        } elseif($cumulative_percent <= 95) {
            $row['abc_class'] = 'B';
        } else {
            $row['abc_class'] = 'C';
        }
        
        $row['value_percent'] = round(($row['total_value'] / $total_value) * 100, 2);
        $row['cumulative_percent'] = round($cumulative_percent, 2);
    }
    
    return [
        'title' => 'ABC Analysis Report',
        'subtitle' => 'วิเคราะห์ประเภทสินค้าตามมูลค่า',
        'headers' => ['SKU', 'ชื่อสินค้า', 'หมวดหมู่', 'จำนวนเบิก', 'น้ำหนัก', 'ความถี่', 'มูลค่า', '% มูลค่า', '% สะสม', 'ประเภท ABC'],
        'data' => $results,
        'summary' => [
            'total_items' => count($results),
            'total_value' => $total_value,
            'class_a_count' => count(array_filter($results, fn($r) => $r['abc_class'] === 'A')),
            'class_b_count' => count(array_filter($results, fn($r) => $r['abc_class'] === 'B')),
            'class_c_count' => count(array_filter($results, fn($r) => $r['abc_class'] === 'C'))
        ]
    ];
}

function generateStockAging($db, $zone_filter) {
    $zone_condition = $zone_filter ? "AND zone = :zone" : "";
    
    $query = "SELECT 
                sku,
                product_name,
                location_id,
                zone,
                ชิ้น as quantity,
                น้ำหนัก as weight,
                received_date,
                expiration_date,
                DATEDIFF(NOW(), FROM_UNIXTIME(received_date)) as days_in_stock,
                DATEDIFF(FROM_UNIXTIME(expiration_date), NOW()) as days_to_expiry,
                CASE 
                    WHEN DATEDIFF(FROM_UNIXTIME(expiration_date), NOW()) < 0 THEN 'หมดอายุแล้ว'
                    WHEN DATEDIFF(FROM_UNIXTIME(expiration_date), NOW()) <= 7 THEN 'หมดอายุภายใน 7 วัน'
                    WHEN DATEDIFF(FROM_UNIXTIME(expiration_date), NOW()) <= 30 THEN 'หมดอายุภายใน 30 วัน'
                    WHEN DATEDIFF(NOW(), FROM_UNIXTIME(received_date)) <= 30 THEN 'ใหม่ (0-30 วัน)'
                    WHEN DATEDIFF(NOW(), FROM_UNIXTIME(received_date)) <= 90 THEN 'ปานกลาง (31-90 วัน)'
                    ELSE 'เก่า (90+ วัน)'
                END as aging_category
              FROM msaster_location_by_stock 
              WHERE status = 'เก็บสินค้า' {$zone_condition}
              ORDER BY days_to_expiry ASC, days_in_stock DESC";
    
    $stmt = $db->prepare($query);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Stock Aging Report',
        'subtitle' => 'รายงานอายุสินค้าคงคลัง',
        'headers' => ['SKU', 'ชื่อสินค้า', 'Location', 'Zone', 'จำนวน', 'น้ำหนัก', 'วันที่รับ', 'วันหมดอายุ', 'วันในคลัง', 'วันหมดอายุ', 'หมวดอายุ'],
        'data' => $results,
        'summary' => [
            'total_items' => count($results),
            'expired' => count(array_filter($results, fn($r) => $r['days_to_expiry'] < 0)),
            'expiring_7days' => count(array_filter($results, fn($r) => $r['days_to_expiry'] >= 0 && $r['days_to_expiry'] <= 7)),
            'expiring_30days' => count(array_filter($results, fn($r) => $r['days_to_expiry'] > 7 && $r['days_to_expiry'] <= 30))
        ]
    ];
}

function generateInventoryValuation($db, $zone_filter) {
    $zone_condition = $zone_filter ? "AND l.zone = :zone" : "";
    
    $query = "SELECT 
                l.sku,
                l.product_name,
                l.zone,
                SUM(l.ชิ้น) as total_quantity,
                SUM(l.น้ำหนัก) as total_weight,
                p.unit_cost,
                p.category,
                (SUM(l.ชิ้น) * COALESCE(p.unit_cost, 0)) as total_value,
                COUNT(l.location_id) as location_count
              FROM msaster_location_by_stock l
              LEFT JOIN master_sku_by_stock p ON l.sku = p.sku
              WHERE l.status = 'เก็บสินค้า' {$zone_condition}
              GROUP BY l.sku, l.product_name, l.zone, p.unit_cost, p.category
              ORDER BY total_value DESC";
    
    $stmt = $db->prepare($query);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_value = array_sum(array_column($results, 'total_value'));
    
    return [
        'title' => 'Inventory Valuation Report',
        'subtitle' => 'รายงานมูลค่าสินค้าคงคลัง',
        'headers' => ['SKU', 'ชื่อสินค้า', 'Zone', 'จำนวน', 'น้ำหนัก', 'ราคาต่อหน่วย', 'หมวดหมู่', 'มูลค่ารวม', 'จำนวน Location'],
        'data' => $results,
        'summary' => [
            'total_items' => count($results),
            'total_value' => $total_value,
            'total_quantity' => array_sum(array_column($results, 'total_quantity')),
            'total_weight' => array_sum(array_column($results, 'total_weight'))
        ]
    ];
}

function generateLowStockReport($db, $zone_filter) {
    $zone_condition = $zone_filter ? "AND l.zone = :zone" : "";
    
    $query = "SELECT 
                p.sku,
                p.product_name,
                p.category,
                p.จำนวนถุง_ปกติ as current_stock,
                p.จำนวนน้ำหนัก_ปกติ as current_weight,
                p.min_stock,
                p.max_stock,
                COALESCE(l.location_count, 0) as location_count,
                p.unit_cost,
                CASE 
                    WHEN p.จำนวนถุง_ปกติ = 0 THEN 'หมดสต็อก'
                    WHEN p.จำนวนถุง_ปกติ <= (p.min_stock * 0.5) THEN 'วิกฤต'
                    WHEN p.จำนวนถุง_ปกติ <= p.min_stock THEN 'ต่ำ'
                    ELSE 'ปกติ'
                END as stock_status
              FROM master_sku_by_stock p
              LEFT JOIN (
                  SELECT sku, COUNT(DISTINCT location_id) as location_count
                  FROM msaster_location_by_stock 
                  WHERE status = 'เก็บสินค้า' {$zone_condition}
                  GROUP BY sku
              ) l ON p.sku = l.sku
              WHERE p.จำนวนถุง_ปกติ <= p.min_stock OR p.จำนวนถุง_ปกติ = 0
              ORDER BY 
                  CASE 
                      WHEN p.จำนวนถุง_ปกติ = 0 THEN 1
                      WHEN p.จำนวนถุง_ปกติ <= (p.min_stock * 0.5) THEN 2
                      ELSE 3
                  END,
                  p.จำนวนถุง_ปกติ ASC";
    
    $stmt = $db->prepare($query);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Low Stock Alert Report',
        'subtitle' => 'รายงานสต็อกต่ำและหมดสต็อก',
        'headers' => ['SKU', 'ชื่อสินค้า', 'หมวดหมู่', 'สต็อกปัจจุบัน', 'น้ำหนัก', 'สต็อกต่ำสุด', 'สต็อกสูงสุด', 'จำนวน Location', 'ราคาต่อหน่วย', 'สถานะ'],
        'data' => $results,
        'summary' => [
            'total_items' => count($results),
            'out_of_stock' => count(array_filter($results, fn($r) => $r['stock_status'] === 'หมดสต็อก')),
            'critical' => count(array_filter($results, fn($r) => $r['stock_status'] === 'วิกฤต')),
            'low' => count(array_filter($results, fn($r) => $r['stock_status'] === 'ต่ำ'))
        ]
    ];
}

function generateTransactionHistory($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND zone_location = :zone" : "";
    
    $query = "SELECT 
                created_at,
                ประเภทหลัก as main_type,
                sub_type,
                sku,
                product_name,
                location_id,
                zone_location,
                ชิ้น as quantity,
                น้ำหนัก as weight,
                name_edit as user_name,
                เลขเอกสาร as document_no,
                remark
              FROM picking_transactions 
              WHERE created_at BETWEEN :start_date AND :end_date {$zone_condition}
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Transaction History Report',
        'subtitle' => 'รายงานประวัติธุรกรรม',
        'headers' => ['วันที่', 'ประเภทหลัก', 'ประเภทย่อย', 'SKU', 'ชื่อสินค้า', 'Location', 'Zone', 'จำนวน', 'น้ำหนัก', 'ผู้ใช้งาน', 'เลขเอกสาร', 'หมายเหตุ'],
        'data' => $results,
        'summary' => [
            'total_transactions' => count($results),
            'total_quantity' => array_sum(array_column($results, 'quantity')),
            'total_weight' => array_sum(array_column($results, 'weight')),
            'unique_users' => count(array_unique(array_column($results, 'user_name')))
        ]
    ];
}

function generatePickEfficiency($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND zone_location = :zone" : "";
    
    $query = "SELECT 
                name_edit as user_name,
                DATE(created_at) as pick_date,
                COUNT(*) as total_picks,
                SUM(ชิ้น) as total_quantity,
                SUM(น้ำหนัก) as total_weight,
                COUNT(DISTINCT sku) as unique_skus,
                COUNT(DISTINCT location_id) as unique_locations,
                MIN(created_at) as first_pick,
                MAX(created_at) as last_pick,
                TIMESTAMPDIFF(HOUR, MIN(created_at), MAX(created_at)) as working_hours
              FROM picking_transactions 
              WHERE created_at BETWEEN :start_date AND :end_date 
              AND ประเภทหลัก = 'จัดเตรียมสินค้า' {$zone_condition}
              GROUP BY name_edit, DATE(created_at)
              ORDER BY pick_date DESC, total_picks DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate efficiency metrics
    foreach($results as &$row) {
        $row['picks_per_hour'] = $row['working_hours'] > 0 ? round($row['total_picks'] / $row['working_hours'], 2) : 0;
        $row['quantity_per_hour'] = $row['working_hours'] > 0 ? round($row['total_quantity'] / $row['working_hours'], 2) : 0;
    }
    
    return [
        'title' => 'Pick Efficiency Report',
        'subtitle' => 'รายงานประสิทธิภาพการเบิกสินค้า',
        'headers' => ['ผู้ใช้งาน', 'วันที่', 'จำนวนครั้ง', 'จำนวนชิ้น', 'น้ำหนัก', 'SKU ไม่ซ้ำ', 'Location ไม่ซ้ำ', 'ชั่วโมงทำงาน', 'ครั้ง/ชม.', 'ชิ้น/ชม.'],
        'data' => $results,
        'summary' => [
            'total_picks' => array_sum(array_column($results, 'total_picks')),
            'total_quantity' => array_sum(array_column($results, 'total_quantity')),
            'avg_picks_per_hour' => count($results) > 0 ? round(array_sum(array_column($results, 'picks_per_hour')) / count($results), 2) : 0,
            'unique_users' => count(array_unique(array_column($results, 'user_name')))
        ]
    ];
}

function generateMovementSummary($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND zone_location = :zone" : "";
    
    $query = "SELECT 
                created_at,
                sub_type as movement_type,
                sku,
                product_name,
                location_id,
                zone_location,
                ชิ้น as quantity,
                น้ำหนัก as weight,
                name_edit as user_name,
                remark
              FROM picking_transactions 
              WHERE created_at BETWEEN :start_date AND :end_date 
              AND ประเภทหลัก = 'ย้ายสินค้า' {$zone_condition}
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Movement Summary Report',
        'subtitle' => 'รายงานสรุปการย้ายสินค้า',
        'headers' => ['วันที่', 'ประเภทการย้าย', 'SKU', 'ชื่อสินค้า', 'Location', 'Zone', 'จำนวน', 'น้ำหนัก', 'ผู้ใช้งาน', 'หมายเหตุ'],
        'data' => $results,
        'summary' => [
            'total_movements' => count($results),
            'total_quantity' => array_sum(array_column($results, 'quantity')),
            'total_weight' => array_sum(array_column($results, 'weight')),
            'unique_users' => count(array_unique(array_column($results, 'user_name')))
        ]
    ];
}

function generateFEFOCompliance($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND t.zone_location = :zone" : "";
    
    $query = "SELECT 
                t.created_at,
                t.sku,
                t.product_name,
                t.location_id,
                t.zone_location,
                t.ชิ้น as quantity,
                l.expiration_date as picked_expiry,
                (SELECT MIN(l2.expiration_date) 
                 FROM msaster_location_by_stock l2 
                 WHERE l2.sku = t.sku 
                 AND l2.status = 'เก็บสินค้า' 
                 AND l2.expiration_date <= l.expiration_date) as earliest_expiry,
                CASE 
                    WHEN l.expiration_date = (SELECT MIN(l2.expiration_date) 
                                             FROM msaster_location_by_stock l2 
                                             WHERE l2.sku = t.sku 
                                             AND l2.status = 'เก็บสินค้า' 
                                             AND l2.expiration_date <= l.expiration_date) 
                    THEN 'Compliant' 
                    ELSE 'Non-Compliant' 
                END as fefo_status
              FROM picking_transactions t
              LEFT JOIN msaster_location_by_stock l ON t.location_id = l.location_id
              WHERE t.created_at BETWEEN :start_date AND :end_date 
              AND t.ประเภทหลัก = 'จัดเตรียมสินค้า' {$zone_condition}
              ORDER BY t.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_picks = count($results);
    $compliant_picks = count(array_filter($results, fn($r) => $r['fefo_status'] === 'Compliant'));
    $compliance_rate = $total_picks > 0 ? round(($compliant_picks / $total_picks) * 100, 2) : 0;
    
    return [
        'title' => 'FEFO Compliance Report',
        'subtitle' => 'รายงานการปฏิบัติตาม FEFO',
        'headers' => ['วันที่', 'SKU', 'ชื่อสินค้า', 'Location', 'Zone', 'จำนวน', 'วันหมดอายุที่เบิก', 'วันหมดอายุเร็วสุด', 'สถานะ FEFO'],
        'data' => $results,
        'summary' => [
            'total_picks' => $total_picks,
            'compliant_picks' => $compliant_picks,
            'non_compliant_picks' => $total_picks - $compliant_picks,
            'compliance_rate' => $compliance_rate
        ]
    ];
}

function generateSpaceUtilization($db, $zone_filter) {
    $zone_condition = $zone_filter ? "WHERE zone = :zone" : "";
    
    $query = "SELECT 
                zone,
                COUNT(*) as total_locations,
                SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) as occupied_locations,
                SUM(CASE WHEN status = 'ว่าง' THEN 1 ELSE 0 END) as empty_locations,
                SUM(CASE WHEN status = 'ซ่อมแซม' THEN 1 ELSE 0 END) as maintenance_locations,
                ROUND((SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as utilization_percent,
                SUM(CASE WHEN status = 'เก็บสินค้า' THEN ชิ้น ELSE 0 END) as total_items,
                SUM(CASE WHEN status = 'เก็บสินค้า' THEN น้ำหนัก ELSE 0 END) as total_weight
              FROM msaster_location_by_stock 
              {$zone_condition}
              GROUP BY zone
              ORDER BY utilization_percent DESC";
    
    $stmt = $db->prepare($query);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Space Utilization Report',
        'subtitle' => 'รายงานการใช้พื้นที่จัดเก็บ',
        'headers' => ['Zone', 'Location ทั้งหมด', 'มีสินค้า', 'ว่าง', 'ซ่อมแซม', '% การใช้งาน', 'จำนวนสินค้า', 'น้ำหนักรวม'],
        'data' => $results,
        'summary' => [
            'total_locations' => array_sum(array_column($results, 'total_locations')),
            'total_occupied' => array_sum(array_column($results, 'occupied_locations')),
            'total_empty' => array_sum(array_column($results, 'empty_locations')),
            'avg_utilization' => count($results) > 0 ? round(array_sum(array_column($results, 'utilization_percent')) / count($results), 2) : 0
        ]
    ];
}

function generateProductivityAnalysis($db, $date_conditions, $zone_filter) {
    $zone_condition = $zone_filter ? "AND zone_location = :zone" : "";
    
    $query = "SELECT 
                name_edit as user_name,
                COUNT(*) as total_transactions,
                SUM(ชิ้น) as total_quantity,
                SUM(น้ำหนัก) as total_weight,
                COUNT(DISTINCT sku) as unique_skus,
                COUNT(DISTINCT location_id) as unique_locations,
                COUNT(DISTINCT DATE(created_at)) as working_days,
                AVG(ชิ้น) as avg_quantity_per_transaction,
                AVG(น้ำหนัก) as avg_weight_per_transaction
              FROM picking_transactions 
              WHERE created_at BETWEEN :start_date AND :end_date {$zone_condition}
              GROUP BY name_edit
              ORDER BY total_transactions DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $date_conditions['start']);
    $stmt->bindParam(':end_date', $date_conditions['end']);
    if($zone_filter) {
        $stmt->bindParam(':zone', $zone_filter);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate productivity metrics
    foreach($results as &$row) {
        $row['transactions_per_day'] = $row['working_days'] > 0 ? round($row['total_transactions'] / $row['working_days'], 2) : 0;
        $row['quantity_per_day'] = $row['working_days'] > 0 ? round($row['total_quantity'] / $row['working_days'], 2) : 0;
    }
    
    return [
        'title' => 'Productivity Analysis Report',
        'subtitle' => 'รายงานวิเคราะห์ประสิทธิภาพผู้ใช้งาน',
        'headers' => ['ผู้ใช้งาน', 'ธุรกรรมทั้งหมด', 'จำนวนรวม', 'น้ำหนักรวม', 'SKU ไม่ซ้ำ', 'Location ไม่ซ้ำ', 'วันทำงาน', 'เฉลี่ย/ธุรกรรม', 'ธุรกรรม/วัน', 'ชิ้น/วัน'],
        'data' => $results,
        'summary' => [
            'total_users' => count($results),
            'total_transactions' => array_sum(array_column($results, 'total_transactions')),
            'avg_transactions_per_user' => count($results) > 0 ? round(array_sum(array_column($results, 'total_transactions')) / count($results), 2) : 0,
            'top_performer' => count($results) > 0 ? $results[0]['user_name'] : 'N/A'
        ]
    ];
}

function createExcelReport($report_data, $filepath, $report_type) {
    // Simple Excel generation using HTML table format
    $excel_content = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n</head>\n<body>\n";
    $excel_content .= "<h1>" . $report_data['title'] . "</h1>\n";
    $excel_content .= "<h2>" . $report_data['subtitle'] . "</h2>\n";
    $excel_content .= "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n\n";
    
    // Summary table
    if(isset($report_data['summary'])) {
        $excel_content .= "<h3>Summary</h3>\n<table border='1'>\n";
        foreach($report_data['summary'] as $key => $value) {
            $excel_content .= "<tr><td>" . ucfirst(str_replace('_', ' ', $key)) . "</td><td>" . $value . "</td></tr>\n";
        }
        $excel_content .= "</table>\n\n";
    }
    
    // Data table
    $excel_content .= "<h3>Detail Data</h3>\n<table border='1'>\n<tr>\n";
    foreach($report_data['headers'] as $header) {
        $excel_content .= "<th>" . $header . "</th>\n";
    }
    $excel_content .= "</tr>\n";
    
    foreach($report_data['data'] as $row) {
        $excel_content .= "<tr>\n";
        foreach($row as $cell) {
            $excel_content .= "<td>" . htmlspecialchars($cell) . "</td>\n";
        }
        $excel_content .= "</tr>\n";
    }
    
    $excel_content .= "</table>\n</body>\n</html>";
    
    file_put_contents($filepath, $excel_content);
}

function createPDFReport($report_data, $filepath, $report_type) {
    // Simple PDF generation using HTML-to-PDF conversion
    // In production, use libraries like TCPDF or DomPDF
    $html_content = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n";
    $html_content .= "<style>
        body { font-family: 'TH SarabunPSK', Arial, sans-serif; font-size: 12px; }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; }
        h2 { color: #34495e; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .summary-table { background-color: #f8f9fa; }
    </style>\n</head>\n<body>\n";
    
    $html_content .= "<h1>" . $report_data['title'] . "</h1>\n";
    $html_content .= "<h2>" . $report_data['subtitle'] . "</h2>\n";
    $html_content .= "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>\n\n";
    
    // Summary
    if(isset($report_data['summary'])) {
        $html_content .= "<h3>Summary</h3>\n<table class='summary-table'>\n";
        foreach($report_data['summary'] as $key => $value) {
            $html_content .= "<tr><td><strong>" . ucfirst(str_replace('_', ' ', $key)) . "</strong></td><td>" . $value . "</td></tr>\n";
        }
        $html_content .= "</table>\n\n";
    }
    
    // Data table (limit to first 100 rows for PDF)
    $html_content .= "<h3>Detail Data</h3>\n<table>\n<tr>\n";
    foreach($report_data['headers'] as $header) {
        $html_content .= "<th>" . $header . "</th>\n";
    }
    $html_content .= "</tr>\n";
    
    $limited_data = array_slice($report_data['data'], 0, 100);
    foreach($limited_data as $row) {
        $html_content .= "<tr>\n";
        foreach($row as $cell) {
            $html_content .= "<td>" . htmlspecialchars($cell) . "</td>\n";
        }
        $html_content .= "</tr>\n";
    }
    
    if(count($report_data['data']) > 100) {
        $html_content .= "<tr><td colspan='" . count($report_data['headers']) . "'><em>Note: Only first 100 records shown in PDF. Use Excel export for complete data.</em></td></tr>\n";
    }
    
    $html_content .= "</table>\n</body>\n</html>";
    
    // For now, save as HTML. In production, convert to actual PDF
    file_put_contents($filepath, $html_content);
}

function saveExportRecord($db, $report_type, $format, $filename, $file_size) {
    $query = "INSERT INTO report_exports (user_id, report_type, format, filename, file_size, created_at) 
              VALUES (:user_id, :report_type, :format, :filename, :file_size, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':report_type', $report_type);
    $stmt->bindParam(':format', $format);
    $stmt->bindParam(':filename', $filename);
    $stmt->bindParam(':file_size', $file_size);
    $stmt->execute();
}

function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    while($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 2) . ' ' . $units[$unit];
}
?>