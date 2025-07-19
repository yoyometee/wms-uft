<?php
session_start();

// Include configuration and classes
require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Product.php';
require_once 'classes/Location.php';
require_once 'classes/Transaction.php';

// Check if user is logged in
checkLogin();

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize classes
$user = new User($db);
$product = new Product($db);
$location = new Location($db);
$transaction = new Transaction($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: logout.php');
    exit;
}

// Get dashboard data with error handling
try {
    $stock_summary = $product->getStockSummary();
} catch(Exception $e) {
    error_log("Error getting stock summary: " . $e->getMessage());
    $stock_summary = ['total_skus' => 0, 'total_stock' => 0, 'total_value' => 0];
}

try {
    $location_utilization = $location->getLocationUtilization();
} catch(Exception $e) {
    error_log("Error getting location utilization: " . $e->getMessage());
    $location_utilization = [];
}

try {
    $recent_transactions = $transaction->getRecentTransactions(10);
    
    // Additional validation for data integrity
    if(!is_array($recent_transactions)) {
        error_log("Warning: getRecentTransactions() did not return an array");
        $recent_transactions = [];
    }
    
    // If no real data, create sample data for testing (remove in production)
    if(empty($recent_transactions) && defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        $recent_transactions = [
            [
                'created_at' => date('Y-m-d H:i:s'),
                'ประเภทหลัก' => 'เบิก',
                'sku' => 'SAMPLE001',
                'product_name' => 'ตัวอย่างสินค้า',
                'pallet_id' => 'PL001',
                'location_id' => 'A1-01',
                'ชิ้น' => 10,
                'น้ำหนัก' => 5.5,
                'name_edit' => 'Admin'
            ]
        ];
        error_log("Using sample transaction data for testing");
    }
    
    error_log("Final recent_transactions count: " . count($recent_transactions));
    
} catch(Exception $e) {
    error_log("Error getting recent transactions: " . $e->getMessage());
    $recent_transactions = [];
}

try {
    $low_stock_products = $product->getLowStockProducts();
} catch(Exception $e) {
    error_log("Error getting low stock products: " . $e->getMessage());
    $low_stock_products = [];
}

try {
    $expiring_soon = $location->getExpiringSoon(30);
} catch(Exception $e) {
    error_log("Error getting expiring products: " . $e->getMessage());
    $expiring_soon = [];
}

