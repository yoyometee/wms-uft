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

// Check login and permissions
checkLogin();
checkPermission('office'); // Only office and admin can adjust inventory

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
        $required_fields = ['sku', 'adjustment_type', 'pieces_change', 'weight_change'];
        foreach($required_fields as $field) {
            if(!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("กรุณากรอกข้อมูล: " . $field);
            }
        }

        $sku = $_POST['sku'];
        $adjustment_type = $_POST['adjustment_type'];
        $pieces_change = (int)$_POST['pieces_change'];
        $weight_change = (float)$_POST['weight_change'];
        $reason = $_POST['reason'] ?? '';
        $location_id = $_POST['location_id'] ?? null;

        // Get product data
        $product_data = $product->getProductBySKU($sku);
        if(!$product_data) {
            throw new Exception("ไม่พบข้อมูลสินค้า SKU: " . $sku);
        }

        // Validate adjustment values
        if($pieces_change == 0 && $weight_change == 0) {
            throw new Exception("กรุณากรอกจำนวนที่ต้องการปรับ");
        }

        // Check if reducing and ensure we don't go below zero
        if($pieces_change < 0) {
            $new_pieces = $product_data['จำนวนถุง_ปกติ'] + $pieces_change;
            if($new_pieces < 0) {
                throw new Exception("จำนวนหลังปรับจะติดลบ (ปัจจุบัน: {$product_data['จำนวนถุง_ปกติ']}, ปรับ: {$pieces_change})");
            }
        }

        if($weight_change < 0) {
            $new_weight = $product_data['จำนวนน้ำหนัก_ปกติ'] + $weight_change;
            if($new_weight < 0) {
                throw new Exception("น้ำหนักหลังปรับจะติดลบ (ปัจจุบัน: {$product_data['จำนวนน้ำหนัก_ปกติ']}, ปรับ: {$weight_change})");
            }
        }

        // Prepare transaction data
        $transaction_table = $adjustment_type === 'location' ? 'adjust_by_location' : 'adjust_pf';
        $transaction_data = [
            'sub_type' => $reason ?: 'ปรับสต็อก',
            'sku' => $sku,
            'product_name' => $product_data['product_name'],
            'barcode' => $product_data['barcode'],
            'location_id' => $location_id,
            'pieces' => abs($pieces_change),
            'weight' => abs($weight_change),
            'remark' => $_POST['remark'] ?? '',
            'transaction_status' => $pieces_change >= 0 ? 'ปรับเพิ่ม' : 'ปรับลด'
        ];

        // Start transaction
        $db->beginTransaction();

        // Create adjustment transaction
        $result = $transaction->createAdjustmentTransaction($transaction_data, $adjustment_type);

        if($result['success']) {
            // Update product stock
            $product->adjustStock($sku, $pieces_change, $weight_change, 'ปกติ');

            // If location-based adjustment and location specified, update location
            if($adjustment_type === 'location' && $location_id) {
                $location_data = $location->getLocationById($location_id);
                if($location_data && $location_data['status'] === 'เก็บสินค้า' && $location_data['sku'] === $sku) {
                    // Update location quantities
                    $new_location_pieces = max(0, $location_data['ชิ้น'] + $pieces_change);
                    $new_location_weight = max(0, $location_data['น้ำหนัก'] + $weight_change);
                    
                    // If quantity becomes zero, clear the location
                    if($new_location_pieces <= 0 || $new_location_weight <= 0) {
                        $location->removePalletFromLocation($location_id);
                    } else {
                        // Update location with new quantities
                        $query = "UPDATE msaster_location_by_stock 
                                 SET ชิ้น = :pieces, น้ำหนัก = :weight, 
                                     name_edit = :name_edit, last_updated = :timestamp, updated_at = NOW()
                                 WHERE location_id = :location_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':pieces', $new_location_pieces);
                        $stmt->bindParam(':weight', $new_location_weight);
                        $stmt->bindParam(':name_edit', $current_user['ชื่อ_สกุล']);
                        $stmt->bindParam(':timestamp', time());
                        $stmt->bindParam(':location_id', $location_id);
                        $stmt->execute();
                    }
                }
            }

            $db->commit();

            $adjustment_text = $pieces_change >= 0 ? 'เพิ่ม' : 'ลด';
            $success_message = "✅ ปรับสต็อกสำเร็จ<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $result['tags_id'] . "<br>";
            $success_message .= "<strong>SKU:</strong> " . $sku . "<br>";
            $success_message .= "<strong>การปรับ:</strong> " . $adjustment_text . " " . abs($pieces_change) . " ชิ้น, " . abs($weight_change) . " กก.";

            // Log activity
            logActivity('INVENTORY_ADJUST', "Adjusted {$sku}: {$pieces_change} pieces, {$weight_change} kg. Reason: {$reason}", $current_user['user_id']);

        } else {
            throw new Exception($result['error']);
        }

    } catch(Exception $e) {
        $db->rollback();
        $error_message = $e->getMessage();
        logActivity('INVENTORY_ADJUST_ERROR', $e->getMessage(), $current_user['user_id']);
    }
}

