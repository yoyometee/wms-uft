<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Product.php';
require_once '../../classes/Location.php';

// Check login
checkLogin();

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);
$product = new Product($db);
$location = new Location($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Get filter parameters
$filter_sku = $_GET['sku'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_zone = $_GET['zone'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_stock_level = $_GET['stock_level'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if($filter_sku) {
    $conditions[] = "p.sku LIKE :sku";
    $params[':sku'] = "%$filter_sku%";
}

if($filter_category) {
    $conditions[] = "p.category = :category";
    $params[':category'] = $filter_category;
}

if($filter_status) {
    $conditions[] = "p.status = :status";
    $params[':status'] = $filter_status;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get stock report data
try {
    $query = "SELECT 
                p.sku,
                p.product_name,
                p.category,
                p.brand,
                p.unit,
                p.น้ำหนัก_ต่อ_ถุง,
                p.จำนวนถุง_ต่อ_แพ็ค,
                p.min_stock,
                p.max_stock,
                p.status,
                COALESCE(p.normal_stock, 0) as normal_stock,
                COALESCE(p.finished_stock, 0) as finished_stock,
                COALESCE(p.total_weight, 0) as total_weight,
                COALESCE(l.location_count, 0) as location_count,
                COALESCE(l.zones, '') as zones
             FROM master_products p
             LEFT JOIN (
                SELECT 
                    sku,
                    COUNT(DISTINCT location_id) as location_count,
                    GROUP_CONCAT(DISTINCT zone ORDER BY zone SEPARATOR ', ') as zones
                FROM msaster_location_by_stock 
                WHERE status = 'เก็บสินค้า' AND sku IS NOT NULL
                GROUP BY sku
             ) l ON p.sku = l.sku
             $where_clause
             ORDER BY p.sku";
    
    $stmt = $db->prepare($query);
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $summary_query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN normal_stock < min_stock THEN 1 ELSE 0 END) as low_stock_count,
                        SUM(CASE WHEN normal_stock = 0 THEN 1 ELSE 0 END) as zero_stock_count,
                        SUM(normal_stock) as total_normal_stock,
                        SUM(finished_stock) as total_finished_stock,
                        SUM(total_weight) as total_weight
                     FROM master_products p
                     $where_clause";
    
    $summary_stmt = $db->prepare($query);
    foreach($params as $key => $value) {
        $summary_stmt->bindValue($key, $value);
    }
    $summary_stmt->execute();
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    error_log("Stock report error: " . $e->getMessage());
    $stock_data = [];
    $summary = [
        'total_products' => 0,
        'low_stock_count' => 0,
        'zero_stock_count' => 0,
        'total_normal_stock' => 0,
        'total_finished_stock' => 0,
        'total_weight' => 0
    ];
}

// Get filter options
$categories = $product->getCategories();
$zones = $location->getZones();

$page_title = 'รายงานสต็อกสินค้า';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-chart-bar"></i> รายงานสต็อกสินค้า</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="../" class="text-white">รายงาน</a></li>
                                    <li class="breadcrumb-item active text-white">รายงานสต็อก</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="text-end">
                            <div class="h5 mb-0"><?php echo formatDate(date('Y-m-d H:i:s')); ?></div>
                            <small>ผู้ใช้งาน: <?php echo htmlspecialchars($current_user['ชื่อ_สกุล']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สินค้าทั้งหมด</h6>
                            <h3 class="text-primary"><?php echo formatNumber($summary['total_products']); ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-box fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สต็อกต่ำ</h6>
                            <h3 class="text-danger"><?php echo formatNumber($summary['low_stock_count']); ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สต็อกหมด</h6>
                            <h3 class="text-warning"><?php echo formatNumber($summary['zero_stock_count']); ?></h3>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สต็อกปกติ</h6>
                            <h3 class="text-success"><?php echo formatNumber($summary['total_normal_stock']); ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-cube fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">สต็อกแล้ว</h6>
                            <h3 class="text-info"><?php echo formatNumber($summary['total_finished_stock']); ?></h3>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-cubes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">น้ำหนักรวม</h6>
                            <h3 class="text-secondary"><?php echo formatWeight($summary['total_weight']); ?></h3>
                        </div>
                        <div class="text-secondary">
                            <i class="fas fa-weight fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-filter"></i> ตัวกรอง</h6>
                </div>
                <div class="card-body">
                    <form method="GET" id="filterForm">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($filter_sku); ?>" placeholder="ค้นหา SKU">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">หมวดหมู่</label>
                                <select name="category" class="form-select">
                                    <option value="">ทุกหมวดหมู่</option>
                                    <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">สถานะ</label>
                                <select name="status" class="form-select">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="ใช้งาน" <?php echo $filter_status === 'ใช้งาน' ? 'selected' : ''; ?>>ใช้งาน</option>
                                    <option value="ระงับ" <?php echo $filter_status === 'ระงับ' ? 'selected' : ''; ?>>ระงับ</option>
                                    <option value="หมด" <?php echo $filter_status === 'หมด' ? 'selected' : ''; ?>>หมด</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">ระดับสต็อก</label>
                                <select name="stock_level" class="form-select">
                                    <option value="">ทุกระดับ</option>
                                    <option value="low" <?php echo $filter_stock_level === 'low' ? 'selected' : ''; ?>>สต็อกต่ำ</option>
                                    <option value="zero" <?php echo $filter_stock_level === 'zero' ? 'selected' : ''; ?>>สต็อกหมด</option>
                                    <option value="normal" <?php echo $filter_stock_level === 'normal' ? 'selected' : ''; ?>>สต็อกปกติ</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                            <a href="stock.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> ล้างตัวกรอง
                            </a>
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="printReport()">
                                <i class="fas fa-print"></i> พิมพ์
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Report Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายงานสต็อกสินค้า</h5>
                    <div class="text-muted">
                        ทั้งหมด <?php echo formatNumber(count($stock_data)); ?> รายการ
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="stockTable">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>ชื่อสินค้า</th>
                                    <th>หมวดหมู่</th>
                                    <th>หน่วย</th>
                                    <th>สต็อกปกติ</th>
                                    <th>สต็อกแล้ว</th>
                                    <th>น้ำหนักรวม</th>
                                    <th>จำนวนตำแหน่ง</th>
                                    <th>Zone</th>
                                    <th>สถานะ</th>
                                    <th>ระดับสต็อก</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stock_data as $item): ?>
                                <?php
                                $stock_level = 'normal';
                                $stock_class = 'text-success';
                                $stock_icon = 'fa-check-circle';
                                
                                if($item['normal_stock'] == 0) {
                                    $stock_level = 'หมด';
                                    $stock_class = 'text-warning';
                                    $stock_icon = 'fa-times-circle';
                                } elseif($item['normal_stock'] < $item['min_stock'] && $item['min_stock'] > 0) {
                                    $stock_level = 'ต่ำ';
                                    $stock_class = 'text-danger';
                                    $stock_icon = 'fa-exclamation-triangle';
                                } else {
                                    $stock_level = 'ปกติ';
                                }
                                
                                // Apply stock level filter
                                if($filter_stock_level) {
                                    if($filter_stock_level === 'zero' && $item['normal_stock'] > 0) continue;
                                    if($filter_stock_level === 'low' && ($item['normal_stock'] >= $item['min_stock'] || $item['min_stock'] == 0)) continue;
                                    if($filter_stock_level === 'normal' && ($item['normal_stock'] == 0 || ($item['normal_stock'] < $item['min_stock'] && $item['min_stock'] > 0))) continue;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['sku']); ?></strong>
                                        <?php if($item['brand']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td>
                                        <?php if($item['category']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td>
                                        <strong class="<?php echo $stock_class; ?>">
                                            <?php echo formatNumber($item['normal_stock']); ?>
                                        </strong>
                                        <?php if($item['min_stock'] > 0): ?>
                                        <br><small class="text-muted">ขั้นต่ำ: <?php echo formatNumber($item['min_stock']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-info"><?php echo formatNumber($item['finished_stock']); ?></strong>
                                    </td>
                                    <td><?php echo formatWeight($item['total_weight']); ?></td>
                                    <td>
                                        <?php if($item['location_count'] > 0): ?>
                                        <span class="badge bg-primary"><?php echo $item['location_count']; ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($item['zones']): ?>
                                        <small><?php echo htmlspecialchars($item['zones']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'ใช้งาน' => 'success',
                                            'ระงับ' => 'danger',
                                            'หมด' => 'secondary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_colors[$item['status']] ?? 'secondary'; ?>">
                                            <?php echo $item['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $stock_class; ?>">
                                            <i class="fas <?php echo $stock_icon; ?>"></i> <?php echo $stock_level; ?>
                                        </span>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#stockTable').DataTable({
        order: [[0, 'asc']], // Sort by SKU
        pageLength: 50,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});

function exportToExcel() {
    // Create Excel export
    const table = document.getElementById('stockTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Stock Report"});
    XLSX.writeFile(wb, `stock_report_${new Date().toISOString().split('T')[0]}.xlsx`);
}

function printReport() {
    window.print();
}
</script>

<style media="print">
.container-fluid {
    max-width: 100% !important;
}
.card {
    border: none !important;
    box-shadow: none !important;
}
.btn, .breadcrumb, .card-header {
    display: none !important;
}
</style>

<?php include '../../includes/footer.php'; ?>