$page_title = 'หน้าหลัก';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h1><i class="fas fa-warehouse"></i> <?php echo APP_NAME; ?></h1>
                    <p class="mb-0">ระบบจัดการคลังสินค้า - สุขภาพดีย์กิน!</p>
                    <small>ผู้ใช้งาน: <?php echo htmlspecialchars($current_user['ชื่อ_สกุล']); ?> | 
                           วันที่: <?php echo formatDate(date('Y-m-d H:i:s')); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">สินค้าทั้งหมด</h5>
                            <h2 class="mb-0"><?php echo formatNumber($stock_summary['total_products'] ?? 0); ?></h2>
                            <small>รายการสินค้า</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-box fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">ตำแหน่งว่าง</h5>
                            <h2 class="mb-0" id="available-locations">
                                <?php 
                                $total_available = 0;
                                foreach($location_utilization as $zone) {
                                    $total_available += $zone['available_locations'];
                                }
                                echo formatNumber($total_available);
                                ?>
                            </h2>
                            <small>ตำแหน่งที่ใช้งานได้</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-map-marker-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">สินค้าใกล้หมดสต็อก</h5>
                            <h2 class="mb-0"><?php echo formatNumber(count($low_stock_products)); ?></h2>
                            <small>รายการที่ต้องดูแล</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">สินค้าใกล้หมดอายุ</h5>
                            <h2 class="mb-0"><?php echo formatNumber(count($expiring_soon)); ?></h2>
                            <small>ภายใน 30 วัน</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-calendar-times fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Menu -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-th-large"></i> การดำเนินการหลัก</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/receive/" class="btn btn-primary btn-menu w-100 h-100">
                                <i class="fas fa-truck-loading fa-2x mb-2"></i>
                                <div class="h6">รับสินค้า</div>
                                <small>Receive</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/picking/" class="btn btn-success btn-menu w-100 h-100">
                                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                                <div class="h6">จัดเตรียมสินค้า</div>
                                <small>Picking</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/movement/" class="btn btn-warning btn-menu w-100 h-100">
                                <i class="fas fa-exchange-alt fa-2x mb-2"></i>
                                <div class="h6">ย้ายสินค้า</div>
                                <small>Movement</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/inventory/" class="btn btn-info btn-menu w-100 h-100">
                                <i class="fas fa-adjust fa-2x mb-2"></i>
                                <div class="h6">ปรับสต็อก</div>
                                <small>Adjustment</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/reports/" class="btn btn-secondary btn-menu w-100 h-100">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <div class="h6">รายงาน</div>
                                <small>Reports</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/conversion/" class="btn btn-purple btn-menu w-100 h-100">
                                <i class="fas fa-recycle fa-2x mb-2"></i>
                                <div class="h6">การแปลง</div>
                                <small>Conversion</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/premium/" class="btn btn-gold btn-menu w-100 h-100">
                                <i class="fas fa-star fa-2x mb-2"></i>
                                <div class="h6">Premium</div>
                                <small>Premium Items</small>
                            </a>
                        </div>
                        <?php if($current_user['role'] === 'admin'): ?>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="modules/admin/" class="btn btn-dark btn-menu w-100 h-100">
                                <i class="fas fa-cog fa-2x mb-2"></i>
                                <div class="h6">จัดการระบบ</div>
                                <small>Admin</small>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Location Utilization Chart -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-pie"></i> การใช้งานตำแหน่ง</h6>
                </div>
                <div class="card-body">
                    <canvas id="locationChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                                <i class="fas fa-list-alt"></i> รายการเคลื่อนไหวล่าสุด
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">
                                <i class="fas fa-exclamation-triangle"></i> สินค้าใกล้หมดสต็อก
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="expiry-tab" data-bs-toggle="tab" data-bs-target="#expiry" type="button" role="tab">
                                <i class="fas fa-calendar-times"></i> สินค้าใกล้หมดอายุ
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="zones-tab" data-bs-toggle="tab" data-bs-target="#zones" type="button" role="tab">
                                <i class="fas fa-map-marker-alt"></i> สถานะตำแหน่ง
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="dashboardTabsContent">
                        <!-- Recent Transactions Tab -->
                        <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="recentTransactions">
                                    <thead>
                                        <tr>
                                            <th>เวลา</th>
                                            <th>ประเภท</th>
                                            <th>SKU</th>
                                            <th>สินค้า</th>
                                            <th>Pallet ID</th>
                                            <th>Location</th>
                                            <th>จำนวน</th>
                                            <th>ผู้ดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Debug: Check transaction data structure
                                        if(is_array($recent_transactions) && !empty($recent_transactions)) {
                                            $debug_trans = array_slice($recent_transactions, 0, 1); // Get first transaction for debug
                                            error_log("Debug recentTransactions data: " . print_r($debug_trans, true));
                                        }
                                        ?>
                                        <?php if(!empty($recent_transactions) && is_array($recent_transactions)): ?>
                                            <?php foreach($recent_transactions as $index => $trans): ?>
                                                <?php 
                                                // Validate transaction data structure
                                                if(!is_array($trans)) {
                                                    error_log("Warning: Transaction at index {$index} is not an array");
                                                    continue;
                                                }
                                                ?>
                                            <tr>
                                                <td><?php echo formatDate($trans['created_at'] ?? ''); ?></td>
                                                <td>
                                                    <?php if(!empty($trans['ประเภทหลัก'])): ?>
                                                    <span class="badge bg-<?php echo getTransactionTypeColor($trans['ประเภทหลัก']); ?>">
                                                        <?php echo htmlspecialchars($trans['ประเภทหลัก']); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($trans['sku'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($trans['product_name'] ?? '-'); ?></td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($trans['pallet_id'] ?? '-'); ?></code>
                                                </td>
                                                <td><?php echo htmlspecialchars($trans['location_id'] ?? '-'); ?></td>
                                                <td>
                                                    <?php echo formatNumber($trans['ชิ้น'] ?? 0); ?> ชิ้น<br>
                                                    <small><?php echo formatWeight($trans['น้ำหนัก'] ?? 0); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($trans['name_edit'] ?? '-'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">ไม่มีรายการเคลื่อนไหว</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Low Stock Tab -->
                        <div class="tab-pane fade" id="stock" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="lowStockTable">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>ชื่อสินค้า</th>
                                            <th>สต็อกปัจจุบัน</th>
                                            <th>สต็อกขั้นต่ำ</th>
                                            <th>สถานะ</th>
                                            <th>การดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($low_stock_products)): ?>
                                            <?php foreach($low_stock_products as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo formatNumber($product['จำนวนถุง_ปกติ']); ?></td>
                                                <td><?php echo formatNumber($product['min_stock']); ?></td>
                                                <td>
                                                    <span class="badge bg-danger">ต่ำกว่าขั้นต่ำ</span>
                                                </td>
                                                <td>
                                                    <a href="modules/receive/?sku=<?php echo urlencode($product['sku']); ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus"></i> รับสินค้า
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">ไม่มีสินค้าใกล้หมดสต็อก</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Expiring Soon Tab -->
                        <div class="tab-pane fade" id="expiry" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="expiringTable">
                                    <thead>
                                        <tr>
                                            <th>Location</th>
                                            <th>SKU</th>
                                            <th>ชื่อสินค้า</th>
                                            <th>Pallet ID</th>
                                            <th>จำนวน</th>
                                            <th>วันหมดอายุ</th>
                                            <th>สถานะ</th>
                                            <th>การดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($expiring_soon)): ?>
                                            <?php foreach($expiring_soon as $item): ?>
                                            <?php $expiry_status = calculateExpiryStatus($item['expiration_date']); ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['location_id']); ?></td>
                                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($item['pallet_id']); ?></code>
                                                </td>
                                                <td>
                                                    <?php echo formatNumber($item['ชิ้น']); ?> ชิ้น<br>
                                                    <small><?php echo formatWeight($item['น้ำหนัก']); ?></small>
                                                </td>
                                                <td><?php echo formatDate($item['expiration_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo str_replace('text-', '', $expiry_status['class']); ?>">
                                                        <?php echo $expiry_status['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="modules/picking/?location=<?php echo urlencode($item['location_id']); ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-hand-paper"></i> เบิกก่อน
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">ไม่มีสินค้าใกล้หมดอายุ</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Zone Status Tab -->
                        <div class="tab-pane fade" id="zones" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="zoneTable">
                                    <thead>
                                        <tr>
                                            <th>Zone</th>
                                            <th>ตำแหน่งทั้งหมด</th>
                                            <th>ที่ใช้งาน</th>
                                            <th>ที่ว่าง</th>
                                            <th>% การใช้งาน</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($location_utilization as $zone): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo getZoneColor($zone['zone']); ?>">
                                                    <?php echo htmlspecialchars($zone['zone']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatNumber($zone['total_locations']); ?></td>
                                            <td><?php echo formatNumber($zone['occupied_locations']); ?></td>
                                            <td><?php echo formatNumber($zone['available_locations']); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $zone['utilization_percent']; ?>%" 
                                                         aria-valuenow="<?php echo $zone['utilization_percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $zone['utilization_percent']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($zone['utilization_percent'] > 90): ?>
                                                    <span class="badge bg-danger">เต็มเกือบหมด</span>
                                                <?php elseif($zone['utilization_percent'] > 70): ?>
                                                    <span class="badge bg-warning">ใช้งานสูง</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">ปกติ</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart.js for Location Utilization
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('locationChart').getContext('2d');
    const locationData = <?php echo json_encode($location_utilization); ?>;
    
    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: locationData.map(zone => zone.zone),
            datasets: [{
                data: locationData.map(zone => zone.utilization_percent),
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d',
                    '#17a2b8',
                    '#6f42c1',
                    '#fd7e14'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Initialize DataTables with comprehensive error handling
    if($('#recentTransactions').length > 0) {
        try {
            // Validate table structure before initializing DataTable
            const table = document.getElementById('recentTransactions');
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            
            if(!thead || !tbody) {
                throw new Error('Table missing thead or tbody elements');
            }
            
            const headerRow = thead.querySelector('tr');
            if(!headerRow) {
                throw new Error('Table missing header row');
            }
            
            const headerCols = headerRow.children.length;
            const bodyRows = tbody.querySelectorAll('tr');
            
            console.log('Table validation:', {
                headerCols: headerCols,
                bodyRowsCount: bodyRows.length,
                expectedCols: 8
            });
            
            if(headerCols !== 8) {
                throw new Error(`Expected 8 header columns, found ${headerCols}`);
            }
            
            let validStructure = true;
            let invalidRows = [];
            
            bodyRows.forEach((row, index) => {
                const rowCols = row.children.length;
                const hasColspan = row.querySelector('td[colspan]');
                
                if(rowCols !== headerCols && !hasColspan) {
                    validStructure = false;
                    invalidRows.push({
                        index: index,
                        cols: rowCols,
                        expected: headerCols
                    });
                    console.warn(`Row ${index}: Found ${rowCols} columns, expected ${headerCols}`);
                }
            });
            
            if(!validStructure) {
                console.error('Table structure validation failed:', invalidRows);
                $('#recentTransactions').closest('.table-responsive').html(
                    '<div class="alert alert-warning">' +
                    '<strong>โครงสร้างตารางไม่ถูกต้อง:</strong><br>' +
                    'พบแถวที่มีจำนวนคอลัมน์ไม่ถูกต้อง ' + invalidRows.length + ' แถว<br>' +
                    'กรุณาตรวจสอบข้อมูลในฐานข้อมูล' +
                    '</div>'
                );
                return;
            }
            
            // Initialize DataTable if validation passes
            $('#recentTransactions').DataTable({
                order: [[0, 'desc']],
                pageLength: 10,
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
                },
                columnDefs: [
                    { targets: '_all', defaultContent: '-' },
                    { targets: [0], type: 'date' },
                    { targets: [6], orderable: false }
                ],
                drawCallback: function() {
                    console.log('DataTable drawn successfully');
                }
            });
            
            console.log('recentTransactions DataTable initialized successfully');
            
        } catch(error) {
            console.error('Error initializing recentTransactions DataTable:', error);
            $('#recentTransactions').closest('.table-responsive').html(
                '<div class="alert alert-danger">' +
                '<strong>เกิดข้อผิดพลาด:</strong> ' + error.message +
                '<br><small>กรุณาตรวจสอบ Console สำหรับรายละเอียดเพิ่มเติม</small>' +
                '</div>'
            );
        }
    }
    
    try {
        $('#lowStockTable').DataTable({
            order: [[2, 'asc']],
            pageLength: 10,
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
            },
            columnDefs: [
                { targets: '_all', defaultContent: '-' }
            ]
        });
    } catch(error) {
        console.error('Error initializing lowStockTable DataTable:', error);
    }
    
    try {
        $('#expiringTable').DataTable({
            order: [[5, 'asc']],
            pageLength: 10,
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
            },
            columnDefs: [
                { targets: '_all', defaultContent: '-' }
            ]
        });
    } catch(error) {
        console.error('Error initializing expiringTable DataTable:', error);
    }
    
    try {
        $('#zoneTable').DataTable({
            order: [[4, 'desc']],
            pageLength: 10,
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
            },
            columnDefs: [
                { targets: '_all', defaultContent: '-' }
            ]
        });
    } catch(error) {
        console.error('Error initializing zoneTable DataTable:', error);
    }
});

// Refresh data function
function refreshData() {
    location.reload();
}

// Auto-refresh every 5 minutes
setInterval(refreshData, 300000);
</script>

<style>
.btn-menu {
    min-height: 120px;
    text-align: center;
    padding: 20px 10px;
    transition: all 0.3s ease;
}

.btn-menu:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5a32a3;
    border-color: #5a32a3;
    color: white;
}

.btn-gold {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-gold:hover {
    background-color: #e0a800;
    border-color: #e0a800;
    color: #212529;
}

.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}

.progress {
    height: 20px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.fab-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.fab {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    margin-bottom: 10px;
    display: block;
    transition: all 0.3s ease;
}

.fab-main {
    background-color: #007bff;
}

.fab-secondary {
    background-color: #6c757d;
}

.fab:hover {
    transform: scale(1.1);
}

#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
</style>

<?php include 'includes/footer.php'; ?>