<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Location.php';

// Check login
checkLogin();

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);
$location = new Location($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Get filter parameters
$filter_zone = $_GET['zone'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_sku = $_GET['sku'] ?? '';
$filter_expiry = $_GET['expiry'] ?? '';

// Get all locations with detailed information
$locations = $location->getAllLocations();

// Apply filters
if($filter_zone) {
    $locations = array_filter($locations, function($loc) use ($filter_zone) {
        return strpos($loc['zone'], $filter_zone) !== false;
    });
}

if($filter_status) {
    $locations = array_filter($locations, function($loc) use ($filter_status) {
        return $loc['status'] === $filter_status;
    });
}

if($filter_sku) {
    $locations = array_filter($locations, function($loc) use ($filter_sku) {
        return strpos($loc['sku'] ?? '', $filter_sku) !== false;
    });
}

// Get utilization data
$utilization = $location->getLocationUtilization();
$zones = $location->getZones();

// Get expiring items
$expiring_items = $location->getExpiringSoon(30);

// Calculate summary statistics
$total_locations = count($locations);
$occupied_locations = count(array_filter($locations, function($loc) { return $loc['status'] === 'เก็บสินค้า'; }));
$available_locations = count(array_filter($locations, function($loc) { return $loc['status'] === 'ว่าง'; }));
$suspended_locations = count(array_filter($locations, function($loc) { return $loc['status'] === 'ระงับ'; }));

$utilization_percent = $total_locations > 0 ? round(($occupied_locations / $total_locations) * 100, 2) : 0;

$page_title = 'รายงานตำแหน่งเก็บ';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-map-marked-alt"></i> รายงานตำแหน่งเก็บ</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-dark">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="../" class="text-dark">รายงาน</a></li>
                                    <li class="breadcrumb-item active text-dark">รายงานตำแหน่งเก็บ</li>
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
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ตำแหน่งทั้งหมด</h6>
                            <h3 class="text-primary"><?php echo formatNumber($total_locations); ?></h3>
                            <small class="text-muted">ตำแหน่ง</small>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-th fa-2x"></i>
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
                            <h6 class="text-muted">ใช้งานแล้ว</h6>
                            <h3 class="text-warning"><?php echo formatNumber($occupied_locations); ?></h3>
                            <small class="text-muted"><?php echo $utilization_percent; ?>% ของทั้งหมด</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-cube fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ว่าง</h6>
                            <h3 class="text-success"><?php echo formatNumber($available_locations); ?></h3>
                            <small class="text-muted">พร้อมใช้งาน</small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-square fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">หมดอายุเร็ว</h6>
                            <h3 class="text-danger"><?php echo formatNumber(count($expiring_items)); ?></h3>
                            <small class="text-muted">ใน 30 วัน</small>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zone Utilization Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> การใช้งาน Zone</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($utilization as $util): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card border-0">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><?php echo htmlspecialchars($util['zone']); ?></h6>
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $util['utilization_percent']; ?>%">
                                            <?php echo $util['utilization_percent']; ?>%
                                        </div>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="h5 text-warning"><?php echo $util['occupied']; ?></div>
                                            <small class="text-muted">ใช้งาน</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 text-success"><?php echo $util['available']; ?></div>
                                            <small class="text-muted">ว่าง</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 text-info"><?php echo $util['total_locations']; ?></div>
                                            <small class="text-muted">รวม</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                                <label class="form-label">Zone</label>
                                <select name="zone" class="form-select">
                                    <option value="">ทุก Zone</option>
                                    <?php foreach($zones as $zone): ?>
                                    <option value="<?php echo htmlspecialchars($zone); ?>" <?php echo $filter_zone === $zone ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zone); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">สถานะ</label>
                                <select name="status" class="form-select">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="ว่าง" <?php echo $filter_status === 'ว่าง' ? 'selected' : ''; ?>>ว่าง</option>
                                    <option value="เก็บสินค้า" <?php echo $filter_status === 'เก็บสินค้า' ? 'selected' : ''; ?>>เก็บสินค้า</option>
                                    <option value="ระงับ" <?php echo $filter_status === 'ระงับ' ? 'selected' : ''; ?>>ระงับ</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($filter_sku); ?>" placeholder="ค้นหา SKU">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">วันหมดอายุ</label>
                                <select name="expiry" class="form-select">
                                    <option value="">ทั้งหมด</option>
                                    <option value="expired" <?php echo $filter_expiry === 'expired' ? 'selected' : ''; ?>>หมดอายุแล้ว</option>
                                    <option value="expiring_soon" <?php echo $filter_expiry === 'expiring_soon' ? 'selected' : ''; ?>>หมดอายุใน 30 วัน</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                            <a href="locations.php" class="btn btn-secondary">
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

    <!-- Locations Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายละเอียดตำแหน่งเก็บ</h5>
                    <div class="text-muted">
                        ทั้งหมด <?php echo formatNumber(count($locations)); ?> ตำแหน่ง
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="locationsTable">
                            <thead>
                                <tr>
                                    <th>Location ID</th>
                                    <th>Zone</th>
                                    <th>Row-Level-Loc</th>
                                    <th>สถานะ</th>
                                    <th>SKU</th>
                                    <th>ชื่อสินค้า</th>
                                    <th>จำนวน</th>
                                    <th>น้ำหนัก</th>
                                    <th>Pallet ID</th>
                                    <th>LOT</th>
                                    <th>วันหมดอายุ</th>
                                    <th>การใช้พื้นที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($locations as $loc): ?>
                                <?php
                                // Apply expiry filter
                                if($filter_expiry) {
                                    $now = time();
                                    $expiry_time = $loc['expiration_date'];
                                    
                                    if($filter_expiry === 'expired' && ($expiry_time > $now || !$expiry_time)) continue;
                                    if($filter_expiry === 'expiring_soon' && ($expiry_time > ($now + 30*24*60*60) || !$expiry_time)) continue;
                                }
                                
                                // Calculate utilization
                                $weight_utilization = $loc['max_weight'] > 0 ? round(($loc['น้ำหนัก'] / $loc['max_weight']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($loc['location_id']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($loc['zone']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($loc['row_name']); ?>-<?php echo $loc['level_num']; ?>-<?php echo $loc['loc_num']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'ว่าง' => 'success',
                                            'เก็บสินค้า' => 'warning',
                                            'ระงับ' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_colors[$loc['status']] ?? 'secondary'; ?>">
                                            <?php echo $loc['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($loc['sku']): ?>
                                        <strong><?php echo htmlspecialchars($loc['sku']); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['product_name']): ?>
                                        <?php echo htmlspecialchars($loc['product_name']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['ชิ้น']): ?>
                                        <strong><?php echo formatNumber($loc['ชิ้น']); ?></strong> ชิ้น
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['น้ำหนัก']): ?>
                                        <strong><?php echo formatWeight($loc['น้ำหนัก']); ?></strong>
                                        <br><small class="text-muted">Max: <?php echo formatWeight($loc['max_weight']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <br><small class="text-muted">Max: <?php echo formatWeight($loc['max_weight']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['pallet_id']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($loc['pallet_id']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['lot']): ?>
                                        <?php echo htmlspecialchars($loc['lot']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['expiration_date'] && $loc['expiration_date'] > 0): ?>
                                        <?php
                                        $expiry_date = date('Y-m-d', $loc['expiration_date']);
                                        $days_to_expiry = ceil(($loc['expiration_date'] - time()) / (24*60*60));
                                        $expiry_class = '';
                                        if($days_to_expiry < 0) {
                                            $expiry_class = 'text-danger';
                                        } elseif($days_to_expiry <= 7) {
                                            $expiry_class = 'text-danger';
                                        } elseif($days_to_expiry <= 30) {
                                            $expiry_class = 'text-warning';
                                        } else {
                                            $expiry_class = 'text-success';
                                        }
                                        ?>
                                        <span class="<?php echo $expiry_class; ?>">
                                            <?php echo $expiry_date; ?>
                                        </span>
                                        <br><small class="<?php echo $expiry_class; ?>">
                                            <?php if($days_to_expiry < 0): ?>
                                            หมดอายุแล้ว <?php echo abs($days_to_expiry); ?> วัน
                                            <?php else: ?>
                                            อีก <?php echo $days_to_expiry; ?> วัน
                                            <?php endif; ?>
                                        </small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($weight_utilization > 0): ?>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar <?php echo $weight_utilization > 90 ? 'bg-danger' : ($weight_utilization > 70 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 style="width: <?php echo $weight_utilization; ?>%">
                                                <?php echo $weight_utilization; ?>%
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar bg-light" style="width: 100%">0%</div>
                                        </div>
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
    $('#locationsTable').DataTable({
        order: [[0, 'asc']], // Sort by Location ID
        pageLength: 50,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        }
    });
});

function exportToExcel() {
    // Create Excel export
    const table = document.getElementById('locationsTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Location Report"});
    XLSX.writeFile(wb, `location_report_${new Date().toISOString().split('T')[0]}.xlsx`);
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