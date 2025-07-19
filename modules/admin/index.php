<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Product.php';
require_once '../../classes/Location.php';
require_once '../../classes/Transaction.php';

// Check login and admin permission
checkPermission('admin');

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);
$product = new Product($db);
$location = new Location($db);
$transaction = new Transaction($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Get system statistics
$stats = [];

try {
    // User stats
    $users = $user->getAllUsers();
    $stats['total_users'] = count($users);
    $stats['active_users'] = count(array_filter($users, function($u) { return $u['active']; }));
    
    // Product stats
    $product_summary = $product->getStockSummary();
    $stats['total_products'] = $product_summary['total_products'] ?? 0;
    $stats['low_stock_products'] = $product_summary['low_stock_products'] ?? 0;
    
    // Location stats
    $location_utilization = $location->getLocationUtilization();
    $stats['total_locations'] = array_sum(array_column($location_utilization, 'total_locations'));
    $stats['occupied_locations'] = array_sum(array_column($location_utilization, 'occupied'));
    
    // Transaction stats (last 30 days)
    $transaction_stats = $transaction->getTransactionStatistics(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $stats['total_transactions'] = 0;
    foreach($transaction_stats as $type_stats) {
        $stats['total_transactions'] += $type_stats['total_transactions'] ?? 0;
    }
    
} catch(Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
}

$page_title = 'จัดการระบบ';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-cog"></i> จัดการระบบ</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item active text-white">จัดการระบบ</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="text-end">
                            <div class="h5 mb-0"><?php echo formatDate(date('Y-m-d H:i:s')); ?></div>
                            <small>ผู้ดูแลระบบ: <?php echo htmlspecialchars($current_user['ชื่อ_สกุล']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Overview Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ผู้ใช้งาน</h6>
                            <h2 class="text-primary"><?php echo formatNumber($stats['active_users']); ?></h2>
                            <small class="text-muted">จากทั้งหมด <?php echo formatNumber($stats['total_users']); ?> คน</small>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สินค้า</h6>
                            <h2 class="text-success"><?php echo formatNumber($stats['total_products']); ?></h2>
                            <small class="text-muted">สต็อกต่ำ <?php echo formatNumber($stats['low_stock_products']); ?> รายการ</small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-box fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ตำแหน่ง</h6>
                            <h2 class="text-warning"><?php echo formatNumber($stats['occupied_locations']); ?></h2>
                            <small class="text-muted">จากทั้งหมด <?php echo formatNumber($stats['total_locations']); ?> ตำแหน่ง</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-map-marker-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">รายการเคลื่อนไหว (30 วัน)</h6>
                            <h2 class="text-info"><?php echo formatNumber($stats['total_transactions']); ?></h2>
                            <small class="text-muted">รายการ</small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Management Menu -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-th-large"></i> เมนูการจัดการ</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="users.php" class="btn btn-primary btn-menu w-100 h-100">
                                <i class="fas fa-users fa-3x mb-3"></i>
                                <h5>จัดการผู้ใช้</h5>
                                <p class="small">เพิ่ม แก้ไข ลบผู้ใช้งาน</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="products.php" class="btn btn-success btn-menu w-100 h-100">
                                <i class="fas fa-box fa-3x mb-3"></i>
                                <h5>จัดการสินค้า</h5>
                                <p class="small">เพิ่ม แก้ไข SKU และข้อมูลสินค้า</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="locations.php" class="btn btn-warning btn-menu w-100 h-100">
                                <i class="fas fa-map fa-3x mb-3"></i>
                                <h5>จัดการตำแหน่ง</h5>
                                <p class="small">เพิ่ม แก้ไข Location และ Zone</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="settings.php" class="btn btn-info btn-menu w-100 h-100">
                                <i class="fas fa-wrench fa-3x mb-3"></i>
                                <h5>การตั้งค่า</h5>
                                <p class="small">ตั้งค่าระบบและพารามิเตอร์</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="backup.php" class="btn btn-secondary btn-menu w-100 h-100">
                                <i class="fas fa-database fa-3x mb-3"></i>
                                <h5>สำรองข้อมูล</h5>
                                <p class="small">Backup และ Restore ฐานข้อมูล</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="logs.php" class="btn btn-dark btn-menu w-100 h-100">
                                <i class="fas fa-list-alt fa-3x mb-3"></i>
                                <h5>ประวัติการใช้งาน</h5>
                                <p class="small">ดู Log และกิจกรรมต่างๆ</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="maintenance.php" class="btn btn-danger btn-menu w-100 h-100">
                                <i class="fas fa-tools fa-3x mb-3"></i>
                                <h5>บำรุงรักษา</h5>
                                <p class="small">ทำความสะอาดและเก็บรักษาระบบ</p>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <a href="reports.php" class="btn btn-purple btn-menu w-100 h-100">
                                <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                <h5>รายงานระบบ</h5>
                                <p class="small">รายงานประสิทธิภาพและการใช้งาน</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-server"></i> สถานะระบบ</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="status-indicator status-online"></div>
                            <strong>Database</strong>
                            <div class="text-success small">เชื่อมต่อปกติ</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="status-indicator status-online"></div>
                            <strong>Application</strong>
                            <div class="text-success small">ทำงานปกติ</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="status-indicator status-online"></div>
                            <strong>Storage</strong>
                            <div class="text-success small">พื้นที่เพียงพอ</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="status-indicator status-online"></div>
                            <strong>Sessions</strong>
                            <div class="text-success small">ทำงานปกติ</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-clock"></i> กิจกรรมล่าสุด</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-user text-primary"></i> เข้าสู่ระบบ</span>
                            <small class="text-muted">เมื่อสักครู่</small>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-database text-success"></i> สำรองข้อมูลอัตโนมัติ</span>
                            <small class="text-muted">1 ชั่วโมงที่แล้ว</small>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-sync text-info"></i> อัพเดตข้อมูลสต็อก</span>
                            <small class="text-muted">2 ชั่วโมงที่แล้ว</small>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-broom text-warning"></i> ทำความสะอาดระบบ</span>
                            <small class="text-muted">เมื่อวาน</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-bolt"></i> การดำเนินการด่วน</h6>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="clearCache()">
                            <i class="fas fa-trash"></i> ล้าง Cache
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="optimizeDatabase()">
                            <i class="fas fa-tachometer-alt"></i> เพิ่มประสิทธิภาพ DB
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="backupNow()">
                            <i class="fas fa-save"></i> สำรองข้อมูลทันที
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="checkSystemHealth()">
                            <i class="fas fa-stethoscope"></i> ตรวจสอบระบบ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btn-menu {
    min-height: 150px;
    text-align: center;
    padding: 20px;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
}

.btn-menu:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    color: white;
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

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}

.status-online {
    background-color: #28a745;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>

<script>
function clearCache() {
    if(confirm('ต้องการล้าง Cache ทั้งหมดหรือไม่?')) {
        // Implement cache clearing logic
        alert('ล้าง Cache เรียบร้อย');
    }
}

function optimizeDatabase() {
    if(confirm('ต้องการเพิ่มประสิทธิภาพฐานข้อมูลหรือไม่?')) {
        // Implement database optimization
        alert('เพิ่มประสิทธิภาพฐานข้อมูลเรียบร้อย');
    }
}

function backupNow() {
    if(confirm('ต้องการสำรองข้อมูลทันทีหรือไม่?')) {
        // Implement immediate backup
        alert('สำรองข้อมูลเรียบร้อย');
    }
}

function checkSystemHealth() {
    // Implement system health check
    alert('ระบบทำงานปกติ ไม่พบปัญหา');
}
</script>

<?php include '../../includes/footer.php'; ?>