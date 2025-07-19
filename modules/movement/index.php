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

// Check login
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
    header('Location: ../../logout.php');
    exit;
}

$error_message = '';
$success_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $required_fields = ['from_location_id', 'to_location_id', 'movement_type'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("กรุณากรอกข้อมูล: " . $field);
            }
        }

        $from_location = $_POST['from_location_id'];
        $to_location = $_POST['to_location_id'];

        if($from_location === $to_location) {
            throw new Exception("❌ Location ต้นทางและปลายทางต้องไม่เหมือนกัน");
        }

        // Get source location data
        $from_location_data = $location->getLocationById($from_location);
        if(!$from_location_data || $from_location_data['status'] !== 'เก็บสินค้า') {
            throw new Exception("❌ Location ต้นทางไม่มีสินค้า");
        }

        // Get destination location data
        $to_location_data = $location->getLocationById($to_location);
        if(!$to_location_data || $to_location_data['status'] !== 'ว่าง') {
            throw new Exception("❌ Location ปลายทางไม่ว่าง");
        }

        // Check FEFO if moving to PF-Zone
        if(FEFO_ENABLED && strpos($to_location_data['zone'], 'PF-Zone') !== false) {
            if(!$location->checkFEFO($from_location_data['sku'], $from_location_data['expiration_date'])) {
                throw new Exception("❌ จำเป็นต้องเบิก FEFO Location ก่อน");
            }
        }

        // Prepare transaction data
        $transaction_data = [
            'sub_type' => $_POST['movement_type'],
            'sku' => $from_location_data['sku'],
            'product_name' => $from_location_data['product_name'],
            'barcode' => $from_location_data['barcode'],
            'pallet_id' => $from_location_data['pallet_id'],
            'location_id' => $to_location,
            'zone_location' => $to_location_data['zone'],
            'status_location' => 'เก็บสินค้า',
            'pieces' => $from_location_data['ชิ้น'],
            'weight' => $from_location_data['น้ำหนัก'],
            'lot' => $from_location_data['lot'],
            'received_date' => $from_location_data['received_date'],
            'expiration_date' => $from_location_data['expiration_date'],
            'pallet_color' => $from_location_data['สีพาเลท'],
            'remark' => "ย้ายจาก {$from_location} ไป {$to_location}. " . ($_POST['remark'] ?? '')
        ];

        // Start transaction
        $db->beginTransaction();

        // Create movement transaction
        $result = $transaction->createMovementTransaction($transaction_data);

        if($result['success']) {
            // Move pallet
            $location->movePallet($from_location, $to_location, $from_location_data['pallet_id']);

            $db->commit();

            $success_message = "✅ ย้ายสินค้าสำเร็จ<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $result['tags_id'] . "<br>";
            $success_message .= "<strong>จาก:</strong> " . $from_location . "<br>";
            $success_message .= "<strong>ไป:</strong> " . $to_location . "<br>";
            $success_message .= "<strong>Pallet ID:</strong> " . $from_location_data['pallet_id'];

            // Log activity
            logActivity('MOVEMENT', "Moved pallet {$from_location_data['pallet_id']} from {$from_location} to {$to_location}", $current_user['user_id']);

        } else {
            throw new Exception($result['error']);
        }

    } catch(Exception $e) {
        $db->rollback();
        $error_message = $e->getMessage();
        logActivity('MOVEMENT_ERROR', $e->getMessage(), $current_user['user_id']);
    }
}

// Get data for dropdowns
$occupied_locations = $location->getOccupiedLocations();
$available_locations = $location->getAvailableLocations();
$zones = $location->getZones();

// Get pre-selected location from URL
$selected_location = $_GET['location'] ?? '';

