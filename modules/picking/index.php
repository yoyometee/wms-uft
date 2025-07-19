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
        $required_fields = ['location_id', 'quantity_picked', 'picking_type'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("กรุณากรอกข้อมูล: " . $field);
            }
        }

        // Get location data
        $location_data = $location->getLocationById($_POST['location_id']);
        if(!$location_data || $location_data['status'] !== 'เก็บสินค้า') {
            throw new Exception("❌ Location นี้ไม่มีสินค้า หรือไม่สามารถเบิกได้");
        }

        $quantity_picked = (int)$_POST['quantity_picked'];
        $weight_picked = (float)($_POST['weight_picked'] ?? 0);
        
        // Validate quantities
        if($quantity_picked <= 0 || $quantity_picked > $location_data['ชิ้น']) {
            throw new Exception("❌ จำนวนที่เบิกไม่ถูกต้อง (มีอยู่: {$location_data['ชิ้น']} ชิ้น)");
        }

        if($weight_picked <= 0 || $weight_picked > $location_data['น้ำหนัก']) {
            throw new Exception("❌ น้ำหนักที่เบิกไม่ถูกต้อง (มีอยู่: {$location_data['น้ำหนัก']} กก.)");
        }

        // Prepare transaction data
        $transaction_data = [
            'sub_type' => $_POST['picking_type'],
            'sku' => $location_data['sku'],
            'product_name' => $location_data['product_name'],
            'barcode' => $location_data['barcode'],
            'pallet_id' => $location_data['pallet_id'],
            'location_id' => $_POST['location_id'],
            'zone_location' => $location_data['zone'],
            'status_location' => 'จัดเตรียม',
            'pieces' => $quantity_picked,
            'weight' => $weight_picked,
            'lot' => $location_data['lot'],
            'received_date' => $location_data['received_date'],
            'expiration_date' => $location_data['expiration_date'],
            'pallet_color' => $location_data['สีพาเลท'],
            'customer_code' => $_POST['customer_code'] ?? '',
            'shop_name' => $_POST['shop_name'] ?? '',
            'เลขเอกสาร' => $_POST['document_no'] ?? '',
            'จุดที่' => $_POST['destination'] ?? '',
            'เลขงานจัดส่ง' => $_POST['delivery_no'] ?? '',
            'remark' => $_POST['remark'] ?? ''
        ];

        // Start transaction
        $db->beginTransaction();

        // Create picking transaction
        $result = $transaction->createPickingTransaction($transaction_data);

        if($result['success']) {
            // Update location (reduce quantity or clear if empty)
            $location->pickFromLocation($_POST['location_id'], $location_data['pallet_id'], $quantity_picked, $weight_picked);

            // Update product stock (reduce from normal stock)
            $product->adjustStock($location_data['sku'], -$quantity_picked, -$weight_picked, 'ปกติ');

            $db->commit();

            $success_message = "✅ จัดเตรียมสินค้าสำเร็จ<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $result['tags_id'] . "<br>";
            $success_message .= "<strong>จำนวนที่เบิก:</strong> " . formatNumber($quantity_picked) . " ชิ้น<br>";
            $success_message .= "<strong>น้ำหนัก:</strong> " . formatWeight($weight_picked);

            // Log activity
            logActivity('PICKING', "Picked {$quantity_picked} pieces of {$location_data['sku']} from {$_POST['location_id']}", $current_user['user_id']);

        } else {
            throw new Exception($result['error']);
        }

    } catch(Exception $e) {
        $db->rollback();
        $error_message = $e->getMessage();
        logActivity('PICKING_ERROR', $e->getMessage(), $current_user['user_id']);
    }
}

// Get pre-selected location or SKU from URL
$selected_location = $_GET['location'] ?? '';
$selected_sku = $_GET['sku'] ?? '';

// Get data for dropdowns
$occupied_locations = $location->getOccupiedLocations();
$products_in_stock = [];

// If SKU is selected, get its locations (FEFO order)
if($selected_sku) {
    $products_in_stock = $location->getLocationsBySKU($selected_sku);
}

