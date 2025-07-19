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
        $required_fields = ['sku', 'location_id', 'pieces', 'weight', 'expiration_date'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("กรุณากรอกข้อมูล: " . $field);
            }
        }

        // Validate SKU
        $product_data = $product->getProductBySKU($_POST['sku']);
        if(!$product_data) {
            throw new Exception("ไม่พบข้อมูลสินค้า SKU: " . $_POST['sku']);
        }

        // Validate Location
        $location_data = $location->getLocationById($_POST['location_id']);
        if(!$location_data || $location_data['status'] != 'ว่าง') {
            throw new Exception("❌ Location นี้ไม่ว่าง หรือไม่สามารถใช้งานได้");
        }

        // Validate expiration date
        $expiry_timestamp = strtotime($_POST['expiration_date']);
        if(!$expiry_timestamp) {
            throw new Exception("รูปแบบวันหมดอายุไม่ถูกต้อง");
        }

        // Prepare transaction data
        $transaction_data = [
            'sub_type' => $_POST['sub_type'] ?? 'รับสินค้าทั่วไป',
            'sku' => $_POST['sku'],
            'product_name' => $product_data['product_name'],
            'barcode' => $product_data['barcode'],
            'location_id' => $_POST['location_id'],
            'zone_location' => $location_data['zone'],
            'status_location' => 'เก็บสินค้า',
            'packs' => (int)($_POST['packs'] ?? 0),
            'pieces' => (int)$_POST['pieces'],
            'weight' => (float)$_POST['weight'],
            'lot' => $_POST['lot'] ?? '',
            'customer_code' => $_POST['customer_code'] ?? '',
            'shop_name' => $_POST['shop_name'] ?? '',
            'received_date' => time(),
            'expiration_date' => $expiry_timestamp,
            'vehicle_no' => $_POST['vehicle_no'] ?? '',
            'pallet_color' => $_POST['pallet_color'] ?? '',
            'remark' => $_POST['remark'] ?? '',
            'number_pallet' => $_POST['number_pallet'] ?? '1'
        ];

        // Start transaction
        $db->beginTransaction();

        // Create receive transaction
        $result = $transaction->createReceiveTransaction($transaction_data);

        if($result['success']) {
            // Prepare location data
            $location_product_data = [
                'product_name' => $product_data['product_name'],
                'packs' => $transaction_data['packs'],
                'pieces' => $transaction_data['pieces'],
                'weight' => $transaction_data['weight'],
                'lot' => $transaction_data['lot'],
                'received_date' => $transaction_data['received_date'],
                'expiration_date' => $transaction_data['expiration_date'],
                'pallet_color' => $transaction_data['pallet_color'],
                'remark' => $transaction_data['remark']
            ];

            // Assign pallet to location
            $location->assignPalletToLocation(
                $_POST['location_id'], 
                $result['pallet_id'], 
                $_POST['sku'], 
                $location_product_data
            );

            // Update product stock
            $product->adjustStock($_POST['sku'], $transaction_data['pieces'], $transaction_data['weight'], 'ปกติ');

            $db->commit();

            $success_message = "✅ รับสินค้าสำเร็จ<br>";
            $success_message .= "<strong>Pallet ID:</strong> " . $result['pallet_id'] . "<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $result['tags_id'] . "<br>";
            $success_message .= "<strong>Location:</strong> " . $_POST['location_id'];

            // Log activity
            logActivity('RECEIVE', "Received {$transaction_data['pieces']} pieces of {$_POST['sku']} to {$_POST['location_id']}", $current_user['user_id']);

        } else {
            throw new Exception($result['error']);
        }

    } catch(Exception $e) {
        $db->rollback();
        $error_message = $e->getMessage();
        logActivity('RECEIVE_ERROR', $e->getMessage(), $current_user['user_id']);
    }
}

// Get data for dropdowns
$products = $product->getAllProducts();
$available_locations = $location->getAvailableLocations();
$zones = $location->getZones();

// Get pre-selected SKU from URL
$selected_sku = $_GET['sku'] ?? '';