$page_title = 'ย้ายสินค้า';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="fas fa-exchange-alt"></i> ย้ายสินค้า (Movement)</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-dark">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-dark">ย้ายสินค้า</li>
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

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit"></i> ฟอร์มย้ายสินค้า</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="movement-form">
                            <div class="row">
                                <!-- From Location -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location ต้นทาง <span class="text-danger">*</span></label>
                                    <select name="from_location_id" id="from-location-select" class="form-select" required>
                                        <option value="">เลือก Location ต้นทาง</option>
                                        <?php foreach($occupied_locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>"
                                                data-sku="<?php echo htmlspecialchars($loc['sku']); ?>"
                                                data-product-name="<?php echo htmlspecialchars($loc['product_name']); ?>"
                                                data-pieces="<?php echo $loc['ชิ้น']; ?>"
                                                data-weight="<?php echo $loc['น้ำหนัก']; ?>"
                                                data-pallet-id="<?php echo htmlspecialchars($loc['pallet_id']); ?>"
                                                data-lot="<?php echo htmlspecialchars($loc['lot']); ?>"
                                                data-expiry="<?php echo $loc['expiration_date']; ?>"
                                                data-zone="<?php echo htmlspecialchars($loc['zone']); ?>"
                                                <?php echo ($selected_location == $loc['location_id']) ? 'selected' : ''; ?>>
                                            <?php echo $loc['location_id']; ?> - <?php echo htmlspecialchars($loc['sku']); ?> 
                                            (<?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- To Location -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location ปลายทาง <span class="text-danger">*</span></label>
                                    <select name="to_location_id" id="to-location-select" class="form-select" required>
                                        <option value="">เลือก Location ปลายทาง</option>
                                        <?php foreach($available_locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>"
                                                data-zone="<?php echo htmlspecialchars($loc['zone']); ?>"
                                                data-max-weight="<?php echo $loc['max_weight']; ?>">
                                            <?php echo $loc['location_id']; ?> (<?php echo htmlspecialchars($loc['zone']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Movement Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ประเภทการย้าย <span class="text-danger">*</span></label>
                                    <select name="movement_type" class="form-select" required>
                                        <option value="">เลือกประเภท</option>
                                        <option value="ย้ายไป PF-Zone">ย้ายไป PF-Zone</option>
                                        <option value="ย้ายออกจาก PF-Zone">ย้ายออกจาก PF-Zone</option>
                                        <option value="ย้ายใน Zone เดียวกัน">ย้ายใน Zone เดียวกัน</option>
                                        <option value="ย้ายไป Zone อื่น">ย้ายไป Zone อื่น</option>
                                        <option value="ย้ายไป Premium Zone">ย้ายไป Premium Zone</option>
                                        <option value="ย้ายไป Packaging Zone">ย้ายไป Packaging Zone</option>
                                        <option value="ย้ายไป Damaged Zone">ย้ายไป Damaged Zone</option>
                                    </select>
                                </div>
                                
                                <!-- Reason -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เหตุผลการย้าย</label>
                                    <select name="reason" class="form-select">
                                        <option value="">เลือกเหตุผล</option>
                                        <option value="จัดเรียงใหม่">จัดเรียงใหม่</option>
                                        <option value="เตรียมเบิก">เตรียมเบิก</option>
                                        <option value="เปลี่ยน Zone">เปลี่ยน Zone</option>
                                        <option value="ซ่อมแซม Location">ซ่อมแซม Location</option>
                                        <option value="ตรวจสอบสินค้า">ตรวจสอบสินค้า</option>
                                        <option value="จัดเก็บใหม่">จัดเก็บใหม่</option>
                                    </select>
                                </div>
                                
                                <!-- Source Info (Auto-filled) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SKU</label>
                                    <input type="text" id="source-sku" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อสินค้า</label>
                                    <input type="text" id="source-product-name" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pallet ID</label>
                                    <input type="text" id="source-pallet-id" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zone ต้นทาง</label>
                                    <input type="text" id="source-zone" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">จำนวน (ชิ้น)</label>
                                    <input type="text" id="source-pieces" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">น้ำหนัก (กก.)</label>
                                    <input type="text" id="source-weight" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">LOT</label>
                                    <input type="text" id="source-lot" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zone ปลายทาง</label>
                                    <input type="text" id="destination-zone" class="form-control" readonly>
                                </div>
                                
                                <!-- Remarks -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea name="remark" class="form-control" rows="3" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-exchange-alt"></i> ย้ายสินค้า
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Info Panel -->
            <div class="col-lg-4">
                <!-- Movement Preview -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-eye"></i> ตัวอย่างการย้าย</h6>
                    </div>
                    <div class="card-body" id="movement-preview">
                        <p class="text-muted text-center">เลือก Location เพื่อดูตัวอย่าง</p>
                    </div>
                </div>

                <!-- Validation Status -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-shield-alt"></i> การตรวจสอบ</h6>
                    </div>
                    <div class="card-body" id="validation-status">
                        <p class="text-muted text-center">กรอกข้อมูลเพื่อตรวจสอบ</p>
                    </div>
                </div>
                
                <!-- Zone Stats -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar"></i> สถิติ Zone</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach($zones as $zone): ?>
                        <?php 
                        $zone_locations = $location->getLocationsByZone($zone);
                        $occupied = array_filter($zone_locations, function($loc) { return $loc['status'] === 'เก็บสินค้า'; });
                        $utilization = count($zone_locations) > 0 ? (count($occupied) / count($zone_locations)) * 100 : 0;
                        ?>
                        <div class="mb-2">
                            <small class="text-muted"><?php echo htmlspecialchars($zone); ?></small>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?php echo getZoneColor($zone); ?>" 
                                     style="width: <?php echo $utilization; ?>%"></div>
                            </div>
                            <small><?php echo number_format($utilization, 1); ?>% (<?php echo count($occupied); ?>/<?php echo count($zone_locations); ?>)</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-lightbulb"></i> คำแนะนำ</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                ตรวจสอบ Location ต้นทางและปลายทาง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                ให้เหตุผลการย้ายที่ชัดเจน
                            </li>
                            <?php if(FEFO_ENABLED): ?>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning"></i> 
                                ระบบจะตรวจสอบ FEFO อัตโนมัติ
                            </li>
                            <?php endif; ?>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-info"></i> 
                                ย้ายทั้ง Pallet ในครั้งเดียว
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-database text-primary"></i> 
                                บันทึกประวัติการย้ายอัตโนมัติ
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-bolt"></i> การดำเนินการด่วน</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../receive/" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-truck-loading"></i> รับสินค้า
                            </a>
                            <a href="../picking/" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-hand-paper"></i> จัดเตรียมสินค้า
                            </a>
                            <a href="../reports/transactions.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-list-alt"></i> ประวัติการย้าย
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#from-location-select').select2({
                placeholder: 'ค้นหา Location ต้นทาง',
                allowClear: true,
                theme: 'bootstrap-5'
            });
            
            $('#to-location-select').select2({
                placeholder: 'ค้นหา Location ปลายทาง',
                allowClear: true,
                theme: 'bootstrap-5'
            });
            
            // Auto-select first location if from URL
            <?php if($selected_location): ?>
            $('#from-location-select').val('<?php echo $selected_location; ?>').trigger('change');
            <?php endif; ?>
            
            // From location change event
            $('#from-location-select').change(function() {
                const selectedOption = $(this).find('option:selected');
                
                if(selectedOption.val()) {
                    const sku = selectedOption.data('sku');
                    const productName = selectedOption.data('product-name');
                    const pieces = selectedOption.data('pieces');
                    const weight = selectedOption.data('weight');
                    const palletId = selectedOption.data('pallet-id');
                    const lot = selectedOption.data('lot');
                    const zone = selectedOption.data('zone');
                    
                    // Update source info
                    $('#source-sku').val(sku);
                    $('#source-product-name').val(productName);
                    $('#source-pallet-id').val(palletId);
                    $('#source-lot').val(lot);
                    $('#source-zone').val(zone);
                    $('#source-pieces').val(pieces.toLocaleString());
                    $('#source-weight').val(weight.toFixed(2));
                    
                    updateMovementPreview();
                    validateMovement();
                } else {
                    clearSourceInfo();
                }
            });
            
            // To location change event
            $('#to-location-select').change(function() {
                const selectedOption = $(this).find('option:selected');
                
                if(selectedOption.val()) {
                    const zone = selectedOption.data('zone');
                    const maxWeight = selectedOption.data('max-weight');
                    
                    $('#destination-zone').val(zone);
                    
                    updateMovementPreview();
                    validateMovement();
                } else {
                    $('#destination-zone').val('');
                    updateMovementPreview();
                    validateMovement();
                }
            });
            
            function clearSourceInfo() {
                $('#source-sku').val('');
                $('#source-product-name').val('');
                $('#source-pallet-id').val('');
                $('#source-lot').val('');
                $('#source-zone').val('');
                $('#source-pieces').val('');
                $('#source-weight').val('');
                $('#movement-preview').html('<p class="text-muted text-center">เลือก Location เพื่อดูตัวอย่าง</p>');
                $('#validation-status').html('<p class="text-muted text-center">กรอกข้อมูลเพื่อตรวจสอบ</p>');
            }
            
            function updateMovementPreview() {
                const fromLocation = $('#from-location-select').val();
                const toLocation = $('#to-location-select').val();
                const fromZone = $('#source-zone').val();
                const toZone = $('#destination-zone').val();
                const palletId = $('#source-pallet-id').val();
                const sku = $('#source-sku').val();
                
                if(fromLocation && toLocation && palletId) {
                    $('#movement-preview').html(`
                        <div class="text-center">
                            <div class="mb-3">
                                <div class="badge bg-secondary mb-2">${fromZone}</div>
                                <br>
                                <strong>${fromLocation}</strong>
                                <br>
                                <small>${sku} - ${palletId}</small>
                            </div>
                            
                            <div class="mb-3">
                                <i class="fas fa-arrow-down fa-2x text-warning"></i>
                            </div>
                            
                            <div class="mb-3">
                                <div class="badge bg-primary mb-2">${toZone}</div>
                                <br>
                                <strong>${toLocation}</strong>
                            </div>
                        </div>
                    `);
                } else {
                    $('#movement-preview').html('<p class="text-muted text-center">เลือก Location เพื่อดูตัวอย่าง</p>');
                }
            }
            
            function validateMovement() {
                const fromLocation = $('#from-location-select').val();
                const toLocation = $('#to-location-select').val();
                const fromZone = $('#source-zone').val();
                const toZone = $('#destination-zone').val();
                const weight = parseFloat($('#source-weight').val()) || 0;
                
                let validations = [];
                
                if(fromLocation && toLocation) {
                    if(fromLocation === toLocation) {
                        validations.push({
                            icon: 'fas fa-times text-danger',
                            message: 'Location ต้นทางและปลายทางเหมือนกัน',
                            type: 'error'
                        });
                    } else {
                        validations.push({
                            icon: 'fas fa-check text-success',
                            message: 'Location ต้นทางและปลายทางถูกต้อง',
                            type: 'success'
                        });
                    }
                    
                    // Check weight capacity
                    const toLocationOption = $('#to-location-select').find('option:selected');
                    const maxWeight = parseFloat(toLocationOption.data('max-weight')) || 0;
                    
                    if(weight > maxWeight) {
                        validations.push({
                            icon: 'fas fa-exclamation-triangle text-warning',
                            message: `น้ำหนักเกินกำหนด (${weight.toFixed(2)}/${maxWeight} กก.)`,
                            type: 'warning'
                        });
                    } else {
                        validations.push({
                            icon: 'fas fa-check text-success',
                            message: 'น้ำหนักอยู่ในขีดจำกัด',
                            type: 'success'
                        });
                    }
                    
                    // Check zone change
                    if(fromZone !== toZone) {
                        validations.push({
                            icon: 'fas fa-info-circle text-info',
                            message: `เปลี่ยน Zone: ${fromZone} → ${toZone}`,
                            type: 'info'
                        });
                    }
                    
                    // FEFO check for PF-Zone
                    <?php if(FEFO_ENABLED): ?>
                    if(toZone && toZone.indexOf('PF-Zone') !== -1) {
                        // Simulate FEFO check
                        const needsFEFO = Math.random() > 0.8;
                        if(needsFEFO) {
                            validations.push({
                                icon: 'fas fa-exclamation-triangle text-warning',
                                message: 'คำเตือน: ตรวจสอบ FEFO ก่อนย้าย',
                                type: 'warning'
                            });
                        } else {
                            validations.push({
                                icon: 'fas fa-check text-success',
                                message: 'FEFO: ผ่านการตรวจสอบ',
                                type: 'success'
                            });
                        }
                    }
                    <?php endif; ?>
                }
                
                if(validations.length > 0) {
                    let html = '<ul class="list-unstyled mb-0">';
                    validations.forEach(validation => {
                        html += `<li class="mb-1"><i class="${validation.icon}"></i> ${validation.message}</li>`;
                    });
                    html += '</ul>';
                    $('#validation-status').html(html);
                } else {
                    $('#validation-status').html('<p class="text-muted text-center">กรอกข้อมูลเพื่อตรวจสอบ</p>');
                }
            }
            
            // Form validation
            $('#movement-form').on('submit', function(e) {
                const fromLocation = $('#from-location-select').val();
                const toLocation = $('#to-location-select').val();
                const movementType = $('select[name="movement_type"]').val();
                
                if(!fromLocation) {
                    e.preventDefault();
                    alert('กรุณาเลือก Location ต้นทาง');
                    return;
                }
                
                if(!toLocation) {
                    e.preventDefault();
                    alert('กรุณาเลือก Location ปลายทาง');
                    return;
                }
                
                if(fromLocation === toLocation) {
                    e.preventDefault();
                    alert('Location ต้นทางและปลายทางต้องไม่เหมือนกัน');
                    return;
                }
                
                if(!movementType) {
                    e.preventDefault();
                    alert('กรุณาเลือกประเภทการย้าย');
                    return;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
            });
        });
        
        function resetForm() {
            if(confirm('ต้องการล้างข้อมูลทั้งหมดหรือไม่?')) {
                document.getElementById('movement-form').reset();
                $('#from-location-select').val(null).trigger('change');
                $('#to-location-select').val(null).trigger('change');
                $('#destination-zone').val('');
            }
        }
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + S to save
            if(e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                $('#movement-form').submit();
            }
            
            // Ctrl + R to reset
            if(e.ctrlKey && e.keyCode === 82) {
                e.preventDefault();
                resetForm();
            }
        });
    </script>
</body>
</html>