$page_title = 'จัดเตรียมสินค้า';
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
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="fas fa-clipboard-list"></i> จัดเตรียมสินค้า (Picking)</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">จัดเตรียมสินค้า</li>
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
                        <h5><i class="fas fa-edit"></i> ฟอร์มจัดเตรียมสินค้า</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="picking-form">
                            <div class="row">
                                <!-- Location Selection -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location ID <span class="text-danger">*</span></label>
                                    <select name="location_id" id="location-select" class="form-select" required>
                                        <option value="">เลือก Location</option>
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
                                
                                <!-- Picking Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ประเภทการเบิก <span class="text-danger">*</span></label>
                                    <select name="picking_type" class="form-select" required>
                                        <option value="">เลือกประเภท</option>
                                        <option value="เบิกขาย">เบิกขาย</option>
                                        <option value="เบิกผลิต">เบิกผลิต</option>
                                        <option value="เบิกตัวอย่าง">เบิกตัวอย่าง</option>
                                        <option value="เบิกใช้ภายใน">เบิกใช้ภายใน</option>
                                        <option value="เบิกเสีย">เบิกเสีย</option>
                                        <option value="เบิกส่งคืน">เบิกส่งคืน</option>
                                    </select>
                                </div>
                                
                                <!-- Quantity Fields -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">จำนวนที่เบิก (ชิ้น) <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity_picked" id="quantity-picked" class="form-control" min="1" step="1" required>
                                    <div class="form-text">มีอยู่: <span id="available-pieces">-</span> ชิ้น</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">น้ำหนักที่เบิก (กก.) <span class="text-danger">*</span></label>
                                    <input type="number" name="weight_picked" id="weight-picked" class="form-control" step="0.01" min="0.01" required>
                                    <div class="form-text">มีอยู่: <span id="available-weight">-</span> กก.</div>
                                </div>
                                
                                <!-- Document Info -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เลขเอกสาร</label>
                                    <input type="text" name="document_no" class="form-control" placeholder="เลขเอกสารการเบิก">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เลขงานจัดส่ง</label>
                                    <input type="text" name="delivery_no" class="form-control" placeholder="เลขงานจัดส่ง">
                                </div>
                                
                                <!-- Customer Info -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">รหัสลูกค้า</label>
                                    <input type="text" name="customer_code" class="form-control" placeholder="รหัสลูกค้า">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อร้านค้า</label>
                                    <input type="text" name="shop_name" class="form-control" placeholder="ชื่อร้านค้า">
                                </div>
                                
                                <!-- Destination -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">จุดหมาย</label>
                                    <select name="destination" class="form-select">
                                        <option value="">เลือกจุดหมาย</option>
                                        <option value="ลานจัดส่ง">ลานจัดส่ง</option>
                                        <option value="โรงงาน">โรงงาน</option>
                                        <option value="คลังย่อย">คลังย่อย</option>
                                        <option value="ลูกค้า">ลูกค้า</option>
                                        <option value="ทำลาย">ทำลาย</option>
                                    </select>
                                </div>
                                
                                <!-- Current Location Info (Auto-filled) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zone ปัจจุบัน</label>
                                    <input type="text" id="current-zone" class="form-control" readonly>
                                </div>
                                
                                <!-- Product Info (Auto-filled) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SKU</label>
                                    <input type="text" id="current-sku" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อสินค้า</label>
                                    <input type="text" id="current-product-name" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pallet ID</label>
                                    <input type="text" id="current-pallet-id" class="form-control" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">LOT</label>
                                    <input type="text" id="current-lot" class="form-control" readonly>
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
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-hand-paper"></i> เบิกสินค้า
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Info Panel -->
            <div class="col-lg-4">
                <!-- Location Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> ข้อมูล Location</h6>
                    </div>
                    <div class="card-body" id="location-info">
                        <p class="text-muted text-center">เลือก Location เพื่อดูข้อมูล</p>
                    </div>
                </div>

                <!-- FEFO Status -->
                <?php if(FEFO_ENABLED): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-clock"></i> สถานะ FEFO</h6>
                    </div>
                    <div class="card-body" id="fefo-status">
                        <p class="text-muted text-center">เลือก Location เพื่อตรวจสอบ FEFO</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar"></i> สถิติ</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 text-success"><?php echo count($occupied_locations); ?></div>
                                <small class="text-muted">Locations มีสินค้า</small>
                            </div>
                            <div class="col-6">
                                <div class="h4 text-info">
                                    <?php
                                    $total_items = 0;
                                    foreach($occupied_locations as $loc) {
                                        $total_items += $loc['ชิ้น'];
                                    }
                                    echo formatNumber($total_items);
                                    ?>
                                </div>
                                <small class="text-muted">ชิ้นทั้งหมด</small>
                            </div>
                        </div>
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
                                ตรวจสอบ Location ให้ถูกต้อง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                เบิกตาม FEFO (หมดอายุก่อน เบิกก่อน)
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                ตรวจสอบจำนวนให้ถูกต้อง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning"></i> 
                                ระบุประเภทการเบิกให้ชัดเจน
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-info"></i> 
                                บันทึกเลขเอกสารเพื่อการตรวจสอบ
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
                            <a href="../movement/" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-exchange-alt"></i> ย้ายสินค้า
                            </a>
                            <a href="../reports/transactions.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-list-alt"></i> ประวัติการเบิก
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
            $('#location-select').select2({
                placeholder: 'ค้นหา Location',
                allowClear: true,
                theme: 'bootstrap-5'
            });
            
            // Auto-select first location if from URL
            <?php if($selected_location): ?>
            $('#location-select').val('<?php echo $selected_location; ?>').trigger('change');
            <?php endif; ?>
            
            // Location change event
            $('#location-select').change(function() {
                const selectedOption = $(this).find('option:selected');
                
                if(selectedOption.val()) {
                    const sku = selectedOption.data('sku');
                    const productName = selectedOption.data('product-name');
                    const pieces = selectedOption.data('pieces');
                    const weight = selectedOption.data('weight');
                    const palletId = selectedOption.data('pallet-id');
                    const lot = selectedOption.data('lot');
                    const expiry = selectedOption.data('expiry');
                    const zone = selectedOption.data('zone');
                    
                    // Update info display
                    $('#current-sku').val(sku);
                    $('#current-product-name').val(productName);
                    $('#current-pallet-id').val(palletId);
                    $('#current-lot').val(lot);
                    $('#current-zone').val(zone);
                    
                    $('#available-pieces').text(pieces.toLocaleString());
                    $('#available-weight').text(weight.toFixed(2));
                    
                    // Set max values for inputs
                    $('#quantity-picked').attr('max', pieces);
                    $('#weight-picked').attr('max', weight);
                    
                    // Clear current values
                    $('#quantity-picked').val('');
                    $('#weight-picked').val('');
                    
                    // Update location info panel
                    const expiryDate = new Date(expiry * 1000);
                    const daysToExpiry = Math.ceil((expiry * 1000 - Date.now()) / (1000 * 60 * 60 * 24));
                    let expiryStatus = '';
                    let expiryClass = '';
                    
                    if(daysToExpiry < 0) {
                        expiryStatus = 'หมดอายุแล้ว';
                        expiryClass = 'text-danger';
                    } else if(daysToExpiry <= 7) {
                        expiryStatus = 'หมดอายุใน ' + daysToExpiry + ' วัน';
                        expiryClass = 'text-danger';
                    } else if(daysToExpiry <= 30) {
                        expiryStatus = 'หมดอายุใน ' + daysToExpiry + ' วัน';
                        expiryClass = 'text-warning';
                    } else {
                        expiryStatus = 'ยังไม่หมดอายุ';
                        expiryClass = 'text-success';
                    }
                    
                    $('#location-info').html(`
                        <h6 class="text-primary">${selectedOption.val()}</h6>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">SKU</small>
                                <div class="fw-bold">${sku}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Zone</small>
                                <div class="fw-bold">${zone}</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">คงเหลือ</small>
                                <div class="fw-bold">${pieces.toLocaleString()} ชิ้น</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">น้ำหนัก</small>
                                <div class="fw-bold">${weight.toFixed(2)} กก.</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">วันหมดอายุ</small>
                                <div class="fw-bold ${expiryClass}">${expiryDate.toLocaleDateString('th-TH')}</div>
                                <small class="${expiryClass}">${expiryStatus}</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Pallet ID</small>
                                <div class="fw-bold">${palletId}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">LOT</small>
                                <div class="fw-bold">${lot || '-'}</div>
                            </div>
                        </div>
                    `);
                    
                    <?php if(FEFO_ENABLED): ?>
                    // Check FEFO status
                    checkFEFOStatus(sku, expiry);
                    <?php endif; ?>
                    
                } else {
                    clearLocationInfo();
                }
            });
            
            // Auto calculate weight based on quantity
            $('#quantity-picked').on('input', function() {
                calculateWeight();
            });
            
            function calculateWeight() {
                const selectedOption = $('#location-select').find('option:selected');
                const availableWeight = parseFloat(selectedOption.data('weight')) || 0;
                const availablePieces = parseInt(selectedOption.data('pieces')) || 0;
                const pickedPieces = parseInt($('#quantity-picked').val()) || 0;
                
                if(availablePieces > 0 && pickedPieces > 0) {
                    const weightPerPiece = availableWeight / availablePieces;
                    const totalWeight = (weightPerPiece * pickedPieces).toFixed(2);
                    $('#weight-picked').val(totalWeight);
                }
            }
            
            function clearLocationInfo() {
                $('#current-sku').val('');
                $('#current-product-name').val('');
                $('#current-pallet-id').val('');
                $('#current-lot').val('');
                $('#current-zone').val('');
                $('#available-pieces').text('-');
                $('#available-weight').text('-');
                $('#quantity-picked').removeAttr('max');
                $('#weight-picked').removeAttr('max');
                $('#location-info').html('<p class="text-muted text-center">เลือก Location เพื่อดูข้อมูล</p>');
                <?php if(FEFO_ENABLED): ?>
                $('#fefo-status').html('<p class="text-muted text-center">เลือก Location เพื่อตรวจสอบ FEFO</p>');
                <?php endif; ?>
            }
            
            <?php if(FEFO_ENABLED): ?>
            function checkFEFOStatus(sku, expiry) {
                // Simple FEFO check - in real implementation, this would be an AJAX call
                const warning = Math.random() > 0.8; // Simulate FEFO warning
                
                if(warning) {
                    $('#fefo-status').html(`
                        <div class="alert alert-warning p-2 mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>คำเตือน FEFO</strong><br>
                            <small>มีสินค้าที่หมดอายุก่อนในตำแหน่งอื่น ควรเบิกก่อน</small>
                        </div>
                    `);
                } else {
                    $('#fefo-status').html(`
                        <div class="alert alert-success p-2 mb-0">
                            <i class="fas fa-check"></i>
                            <strong>FEFO OK</strong><br>
                            <small>สามารถเบิกจากตำแหน่งนี้ได้</small>
                        </div>
                    `);
                }
            }
            <?php endif; ?>
            
            // Form validation
            $('#picking-form').on('submit', function(e) {
                const locationId = $('#location-select').val();
                const quantity = $('#quantity-picked').val();
                const weight = $('#weight-picked').val();
                const pickingType = $('select[name="picking_type"]').val();
                
                if(!locationId) {
                    e.preventDefault();
                    alert('กรุณาเลือก Location');
                    return;
                }
                
                if(!quantity || quantity <= 0) {
                    e.preventDefault();
                    alert('กรุณากรอกจำนวนที่เบิก');
                    return;
                }
                
                if(!weight || weight <= 0) {
                    e.preventDefault();
                    alert('กรุณากรอกน้ำหนักที่เบิก');
                    return;
                }
                
                if(!pickingType) {
                    e.preventDefault();
                    alert('กรุณาเลือกประเภทการเบิก');
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
                document.getElementById('picking-form').reset();
                $('#location-select').val(null).trigger('change');
            }
        }
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + S to save
            if(e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                $('#picking-form').submit();
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