<?php
// File: index.php
require_once 'config/app_config.php';
require_once 'includes/master_layout.php';

// Check if user is logged in
checkLogin();

// Get database connection
$db = getDBConnection();

// Get dashboard statistics
try {
    // Total products
    $stmt = $db->query("SELECT COUNT(*) as count FROM master_products");
    $total_products = $stmt->fetch()['count'] ?? 0;
    
    // Total locations
    $stmt = $db->query("SELECT COUNT(*) as count FROM msaster_location_by_stock");
    $total_locations = $stmt->fetch()['count'] ?? 0;
    
    // Available locations
    $stmt = $db->query("SELECT COUNT(*) as count FROM msaster_location_by_stock WHERE status = 'ว่าง'");
    $available_locations = $stmt->fetch()['count'] ?? 0;
    
    // Low stock items
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM master_products 
        WHERE จำนวนถุง_ปกติ <= min_stock AND min_stock > 0
    ");
    $low_stock_items = $stmt->fetch()['count'] ?? 0;
    
    // Items expiring in 30 days
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM msaster_location_by_stock 
        WHERE expiration_date IS NOT NULL 
        AND expiration_date > 0 
        AND expiration_date <= UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $expiring_items = $stmt->fetch()['count'] ?? 0;
    
    // Today's transactions
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM transaction_product_flow 
        WHERE DATE(created_at) = CURDATE()
    ");
    $today_transactions = $stmt->fetch()['count'] ?? 0;
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $total_products = $total_locations = $available_locations = $low_stock_items = $expiring_items = $today_transactions = 0;
}

// Start output buffering for content
ob_start();
?>

<div class="container-fluid mt-4">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary-gradient text-white">
                <div class="card-body text-center py-4">
                    <h1><i class="fas fa-warehouse fa-2x mb-3"></i></h1>
                    <h2><?php echo APP_NAME; ?></h2>
                    <p class="mb-0 fs-5">ระบบจัดการคลังสินค้า - สุขภาพดีย์กิน!</p>
                    <small class="opacity-75">
                        ผู้ใช้งาน: <?php echo htmlspecialchars($_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'] ?? 'ผู้ใช้งาน'); ?> | 
                        วันที่: <?php echo formatDateTime(date('Y-m-d H:i:s')); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card bg-success-gradient">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">สินค้าทั้งหมด</h5>
                        <h2><?php echo formatNumber($total_products); ?></h2>
                        <small>รายการสินค้า</small>
                    </div>
                    <div>
                        <i class="fas fa-box fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card bg-info-gradient">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">ตำแหน่งว่าง</h5>
                        <h2><?php echo formatNumber($available_locations); ?></h2>
                        <small>จาก <?php echo formatNumber($total_locations); ?> ตำแหน่ง</small>
                    </div>
                    <div>
                        <i class="fas fa-map-marker-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card bg-warning-gradient">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">สต็อกต่ำ</h5>
                        <h2><?php echo formatNumber($low_stock_items); ?></h2>
                        <small>รายการที่ต้องดูแล</small>
                    </div>
                    <div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card bg-danger-gradient">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">ใกล้หมดอายุ</h5>
                        <h2><?php echo formatNumber($expiring_items); ?></h2>
                        <small>ภายใน 30 วัน</small>
                    </div>
                    <div>
                        <i class="fas fa-calendar-times fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">รายการวันนี้</h5>
                        <h2><?php echo formatNumber($today_transactions); ?></h2>
                        <small>ธุรกรรม</small>
                    </div>
                    <div>
                        <i class="fas fa-list-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">ใช้งานตำแหน่ง</h5>
                        <h2><?php echo $total_locations > 0 ? number_format((($total_locations - $available_locations) / $total_locations) * 100, 1) : 0; ?>%</h2>
                        <small>อัตราการใช้งาน</small>
                    </div>
                    <div>
                        <i class="fas fa-chart-pie fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Operations Menu -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-th-large"></i> การดำเนินการหลัก</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/receive/" class="menu-card bg-success-gradient text-decoration-none">
                                <div class="text-center">
                                    <i class="fas fa-truck-loading fa-3x mb-2"></i>
                                    <div class="h5">รับสินค้า</div>
                                    <small>Receive Products</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/picking/" class="menu-card bg-primary-gradient text-decoration-none">
                                <div class="text-center">
                                    <i class="fas fa-hand-paper fa-3x mb-2"></i>
                                    <div class="h5">จัดเตรียมสินค้า</div>
                                    <small>Order Picking</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/movement/" class="menu-card bg-warning-gradient text-decoration-none">
                                <div class="text-center">
                                    <i class="fas fa-exchange-alt fa-3x mb-2"></i>
                                    <div class="h5">ย้ายสินค้า</div>
                                    <small>Stock Movement</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/inventory/" class="menu-card bg-info-gradient text-decoration-none">
                                <div class="text-center">
                                    <i class="fas fa-clipboard-check fa-3x mb-2"></i>
                                    <div class="h5">ปรับสต็อก</div>
                                    <small>Stock Adjustment</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Functions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-tools"></i> ฟังก์ชันเพิ่มเติม</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/conversion/" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-exchange-alt d-block mb-2"></i>
                                <strong>แปลงสินค้า</strong><br>
                                <small>Conversion</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/premium/" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-star d-block mb-2"></i>
                                <strong>สินค้าพรีเมียม</strong><br>
                                <small>Premium</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/reorder/" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-redo d-block mb-2"></i>
                                <strong>สั่งซื้อใหม่</strong><br>
                                <small>Reorder</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/cycle-count/" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-calculator d-block mb-2"></i>
                                <strong>นับสต็อก</strong><br>
                                <small>Cycle Count</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/analytics/" class="btn btn-outline-danger w-100 py-3">
                                <i class="fas fa-chart-line d-block mb-2"></i>
                                <strong>Analytics</strong><br>
                                <small>วิเคราะห์ข้อมูล</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="modules/ai/" class="btn btn-outline-dark w-100 py-3">
                                <i class="fas fa-robot d-block mb-2"></i>
                                <strong>AI Assistant</strong><br>
                                <small>ผู้ช่วย AI</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> รายงานและการวิเคราะห์</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <a href="modules/reports/" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-alt d-block mb-1"></i>
                                <strong>รายงาน</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/reports/stock.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-boxes d-block mb-1"></i>
                                <strong>รายงานสต็อก</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/reports/transactions.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-list d-block mb-1"></i>
                                <strong>รายการเคลื่อนไหว</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/reports/locations.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-map d-block mb-1"></i>
                                <strong>รายงานตำแหน่ง</strong>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-cogs"></i> การจัดการระบบ</h6>
                </div>
                <div class="card-body">
                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <a href="modules/admin/users.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-users d-block mb-1"></i>
                                <strong>จัดการผู้ใช้</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/admin/products.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-box d-block mb-1"></i>
                                <strong>จัดการสินค้า</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/admin/locations.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-map-marker-alt d-block mb-1"></i>
                                <strong>จัดการตำแหน่ง</strong>
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="modules/admin/" class="btn btn-outline-dark w-100">
                                <i class="fas fa-cog d-block mb-1"></i>
                                <strong>ตั้งค่าระบบ</strong>
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                        <p class="text-muted">ต้องเป็น Administrator เพื่อเข้าถึงส่วนนี้</p>
                        <small class="text-muted">
                            สิทธิ์ปัจจุบัน: <span class="badge bg-secondary"><?php echo ucfirst($_SESSION['role'] ?? 'Unknown'); ?></span>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
renderMasterLayout($content, 'หน้าหลัก');
?>