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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if(!isset($input['search']) || empty(trim($input['search']))) {
    echo json_encode(['success' => false, 'error' => 'Search term required']);
    exit;
}

$search_term = trim($input['search']);

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Initialize classes
    $product = new Product($db);
    $location = new Location($db);
    $transaction = new Transaction($db);
    
    $results = [];
    
    // Search in products
    $products = $product->searchProducts($search_term);
    foreach($products as $prod) {
        $results[] = [
            'title' => $prod['sku'] . ' - ' . $prod['product_name'],
            'type' => 'สินค้า',
            'description' => 'หน่วย: ' . $prod['unit'] . ' | น้ำหนัก: ' . $prod['น้ำหนัก_ต่อ_ถุง'] . ' กก.',
            'extra' => 'สต็อก: ' . number_format($prod['จำนวนถุง_ปกติ']) . ' ถุง',
            'url' => '../modules/receive/?sku=' . urlencode($prod['sku']),
            'icon' => 'fas fa-box'
        ];
    }
    
    // Search in locations
    $locations = $location->searchLocations($search_term);
    foreach($locations as $loc) {
        $status_icon = $loc['status'] === 'ว่าง' ? 'fas fa-circle text-success' : 'fas fa-circle text-danger';
        $results[] = [
            'title' => $loc['location_id'],
            'type' => 'ตำแหน่ง',
            'description' => 'Zone: ' . $loc['zone'] . ' | สถานะ: ' . $loc['status'],
            'extra' => $loc['status'] === 'เก็บสินค้า' ? 'SKU: ' . $loc['sku'] : 'พร้อมใช้งาน',
            'url' => '../modules/movement/?location=' . urlencode($loc['location_id']),
            'icon' => $status_icon
        ];
    }
    
    // Search in transactions by pallet ID or tags ID
    if(preg_match('/^(ATG|REC|PIC|MOV|ONL|PRM|CON|ADJ|RP)/i', $search_term)) {
        $transactions = $transaction->getTransactionHistory([
            'pallet_id' => $search_term,
            'limit' => 10
        ]);
        
        foreach($transactions as $trans) {
            $results[] = [
                'title' => $trans['tags_id'] ?? $trans['pallet_id'],
                'type' => 'รายการเคลื่อนไหว',
                'description' => $trans['ประเภทหลัก'] . ' | SKU: ' . $trans['sku'],
                'extra' => 'จำนวน: ' . number_format($trans['ชิ้น']) . ' ชิ้น | ' . date('d/m/Y H:i', strtotime($trans['created_at'])),
                'url' => '../modules/reports/transactions.php?search=' . urlencode($search_term),
                'icon' => 'fas fa-list-alt'
            ];
        }
    }
    
    // If no results found, try broader search
    if(empty($results)) {
        // Search by barcode
        $barcode_products = $product->getProductsByBarcode($search_term);
        foreach($barcode_products as $prod) {
            $results[] = [
                'title' => $prod['product_name'],
                'type' => 'สินค้า (Barcode)',
                'description' => 'SKU: ' . $prod['sku'] . ' | Barcode: ' . $prod['barcode'],
                'extra' => 'หน่วย: ' . $prod['unit'],
                'url' => '../modules/receive/?sku=' . urlencode($prod['sku']),
                'icon' => 'fas fa-barcode'
            ];
        }
    }
    
    // Log search activity
    $log_entry = date('Y-m-d H:i:s') . " - User: {$_SESSION['user_id']}, Search: $search_term, Results: " . count($results) . "\n";
    file_put_contents('../logs/search.log', $log_entry, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => count($results),
        'search_term' => $search_term
    ]);

} catch(Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการค้นหา'
    ]);
}
?>