$page_title = 'รับสินค้า';
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
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="fas fa-truck-loading"></i> รับสินค้าเข้าคลัง</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">รับสินค้า</li>
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
                        <h5><i class="fas fa-edit"></i> ฟอร์มรับสินค้า</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="receive-form">
                            <div class="row">
                                <!-- SKU Selection -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SKU <span class="text-danger">*</span></label>
                                    <select name="sku" id="sku-select" class="form-select" required>
                                        <option value="">เลือก SKU</option>
                                        <?php foreach($products as $prod): ?>
                                        <option value="<?php echo $prod['sku']; ?>" 
                                                data-name="<?php echo htmlspecialchars($prod['product_name']); ?>"
                                                data-barcode="<?php echo htmlspecialchars($prod['barcode']); ?>"
                                                data-weight="<?php echo $prod['น้ำหนัก_ต่อ_ถุง']; ?>"
                                                data-bags-per-pack="<?php echo $prod['จำนวนถุง_ต่อ_แพ็ค']; ?>"
                                                data-unit="<?php echo htmlspecialchars($prod['unit']); ?>"
                                                <?php echo ($selected_sku == $prod['sku']) ? 'selected' : ''; ?>>
                                            <?php echo $prod['sku']; ?> - <?php echo htmlspecialchars($prod['product_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Product Name (Auto-filled) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อสินค้า</label>
                                    <input type="text" id="product-name" class="form-control" readonly>
                                </div>
                                
                                <!-- Location Selection -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location ID <span class="text-danger">*</span></label>
                                    <select name="location_id" id="location-select" class="form-select" required>
                                        <option value="">เลือก Location</option>
                                        <?php foreach($available_locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>"
                                                data-zone="<?php echo htmlspecialchars($loc['zone']); ?>"
                                                data-max-weight="<?php echo $loc['max_weight']; ?>">
                                            <?php echo $loc['location_id']; ?> (<?php echo htmlspecialchars($loc['zone']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Receive Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ประเภทการรับ</label>
                                    <select name="sub_type" class="form-select">
                                        <option value="รับสินค้าทั่วไป">รับสินค้าทั่วไป</option>
                                        <option value="รับสินค้าจากรีแพ็ค">รับสินค้าจากรีแพ็ค</option>
                                        <option value="ปรับเข้า (ยอดยกมา)">ปรับเข้า (ยอดยกมา)</option>
                                        <option value="รับคืนจากลูกค้า">รับคืนจากลูกค้า</option>
                                    </select>
                                </div>
                                
                                <!-- Quantity Fields -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">จำนวนแพ็ค</label>
                                    <input type="number" name="packs" id="packs" class="form-control" min="0" step="1" value="0">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">จำนวนชิ้น <span class="text-danger">*</span></label>
                                    <input type="number" name="pieces" id="pieces" class="form-control" min="1" step="1" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">น้ำหนัก (กก.) <span class="text-danger">*</span></label>
                                    <input type="number" name="weight" id="weight" class="form-control" step="0.01" min="0.01" required>
                                </div>
                                
                                <!-- LOT and Expiry -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">LOT</label>
                                    <input type="text" name="lot" class="form-control" placeholder="หมายเลข LOT">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">วันหมดอายุ <span class="text-danger">*</span></label>
                                    <input type="date" name="expiration_date" class="form-control" required>
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
                                
                                <!-- Additional Info -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">สีพาเลท</label>
                                    <select name="pallet_color" class="form-select">
                                        <option value="">เลือกสี</option>
                                        <option value="เขียว">เขียว</option>
                                        <option value="แดง">แดง</option>
                                        <option value="น้ำเงิน">น้ำเงิน</option>
                                        <option value="เหลือง">เหลือง</option>
                                        <option value="ขาว">ขาว</option>
                                        <option value="ดำ">ดำ</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">คันที่</label>
                                    <input type="text" name="vehicle_no" class="form-control" placeholder="หมายเลขคัน">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">จำนวนพาเลท</label>
                                    <input type="number" name="number_pallet" class="form-control" min="1" value="1">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Barcode</label>
                                    <input type="text" id="barcode-display" class="form-control" readonly>
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Info Panel -->
            <div class="col-lg-4">
                <!-- Product Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> ข้อมูลสินค้า</h6>
                    </div>
                    <div class="card-body" id="product-info">
                        <p class="text-muted text-center">เลือก SKU เพื่อดูข้อมูล</p>
                    </div>
                </div>
                
                <!-- Location Stats -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="fas fa-map-marker-alt"></i> สถานะ Location</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 text-success"><?php echo count($available_locations); ?></div>
                                <small class="text-muted">ตำแหน่งว่าง</small>
                            </div>
                            <div class="col-6">
                                <div class="h4 text-info"><?php echo count($zones); ?></div>
                                <small class="text-muted">Zone ทั้งหมด</small>
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
                                ตรวจสอบ SKU ให้ถูกต้อง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                เลือก Location ที่ว่าง
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i> 
                                กรอกวันหมดอายุให้ถูกต้อง
                            </li>
                            <?php if(FEFO_ENABLED): ?>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning"></i> 
                                ระบบจะตรวจสอบ FEFO อัตโนมัติ
                            </li>
                            <?php endif; ?>
                            <li class="mb-2">
                                <i class="fas fa-calculator text-info"></i> 
                                น้ำหนักจะคำนวณอัตโนมัติ
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
                            <a href="../picking/" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-hand-paper"></i> จัดเตรียมสินค้า
                            </a>
                            <a href="../movement/" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-exchange-alt"></i> ย้ายสินค้า
                            </a>
                            <a href="../inventory/" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-adjust"></i> ปรับสต็อก
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
            
            // Auto-select first SKU if from URL
            <?php if($selected_sku): ?>
            $('#sku-select').val('<?php echo $selected_sku; ?>').trigger('change');
            <?php endif; ?>
            
            // SKU change event
            $('#sku-select').change(function() {
                const selectedOption = $(this).find('option:selected');
                const productName = selectedOption.data('name');
                const barcode = selectedOption.data('barcode');
                const weight = selectedOption.data('weight');
                const bagsPerPack = selectedOption.data('bags-per-pack');
                const unit = selectedOption.data('unit');
                
                $('#product-name').val(productName);
                $('#barcode-display').val(barcode);
                
                if(productName) {
                    $('#product-info').html(`
                        <h6 class="text-primary">${productName}</h6>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">น้ำหนัก/ถุง</small>
                                <div class="fw-bold">${weight} กก.</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">ถุง/แพ็ค</small>
                                <div class="fw-bold">${bagsPerPack} ถุง</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">SKU</small>
                                <div class="fw-bold">${$(this).val()}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">หน่วย</small>
                                <div class="fw-bold">${unit}</div>
                            </div>
                        </div>
                        ${barcode ? `<hr><div><small class="text-muted">Barcode</small><div class="fw-bold">${barcode}</div></div>` : ''}
                    `);
                } else {
                    $('#product-info').html('<p class="text-muted text-center">เลือก SKU เพื่อดูข้อมูล</p>');
                }
            });
            
            // Auto calculate weight based on pieces
            $('#pieces').on('input', function() {
                calculateWeight();
            });
            
            function calculateWeight() {
                const selectedOption = $('#sku-select').find('option:selected');
                const weightPerBag = parseFloat(selectedOption.data('weight')) || 0;
                const pieces = parseInt($('#pieces').val()) || 0;
                
                if(weightPerBag > 0 && pieces > 0) {
                    const totalWeight = (weightPerBag * pieces).toFixed(2);
                    $('#weight').val(totalWeight);
                }
            }
            
            // Form validation
            $('#receive-form').on('submit', function(e) {
                const sku = $('#sku-select').val();
                const locationId = $('#location-select').val();
                const pieces = $('#pieces').val();
                const weight = $('#weight').val();
                const expiryDate = $('input[name="expiration_date"]').val();
                
                if(!sku) {
                    e.preventDefault();
                    alert('กรุณาเลือก SKU');
                    return;
                }
                
                if(!locationId) {
                    e.preventDefault();
                    alert('กรุณาเลือก Location');
                    return;
                }
                
                if(!pieces || pieces <= 0) {
                    e.preventDefault();
                    alert('กรุณากรอกจำนวนชิ้น');
                    return;
                }
                
                if(!weight || weight <= 0) {
                    e.preventDefault();
                    alert('กรุณากรอกน้ำหนัก');
                    return;
                }
                
                if(!expiryDate) {
                    e.preventDefault();
                    alert('กรุณาเลือกวันหมดอายุ');
                    return;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
            });
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            $('input[name="expiration_date"]').attr('min', today);
        });
        
        function resetForm() {
            if(confirm('ต้องการล้างข้อมูลทั้งหมดหรือไม่?')) {
                document.getElementById('receive-form').reset();
                $('#sku-select').val(null).trigger('change');
                $('#location-select').val(null).trigger('change');
                $('#product-info').html('<p class="text-muted text-center">เลือก SKU เพื่อดูข้อมูล</p>');
                $('#product-name').val('');
                $('#barcode-display').val('');
            }
        }
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + S to save
            if(e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                $('#receive-form').submit();
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