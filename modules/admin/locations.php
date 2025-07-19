<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Location.php';

// Check login and admin permission
checkPermission('admin');

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

$error_message = '';
$success_message = '';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'create':
                    $location_data = [
                        'location_id' => sanitizeInput($_POST['location_id']),
                        'zone' => sanitizeInput($_POST['zone']),
                        'row_name' => sanitizeInput($_POST['row_name']),
                        'level_num' => (int)$_POST['level_num'],
                        'loc_num' => (int)$_POST['loc_num'],
                        'max_weight' => (float)$_POST['max_weight'],
                        'max_pallet' => (int)$_POST['max_pallet'],
                        'max_height' => (float)$_POST['max_height'],
                        'status' => 'ว่าง'
                    ];
                    
                    if($location->createLocation($location_data)) {
                        $success_message = 'เพิ่ม Location เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถเพิ่ม Location ได้';
                    }
                    break;
                    
                case 'update':
                    $location_id = sanitizeInput($_POST['location_id']);
                    $location_data = [
                        'zone' => sanitizeInput($_POST['zone']),
                        'row_name' => sanitizeInput($_POST['row_name']),
                        'level_num' => (int)$_POST['level_num'],
                        'loc_num' => (int)$_POST['loc_num'],
                        'max_weight' => (float)$_POST['max_weight'],
                        'max_pallet' => (int)$_POST['max_pallet'],
                        'max_height' => (float)$_POST['max_height']
                    ];
                    
                    if($location->updateLocation($location_id, $location_data)) {
                        $success_message = 'อัพเดตข้อมูล Location เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถอัพเดตข้อมูลได้';
                    }
                    break;
                    
                case 'delete':
                    $location_id = sanitizeInput($_POST['location_id']);
                    if($location->deleteLocation($location_id)) {
                        $success_message = 'ลบ Location เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถลบ Location ได้ อาจมีสินค้าอยู่';
                    }
                    break;
                    
                case 'clear_location':
                    $location_id = sanitizeInput($_POST['location_id']);
                    if($location->removePalletFromLocation($location_id)) {
                        $success_message = 'ล้างข้อมูล Location เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถล้างข้อมูล Location ได้';
                    }
                    break;
            }
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all locations with utilization data
$locations = $location->getAllLocations();
$utilization = $location->getLocationUtilization();
$zones = $location->getZones();

