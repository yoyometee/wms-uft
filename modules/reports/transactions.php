<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Transaction.php';

// Check login
checkLogin();

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);
$transaction = new Transaction($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_sub_type = $_GET['sub_type'] ?? '';
$filter_sku = $_GET['sku'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query conditions
$conditions = ["DATE(FROM_UNIXTIME(timestamp)) BETWEEN :date_from AND :date_to"];
$params = [
    ':date_from' => $filter_date_from,
    ':date_to' => $filter_date_to
];

if($filter_type) {
    $conditions[] = "type = :type";
    $params[':type'] = $filter_type;
}

if($filter_sub_type) {
    $conditions[] = "sub_type = :sub_type";
    $params[':sub_type'] = $filter_sub_type;
}

if($filter_sku) {
    $conditions[] = "sku LIKE :sku";
    $params[':sku'] = "%$filter_sku%";
}

if($filter_user) {
    $conditions[] = "user_id LIKE :user";
    $params[':user'] = "%$filter_user%";
}

$where_clause = 'WHERE ' . implode(' AND ', $conditions);

// Get transaction data
try {
    $query = "SELECT 
                id,
                type,
                sub_type,
                sku,
                product_name,
                barcode,
                pallet_id,
                tags_id,
                location_id,
                zone_location,
                status_location,
                pieces,
                weight,
                lot,
                customer_code,
                shop_name,
                user_id,
                timestamp,
                remark
             FROM master_transactions
             $where_clause
             ORDER BY timestamp DESC";
    
    $stmt = $db->prepare($query);
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $summary_query = "SELECT 
                        type,
                        COUNT(*) as count,
                        SUM(pieces) as total_pieces,
                        SUM(weight) as total_weight
                     FROM master_transactions
                     $where_clause
                     GROUP BY type";
    
    $summary_stmt = $db->prepare($summary_query);
    foreach($params as $key => $value) {
        $summary_stmt->bindValue($key, $value);
    }
    $summary_stmt->execute();
    $summary_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $summary = [];
    foreach($summary_data as $item) {
        $summary[$item['type']] = $item;
    }
    
} catch(Exception $e) {
    error_log("Transaction report error: " . $e->getMessage());
    $transactions = [];
    $summary = [];
}

$page_title = 'รายงานการเคลื่อนไหว';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-exchange-alt"></i> รายงานการเคลื่อนไหว</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="../" class="text-white">รายงาน</a></li>
                                    <li class="breadcrumb-item active text-white">รายงานการเคลื่อนไหว</li>
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
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">รับสินค้า</h6>
                            <h3 class="text-success"><?php echo formatNumber($summary['RECEIVE']['count'] ?? 0); ?></h3>
                            <small class="text-muted"><?php echo formatNumber($summary['RECEIVE']['total_pieces'] ?? 0); ?> ชิ้น</small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-truck-loading fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">จัดเตรียม</h6>
                            <h3 class="text-warning"><?php echo formatNumber($summary['PICKING']['count'] ?? 0); ?></h3>
                            <small class="text-muted"><?php echo formatNumber($summary['PICKING']['total_pieces'] ?? 0); ?> ชิ้น</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-hand-paper fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ย้าย</h6>
                            <h3 class="text-info"><?php echo formatNumber($summary['MOVEMENT']['count'] ?? 0); ?></h3>
                            <small class="text-muted"><?php echo formatNumber($summary['MOVEMENT']['total_pieces'] ?? 0); ?> ชิ้น</small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">รวมทั้งหมด</h6>
                            <h3 class="text-secondary"><?php echo formatNumber(count($transactions)); ?></h3>
                            <small class="text-muted">รายการ</small>
                        </div>
                        <div class="text-secondary">
                            <i class="fas fa-list fa-2x"></i>
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
                            <div class="col-md-2 mb-3">
                                <label class="form-label">วันที่เริ่มต้น</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">วันที่สิ้นสุด</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">ประเภท</label>
                                <select name="type" class="form-select">
                                    <option value="">ทุกประเภท</option>
                                    <option value="RECEIVE" <?php echo $filter_type === 'RECEIVE' ? 'selected' : ''; ?>>รับสินค้า</option>
                                    <option value="PICKING" <?php echo $filter_type === 'PICKING' ? 'selected' : ''; ?>>จัดเตรียม</option>
                                    <option value="MOVEMENT" <?php echo $filter_type === 'MOVEMENT' ? 'selected' : ''; ?>>ย้าย</option>
                                    <option value="ADJUST" <?php echo $filter_type === 'ADJUST' ? 'selected' : ''; ?>>ปรับสต็อก</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">ประเภทย่อย</label>
                                <input type="text" name="sub_type" class="form-control" value="<?php echo htmlspecialchars($filter_sub_type); ?>" placeholder="ประเภทย่อย">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($filter_sku); ?>" placeholder="ค้นหา SKU">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">ผู้ใช้งาน</label>
                                <input type="text" name="user" class="form-control" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="รหัสผู้ใช้">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                            <a href="transactions.php" class="btn btn-secondary">
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

    <!-- Transactions Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายการเคลื่อนไหว</h5>
                    <div class="text-muted">
                        ทั้งหมด <?php echo formatNumber(count($transactions)); ?> รายการ
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>วันที่/เวลา</th>
                                    <th>ประเภท</th>
                                    <th>SKU</th>
                                    <th>ชื่อสินค้า</th>
                                    <th>Pallet/Tags</th>
                                    <th>Location</th>
                                    <th>จำนวน</th>
                                    <th>น้ำหนัก</th>
                                    <th>ลูกค้า</th>
                                    <th>ผู้ใช้งาน</th>
                                    <th>หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($txn['timestamp']); ?></strong>
                                        <br><small class="text-muted"><?php echo date('H:i:s', $txn['timestamp']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $type_colors = [
                                            'RECEIVE' => 'success',
                                            'PICKING' => 'warning',
                                            'MOVEMENT' => 'info',
                                            'ADJUST' => 'secondary'
                                        ];
                                        $type_names = [
                                            'RECEIVE' => 'รับสินค้า',
                                            'PICKING' => 'จัดเตรียม',
                                            'MOVEMENT' => 'ย้าย',
                                            'ADJUST' => 'ปรับสต็อก'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $type_colors[$txn['type']] ?? 'secondary'; ?>">
                                            <?php echo $type_names[$txn['type']] ?? $txn['type']; ?>
                                        </span>
                                        <?php if($txn['sub_type']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($txn['sub_type']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($txn['sku']); ?></strong>
                                        <?php if($txn['lot']): ?>
                                        <br><small class="text-muted">LOT: <?php echo htmlspecialchars($txn['lot']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($txn['product_name']); ?></td>
                                    <td>
                                        <?php if($txn['pallet_id']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($txn['pallet_id']); ?></span>
                                        <?php endif; ?>
                                        <?php if($txn['tags_id']): ?>
                                        <br><span class="badge bg-info"><?php echo htmlspecialchars($txn['tags_id']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($txn['location_id']): ?>
                                        <strong><?php echo htmlspecialchars($txn['location_id']); ?></strong>
                                        <?php if($txn['zone_location']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($txn['zone_location']); ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo formatNumber($txn['pieces']); ?></strong> ชิ้น
                                    </td>
                                    <td><?php echo formatWeight($txn['weight']); ?></td>
                                    <td>
                                        <?php if($txn['customer_code'] || $txn['shop_name']): ?>
                                        <?php if($txn['customer_code']): ?>
                                        <strong><?php echo htmlspecialchars($txn['customer_code']); ?></strong>
                                        <?php endif; ?>
                                        <?php if($txn['shop_name']): ?>
                                        <br><small><?php echo htmlspecialchars($txn['shop_name']); ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($txn['user_id']); ?></span>
                                    </td>
                                    <td>
                                        <?php if($txn['remark']): ?>
                                        <small><?php echo htmlspecialchars($txn['remark']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#transactionsTable').DataTable({
        order: [[0, 'desc']], // Sort by date/time descending
        pageLength: 50,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        }
    });
});

function exportToExcel() {
    // Create Excel export
    const table = document.getElementById('transactionsTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Transaction Report"});
    XLSX.writeFile(wb, `transaction_report_${new Date().toISOString().split('T')[0]}.xlsx`);
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