// Get data for dropdowns
$products = $product->getAllProducts();
$occupied_locations = $location->getOccupiedLocations();

$page_title = 'ปรับสต็อก';
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
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="fas fa-adjust"></i> ปรับสต็อก (Inventory Adjustment)</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">ปรับสต็อก</li>
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
                        <h5><i class="fas fa-edit"></i> ฟอร์มปรับสต็อก</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="adjustment-form">
                            <div class="row">
                                <!-- SKU Selection -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SKU <span class="text-danger">*</span></label>
                                    <select name="sku" id="sku-select" class="form-select" required>
                                        <option value="">เลือก SKU</option>
                                        <?php foreach($products as $prod): ?>
                                        <option value="<?php echo $prod['sku']; ?>"
                                                data-name="<?php echo htmlspecialchars($prod['product_name']); ?>"
                                                data-current-pieces="<?php echo $prod['จำนวนถุง_ปกติ']; ?>"
                                                data-current-weight="<?php echo $prod['จำนวนน้ำหนัก_ปกติ']; ?>"
                                                data-unit="<?php echo htmlspecialchars($prod['unit']); ?>"
                                                data-min-stock="<?php echo $prod['min_stock']; ?>"
                                                data-max-stock="<?php echo $prod['max_stock']; ?>">
                                            <?php echo $prod['sku']; ?> - <?php echo htmlspecialchars($prod['product_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Adjustment Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ประเภทการปรับ <span class="text-danger">*</span></label>
                                    <select name="adjustment_type" id="adjustment-type" class="form-select" required>
                                        <option value="">เลือกประเภท</option>
                                        <option value="pf">ปรับสต็อก PF (Pick Face)</option>
                                        <option value="location">ปรับสต็อกตาม Location</option>
                                    </select>
                                </div>
                                
                                <!-- Location (only for location adjustment) -->
                                <div class="col-md-6 mb-3" id="location-field" style="display: none;">
                                    <label class="form-label">Location ID</label>
                                    <select name="location_id" id="location-select" class="form-select">
                                        <option value="">เลือก Location (ไม่บังคับ)</option>
                                        <?php foreach($occupied_locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>"
                                                data-sku="<?php echo htmlspecialchars($loc['sku']); ?>"
                                                data-pieces="<?php echo $loc['ชิ้น']; ?>"
                                                data-weight="<?php echo $loc['น้ำหนัก']; ?>">
                                            <?php echo $loc['location_id']; ?> - <?php echo htmlspecialchars($loc['sku']); ?> 
                                            (<?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Current Stock Display -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">สต็อกปัจจุบัน</label>
                                    <div class="input-group">
                                        <input type="text" id="current-pieces" class="form-control" readonly placeholder="จำนวนชิ้น">
                                        <span class="input-group-text">ชิ้น</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">น้ำหนักปัจจุบัน</label>
                                    <div class="input-group">
                                        <input type="text" id="current-weight" class="form-control" readonly placeholder="น้ำหนัก">
                                        <span class="input-group-text">กก.</span>
                                    </div>
                                </div>
                                
                                <!-- Adjustment Values -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ปรับจำนวน (ชิ้น) <span class="text-danger">*</span></label>
                                    <input type="number" name="pieces_change" id="pieces-change" class="form-control" step="1" required>
                                    <div class="form-text">ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ปรับน้ำหนัก (กก.) <span class="text-danger">*</span></label>
                                    <input type="number" name="weight_change" id="weight-change" class="form-control" step="0.01" required>
                                    <div class="form-text">ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด</div>
                                </div>
                                
                                <!-- Calculation Results -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ผลลัพธ์ (ชิ้น)</label>
                                    <div class="input-group">
                                        <input type="text" id="result-pieces" class="form-control" readonly>
                                        <span class="input-group-text">ชิ้น</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ผลลัพธ์ (น้ำหนัก)</label>
                                    <div class="input-group">
                                        <input type="text" id="result-weight" class="form-control" readonly>
                                        <span class="input-group-text">กก.</span>
                                    </div>
                                </div>
                                
                                <!-- Reason -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เหตุผลการปรับ <span class="text-danger">*</span></label>
                                    <select name="reason" class="form-select" required>
                                        <option value="">เลือกเหตุผล</option>
                                        <option value="ตรวจนับ Cycle Count">ตรวจนับ Cycle Count</option>
                                        <option value="ตรวจนับประจำปี">ตรวจนับประจำปี</option>
                                        <option value="สินค้าเสียหาย">สินค้าเสียหาย</option>
                                        <option value="สินค้าหมดอายุ">สินค้าหมดอายุ</option>
                                        <option value="สินค้าสูญหาย">สินค้าสูญหาย</option>
                                        <option value="ข้อผิดพลาดการบันทึก">ข้อผิดพลาดการบันทึก</option>
                                        <option value="ยอดยกมา">ยอดยกมา</option>
                                        <option value="การแปลงหน่วย">การแปลงหน่วย</option>
                                        <option value="อื่นๆ">อื่นๆ</option>
                                    </select>
                                </div>
                                
                                <!-- Product Name (Auto-filled) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อสินค้า</label>
                                    <input type="text" id="product-name" class="form-control" readonly>
                                </div>
                                
                                <!-- Remarks -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea name="remark" class="form-control" rows="3" placeholder="หมายเหตุเพิ่มเติม, เลขที่เอกสาร, รายละเอียดการตรวจนับ"></textarea>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> บันทึกการปรับสต็อก
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Info Panel -->
            <div class="col-lg-4">
                <!-- Stock Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> ข้อมูลสต็อก</h6>
                    </div>
                    <div class="card-body" id="stock-info">
                        <p class="text-muted text-center">เลือก SKU เพื่อดูข้อมูล</p>
                    </div>
                </div>

                <!-- Adjustment Preview -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-calculator"></i> ตัวอย่างการปรับ</h6>
                    </div>
                    <div class="card-body" id="adjustment-preview">
                        <p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>
                    </div>
                </div>
                
                <!-- Warnings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-exclamation-triangle"></i> คำเตือน</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning p-2 mb-2">
                            <small><i class="fas fa-exclamation-triangle"></i> การปรับสต็อกจะส่งผลต่อรายงานและการคำนวณ</small>
                        </div>
                        <div class="alert alert-info p-2 mb-0">
                            <small><i class="fas fa-info-circle"></i> ระบบจะบันทึกประวัติการปรับทุกครั้ง</small>
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
                                ตรวจสอบ SKU ให้ถูกต้อง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                ระบุเหตุผลการปรับให้ชัดเจน
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                ตรวจสอบจำนวนก่อนบันทึก
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning"></i> 
                                ระวังการปรับลดให้เกินสต็อกปัจจุบัน
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-info"></i> 
                                บันทึกเลขเอกสารในหมายเหตุ
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
                            <a href="../reports/stock.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-bar"></i> รายงานสต็อก
                            </a>
                            <a href="../reports/transactions.php?type=adjustment" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-list-alt"></i> ประวัติการปรับ
                            </a>
                            <a href="../receive/" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-truck-loading"></i> รับสินค้า
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
            $('#sku-select').select2({
                placeholder: 'ค้นหา SKU หรือชื่อสินค้า',
                allowClear: true,
                theme: 'bootstrap-5'
            });
            
            $('#location-select').select2({
                placeholder: 'ค้นหา Location',
                allowClear: true,
                theme: 'bootstrap-5'
            });
            
            // SKU change event
            $('#sku-select').change(function() {
                const selectedOption = $(this).find('option:selected');
                
                if(selectedOption.val()) {
                    const productName = selectedOption.data('name');
                    const currentPieces = selectedOption.data('current-pieces');
                    const currentWeight = selectedOption.data('current-weight');
                    const unit = selectedOption.data('unit');
                    const minStock = selectedOption.data('min-stock');
                    const maxStock = selectedOption.data('max-stock');
                    
                    $('#product-name').val(productName);
                    $('#current-pieces').val(currentPieces.toLocaleString());
                    $('#current-weight').val(currentWeight.toFixed(2));
                    
                    // Filter locations for this SKU
                    filterLocationsBySKU(selectedOption.val());
                    
                    // Update stock info panel
                    const stockStatus = getStockStatus(currentPieces, minStock, maxStock);
                    
                    $('#stock-info').html(`
                        <h6 class="text-primary">${selectedOption.val()}</h6>
                        <p class="mb-2">${productName}</p>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">ปัจจุบัน</small>
                                <div class="fw-bold">${currentPieces.toLocaleString()} ${unit}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">น้ำหนัก</small>
                                <div class="fw-bold">${currentWeight.toFixed(2)} กก.</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">ขั้นต่ำ</small>
                                <div class="fw-bold">${minStock.toLocaleString()}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">ขั้นสูง</small>
                                <div class="fw-bold">${maxStock.toLocaleString()}</div>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <span class="badge bg-${stockStatus.class}">${stockStatus.text}</span>
                        </div>
                    `);
                    
                    updateCalculation();
                } else {
                    clearForm();
                }
            });
            
            // Adjustment type change event
            $('#adjustment-type').change(function() {
                if($(this).val() === 'location') {
                    $('#location-field').show();
                } else {
                    $('#location-field').hide();
                    $('#location-select').val(null).trigger('change');
                }
            });
            
            // Input change events for calculation
            $('#pieces-change, #weight-change').on('input', function() {
                updateCalculation();
            });
            
            function filterLocationsBySKU(sku) {
                $('#location-select option').each(function() {
                    const optionSKU = $(this).data('sku');
                    if($(this).val() === '') {
                        $(this).show(); // Keep the empty option
                    } else if(optionSKU === sku) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                
                // Refresh Select2 to apply changes
                $('#location-select').trigger('change.select2');
            }
            
            function updateCalculation() {
                const selectedSKU = $('#sku-select').find('option:selected');
                const currentPieces = parseInt(selectedSKU.data('current-pieces')) || 0;
                const currentWeight = parseFloat(selectedSKU.data('current-weight')) || 0;
                const piecesChange = parseInt($('#pieces-change').val()) || 0;
                const weightChange = parseFloat($('#weight-change').val()) || 0;
                
                if(selectedSKU.val()) {
                    const resultPieces = currentPieces + piecesChange;
                    const resultWeight = currentWeight + weightChange;
                    
                    $('#result-pieces').val(resultPieces.toLocaleString());
                    $('#result-weight').val(resultWeight.toFixed(2));
                    
                    // Update preview
                    let changeType = '';
                    let changeClass = '';
                    if(piecesChange > 0 || weightChange > 0) {
                        changeType = 'เพิ่ม';
                        changeClass = 'success';
                    } else if(piecesChange < 0 || weightChange < 0) {
                        changeType = 'ลด';
                        changeClass = 'danger';
                    } else {
                        changeType = 'ไม่เปลี่ยนแปลง';
                        changeClass = 'secondary';
                    }
                    
                    let warningHtml = '';
                    if(resultPieces < 0 || resultWeight < 0) {
                        warningHtml = '<div class="alert alert-danger p-2 mt-2"><small>⚠️ ผลลัพธ์เป็นลบ!</small></div>';
                    } else if(resultPieces === 0 && resultWeight === 0) {
                        warningHtml = '<div class="alert alert-warning p-2 mt-2"><small>⚠️ สต็อกจะเป็น 0</small></div>';
                    }
                    
                    $('#adjustment-preview').html(`
                        <div class="text-center">
                            <div class="mb-2">
                                <span class="badge bg-${changeClass}">${changeType}</span>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">ก่อน</small>
                                    <div>${currentPieces.toLocaleString()} ชิ้น</div>
                                    <div>${currentWeight.toFixed(2)} กก.</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">หลัง</small>
                                    <div class="${resultPieces < 0 ? 'text-danger' : ''}">${resultPieces.toLocaleString()} ชิ้น</div>
                                    <div class="${resultWeight < 0 ? 'text-danger' : ''}">${resultWeight.toFixed(2)} กก.</div>
                                </div>
                            </div>
                            ${warningHtml}
                        </div>
                    `);
                } else {
                    $('#result-pieces').val('');
                    $('#result-weight').val('');
                    $('#adjustment-preview').html('<p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>');
                }
            }
            
            function getStockStatus(current, min, max) {
                if(current <= min) {
                    return {class: 'danger', text: 'ต่ำกว่าขั้นต่ำ'};
                } else if(current >= max) {
                    return {class: 'warning', text: 'สูงกว่าขั้นสูง'};
                } else {
                    return {class: 'success', text: 'ปกติ'};
                }
            }
            
            function clearForm() {
                $('#product-name').val('');
                $('#current-pieces').val('');
                $('#current-weight').val('');
                $('#result-pieces').val('');
                $('#result-weight').val('');
                $('#stock-info').html('<p class="text-muted text-center">เลือก SKU เพื่อดูข้อมูล</p>');
                $('#adjustment-preview').html('<p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>');
                $('#location-select option').show();
            }
            
            // Form validation
            $('#adjustment-form').on('submit', function(e) {
                const sku = $('#sku-select').val();
                const adjustmentType = $('#adjustment-type').val();
                const piecesChange = $('#pieces-change').val();
                const weightChange = $('#weight-change').val();
                const reason = $('select[name="reason"]').val();
                
                if(!sku) {
                    e.preventDefault();
                    alert('กรุณาเลือก SKU');
                    return;
                }
                
                if(!adjustmentType) {
                    e.preventDefault();
                    alert('กรุณาเลือกประเภทการปรับ');
                    return;
                }
                
                if(!piecesChange && !weightChange) {
                    e.preventDefault();
                    alert('กรุณากรอกจำนวนที่ต้องการปรับ');
                    return;
                }
                
                if(!reason) {
                    e.preventDefault();
                    alert('กรุณาเลือกเหตุผลการปรับ');
                    return;
                }
                
                // Check for negative results
                const selectedSKU = $('#sku-select').find('option:selected');
                const currentPieces = parseInt(selectedSKU.data('current-pieces')) || 0;
                const currentWeight = parseFloat(selectedSKU.data('current-weight')) || 0;
                const resultPieces = currentPieces + parseInt(piecesChange);
                const resultWeight = currentWeight + parseFloat(weightChange);
                
                if(resultPieces < 0 || resultWeight < 0) {
                    if(!confirm('ผลลัพธ์จะเป็นลบ ต้องการดำเนินการต่อหรือไม่?')) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
            });
        });
        
        function resetForm() {
            if(confirm('ต้องการล้างข้อมูลทั้งหมดหรือไม่?')) {
                document.getElementById('adjustment-form').reset();
                $('#sku-select').val(null).trigger('change');
                $('#location-select').val(null).trigger('change');
                $('#adjustment-type').val(null).trigger('change');
                $('#location-field').hide();
            }
        }
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + S to save
            if(e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                $('#adjustment-form').submit();
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