$page_title = 'จัดการตำแหน่ง';
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
                            <h2><i class="fas fa-map"></i> จัดการตำแหน่ง</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-dark">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="index.php" class="text-dark">จัดการระบบ</a></li>
                                    <li class="breadcrumb-item active text-dark">จัดการตำแหน่ง</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                                <i class="fas fa-plus"></i> เพิ่ม Location ใหม่
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Zone Utilization Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> สรุปการใช้งาน Zone</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($utilization as $util): ?>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><?php echo htmlspecialchars($util['zone']); ?></h6>
                                    <div class="h4 text-primary"><?php echo $util['utilization_percent']; ?>%</div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar" style="width: <?php echo $util['utilization_percent']; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        ใช้งาน <?php echo $util['occupied']; ?> / <?php echo $util['total_locations']; ?> ตำแหน่ง
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Locations Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายการตำแหน่ง</h5>
                    <div class="d-flex gap-2">
                        <select id="zoneFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">ทุก Zone</option>
                            <?php foreach($zones as $zone): ?>
                            <option value="<?php echo htmlspecialchars($zone); ?>"><?php echo htmlspecialchars($zone); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-success btn-sm" onclick="exportLocations()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-info btn-sm" onclick="refreshTable()">
                            <i class="fas fa-sync"></i> รีเฟรช
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="locationsTable">
                            <thead>
                                <tr>
                                    <th>Location ID</th>
                                    <th>Zone</th>
                                    <th>Row</th>
                                    <th>Level</th>
                                    <th>Loc</th>
                                    <th>สถานะ</th>
                                    <th>สินค้า</th>
                                    <th>จำนวน</th>
                                    <th>น้ำหนัก</th>
                                    <th>Pallet ID</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($locations as $loc): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($loc['location_id']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($loc['zone']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($loc['row_name']); ?></td>
                                    <td><?php echo $loc['level_num']; ?></td>
                                    <td><?php echo $loc['loc_num']; ?></td>
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
                                        <br><small class="text-muted"><?php echo htmlspecialchars($loc['product_name']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['ชิ้น']): ?>
                                        <?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($loc['น้ำหนัก']): ?>
                                        <?php echo formatWeight($loc['น้ำหนัก']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
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
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editLocation(<?php echo htmlspecialchars(json_encode($loc)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if($loc['status'] === 'เก็บสินค้า'): ?>
                                            <button class="btn btn-outline-warning" onclick="clearLocation('<?php echo htmlspecialchars($loc['location_id']); ?>')">
                                                <i class="fas fa-broom"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if($loc['status'] === 'ว่าง'): ?>
                                            <button class="btn btn-outline-danger" onclick="deleteLocation('<?php echo htmlspecialchars($loc['location_id']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่ม Location ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location ID <span class="text-danger">*</span></label>
                            <input type="text" name="location_id" class="form-control" required pattern="[A-Z0-9-]+" 
                                   placeholder="เช่น A01-01-01">
                            <div class="form-text">รูปแบบ: Zone-Row-Level-Loc</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zone <span class="text-danger">*</span></label>
                            <input type="text" name="zone" class="form-control" required 
                                   placeholder="เช่น PF-Zone Selective Rack">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Row <span class="text-danger">*</span></label>
                            <input type="text" name="row_name" class="form-control" required placeholder="เช่น A01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Level <span class="text-danger">*</span></label>
                            <input type="number" name="level_num" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loc Number <span class="text-danger">*</span></label>
                            <input type="number" name="loc_num" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">น้ำหนักสูงสุด (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="max_weight" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">จำนวนพาเลทสูงสุด <span class="text-danger">*</span></label>
                            <input type="number" name="max_pallet" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ความสูงสูงสุด (ม.) <span class="text-danger">*</span></label>
                            <input type="number" name="max_height" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Location Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> แก้ไขข้อมูล Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editLocationForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="location_id" id="edit_location_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location ID</label>
                            <input type="text" id="edit_location_id_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zone <span class="text-danger">*</span></label>
                            <input type="text" name="zone" id="edit_zone" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Row <span class="text-danger">*</span></label>
                            <input type="text" name="row_name" id="edit_row_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Level <span class="text-danger">*</span></label>
                            <input type="number" name="level_num" id="edit_level_num" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loc Number <span class="text-danger">*</span></label>
                            <input type="number" name="loc_num" id="edit_loc_num" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">น้ำหนักสูงสุด (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="max_weight" id="edit_max_weight" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">จำนวนพาเลทสูงสุด <span class="text-danger">*</span></label>
                            <input type="number" name="max_pallet" id="edit_max_pallet" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ความสูงสูงสุด (ม.) <span class="text-danger">*</span></label>
                            <input type="number" name="max_height" id="edit_max_height" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">อัพเดต</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#locationsTable').DataTable({
        order: [[0, 'asc']], // Sort by Location ID
        pageLength: 25,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        },
        columnDefs: [
            { targets: -1, orderable: false } // Disable sorting for actions column
        ]
    });
    
    // Zone filter
    $('#zoneFilter').on('change', function() {
        const selectedZone = this.value;
        if(selectedZone) {
            table.column(1).search(selectedZone).draw();
        } else {
            table.column(1).search('').draw();
        }
    });
});

function editLocation(locationData) {
    $('#edit_location_id').val(locationData.location_id);
    $('#edit_location_id_display').val(locationData.location_id);
    $('#edit_zone').val(locationData.zone);
    $('#edit_row_name').val(locationData.row_name);
    $('#edit_level_num').val(locationData.level_num);
    $('#edit_loc_num').val(locationData.loc_num);
    $('#edit_max_weight').val(locationData.max_weight);
    $('#edit_max_pallet').val(locationData.max_pallet);
    $('#edit_max_height').val(locationData.max_height);
    
    $('#editLocationModal').modal('show');
}

function clearLocation(locationId) {
    if(confirm(`ต้องการล้างข้อมูลใน Location "${locationId}" หรือไม่?\n\nสินค้าจะถูกเอาออกจากตำแหน่งนี้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="clear_location">
            <input type="hidden" name="location_id" value="${locationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteLocation(locationId) {
    if(confirm(`ต้องการลบ Location "${locationId}" หรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="location_id" value="${locationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportLocations() {
    // Export locations to CSV
    const table = document.getElementById('locationsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'Location ID,Zone,Row,Level,Loc,สถานะ,สินค้า,จำนวน,น้ำหนัก,Pallet ID\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        for(let i = 0; i < cells.length - 1; i++) { // Exclude actions column
            rowData.push('"' + cells[i].textContent.trim().replace(/"/g, '""') + '"');
        }
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'locations_export_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

function refreshTable() {
    location.reload();
}
</script>

<?php include '../../includes/footer.php'; ?>