<?php
require_once '../../config/app_config.php';
require_once '../../includes/master_layout.php';

// Check login
checkLogin();

$page_title = 'ย้ายสินค้า';
$success_message = '';
$error_message = '';

// Get database connection
$db = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'movement_operation') {
    // Validate CSRF token
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            // Validate required fields
            $required_fields = ['from_location_id', 'to_location_id', 'movement_type'];
            foreach($required_fields as $field) {
                if(empty($_POST[$field])) {
                    throw new Exception("กรุณากรอกข้อมูล: " . $field);
                }
            }

            $from_location = trim($_POST['from_location_id']);
            $to_location = trim($_POST['to_location_id']);
            $movement_type = trim($_POST['movement_type']);
            $reason = trim($_POST['reason'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            if($from_location === $to_location) {
                throw new Exception("❌ Location ต้นทางและปลายทางต้องไม่เหมือนกัน");
            }

            // Get source location data
            $stmt = $db->prepare("
                SELECT location_id, sku, product_name, barcode, pallet_id, จำนวนถุง_ปกติ, ชิ้น, น้ำหนัก, 
                       lot, received_date, expiration_date, สีพาเลท, zone, status
                FROM msaster_location_by_stock 
                WHERE location_id = ? AND status = 'เก็บสินค้า'
            ");
            $stmt->execute([$from_location]);
            $from_location_data = $stmt->fetch();

            if (!$from_location_data) {
                throw new Exception("❌ Location ต้นทางไม่มีสินค้า");
            }

            // Get destination location data
            $stmt = $db->prepare("
                SELECT location_id, zone, status, max_weight
                FROM msaster_location_by_stock 
                WHERE location_id = ? AND status = 'ว่าง'
            ");
            $stmt->execute([$to_location]);
            $to_location_data = $stmt->fetch();

            if (!$to_location_data) {
                throw new Exception("❌ Location ปลายทางไม่ว่าง");
            }

            // Check weight capacity if available
            if (isset($to_location_data['max_weight']) && $to_location_data['max_weight'] > 0) {
                if ($from_location_data['น้ำหนัก'] > $to_location_data['max_weight']) {
                    throw new Exception("❌ น้ำหนักเกินกำหนดของ Location ปลายทาง");
                }
            }

            // Generate Tags ID
            $tags_id = 'MV' . date('ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Start transaction
            $db->beginTransaction();

            // Create movement transaction
            $stmt = $db->prepare("
                INSERT INTO transaction_product_flow 
                (tags_id, sku, product_name, barcode, pallet_id, location_id, 
                 zone_location, status_location, จำนวนถุง_ปกติ, ชิ้น, น้ำหนัก, 
                 lot, received_date, expiration_date, สีพาเลท, ประเภทหลัก, ประเภทย่อย,
                 remark, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'เก็บสินค้า', ?, ?, ?, ?, ?, ?, ?, 'ย้าย', ?, 
                        ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tags_id, $from_location_data['sku'], $from_location_data['product_name'], 
                $from_location_data['barcode'], $from_location_data['pallet_id'], $to_location,
                $to_location_data['zone'], $from_location_data['จำนวนถุง_ปกติ'], 
                $from_location_data['ชิ้น'], $from_location_data['น้ำหนัก'],
                $from_location_data['lot'], $from_location_data['received_date'], 
                $from_location_data['expiration_date'], $from_location_data['สีพาเลท'],
                $movement_type, "ย้ายจาก {$from_location} ไป {$to_location}. {$reason}. {$remark}", 
                $_SESSION['user_id']
            ]);

            // Clear source location
            $stmt = $db->prepare("
                UPDATE msaster_location_by_stock 
                SET sku = NULL, product_name = NULL, barcode = NULL, pallet_id = NULL,
                    จำนวนถุง_ปกติ = 0, ชิ้น = 0, น้ำหนัก = 0, lot = NULL,
                    received_date = NULL, expiration_date = NULL, สีพาเลท = NULL,
                    status = 'ว่าง', name_edit = ?, last_updated = ?, updated_at = NOW()
                WHERE location_id = ?
            ");
            $stmt->execute([$_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'], time(), $from_location]);

            // Move to destination location
            $stmt = $db->prepare("
                UPDATE msaster_location_by_stock 
                SET sku = ?, product_name = ?, barcode = ?, pallet_id = ?,
                    จำนวนถุง_ปกติ = ?, ชิ้น = ?, น้ำหนัก = ?, lot = ?,
                    received_date = ?, expiration_date = ?, สีพาเลท = ?,
                    status = 'เก็บสินค้า', name_edit = ?, last_updated = ?, updated_at = NOW()
                WHERE location_id = ?
            ");
            $stmt->execute([
                $from_location_data['sku'], $from_location_data['product_name'], 
                $from_location_data['barcode'], $from_location_data['pallet_id'],
                $from_location_data['จำนวนถุง_ปกติ'], $from_location_data['ชิ้น'], 
                $from_location_data['น้ำหนัก'], $from_location_data['lot'],
                $from_location_data['received_date'], $from_location_data['expiration_date'], 
                $from_location_data['สีพาเลท'], $_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'], 
                time(), $to_location
            ]);

            $db->commit();

            $success_message = "✅ ย้ายสินค้าสำเร็จ!<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $tags_id . "<br>";
            $success_message .= "<strong>จาก:</strong> " . $from_location . "<br>";
            $success_message .= "<strong>ไป:</strong> " . $to_location . "<br>";
            $success_message .= "<strong>Pallet ID:</strong> " . $from_location_data['pallet_id'];

            // Reset form values
            $_POST = [];

        } catch(Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error_message = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// Get data for dropdowns
try {
    $stmt = $db->query("
        SELECT location_id, sku, product_name, ชิ้น, น้ำหนัก, pallet_id, lot, 
               expiration_date, zone, status
        FROM msaster_location_by_stock 
        WHERE status = 'เก็บสินค้า' 
        ORDER BY zone, location_id 
        LIMIT 100
    ");
    $occupied_locations = $stmt->fetchAll();
} catch(Exception $e) {
    $occupied_locations = [];
}

try {
    $stmt = $db->query("
        SELECT location_id, zone, status, max_weight
        FROM msaster_location_by_stock 
        WHERE status = 'ว่าง' 
        ORDER BY zone, location_id 
        LIMIT 100
    ");
    $available_locations = $stmt->fetchAll();
} catch(Exception $e) {
    $available_locations = [];
}

ob_start();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-exchange-alt"></i> ย้ายสินค้า (Movement)</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                            <li class="breadcrumb-item active">ย้ายสินค้า</li>
                        </ol>
                    </nav>
                </div>
                <div class="card-body">
                    <?php if($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3 needs-validation" novalidate>
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="movement_operation">
                        
                        <div class="col-md-6">
                            <label class="form-label">Location ต้นทาง <span class="text-danger">*</span></label>
                            <select name="from_location_id" id="from-location-select" class="form-select" required>
                                <option value="">เลือก Location ต้นทาง</option>
                                <?php foreach($occupied_locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_id']); ?>"
                                            data-sku="<?php echo htmlspecialchars($loc['sku']); ?>"
                                            data-product-name="<?php echo htmlspecialchars($loc['product_name']); ?>"
                                            data-pieces="<?php echo $loc['ชิ้น']; ?>"
                                            data-weight="<?php echo $loc['น้ำหนัก']; ?>"
                                            data-pallet-id="<?php echo htmlspecialchars($loc['pallet_id']); ?>"
                                            data-lot="<?php echo htmlspecialchars($loc['lot']); ?>"
                                            data-expiry="<?php echo $loc['expiration_date']; ?>"
                                            data-zone="<?php echo htmlspecialchars($loc['zone']); ?>"
                                            <?php echo (($_POST['from_location_id'] ?? '') === $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo $loc['location_id']; ?> - <?php echo htmlspecialchars($loc['sku']); ?> 
                                        (<?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือก Location ต้นทาง</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Location ปลายทาง <span class="text-danger">*</span></label>
                            <select name="to_location_id" id="to-location-select" class="form-select" required>
                                <option value="">เลือก Location ปลายทาง</option>
                                <?php foreach($available_locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_id']); ?>"
                                            data-zone="<?php echo htmlspecialchars($loc['zone']); ?>"
                                            data-max-weight="<?php echo $loc['max_weight'] ?? 0; ?>"
                                            <?php echo (($_POST['to_location_id'] ?? '') === $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo $loc['location_id']; ?> (<?php echo htmlspecialchars($loc['zone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือก Location ปลายทาง</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ประเภทการย้าย <span class="text-danger">*</span></label>
                            <select name="movement_type" class="form-select" required>
                                <option value="">เลือกประเภท</option>
                                <option value="ย้ายไป PF-Zone" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายไป PF-Zone') ? 'selected' : ''; ?>>ย้ายไป PF-Zone</option>
                                <option value="ย้ายออกจาก PF-Zone" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายออกจาก PF-Zone') ? 'selected' : ''; ?>>ย้ายออกจาก PF-Zone</option>
                                <option value="ย้ายใน Zone เดียวกัน" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายใน Zone เดียวกัน') ? 'selected' : ''; ?>>ย้ายใน Zone เดียวกัน</option>
                                <option value="ย้ายไป Zone อื่น" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายไป Zone อื่น') ? 'selected' : ''; ?>>ย้ายไป Zone อื่น</option>
                                <option value="ย้ายไป Premium Zone" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายไป Premium Zone') ? 'selected' : ''; ?>>ย้ายไป Premium Zone</option>
                                <option value="ย้ายไป Packaging Zone" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายไป Packaging Zone') ? 'selected' : ''; ?>>ย้ายไป Packaging Zone</option>
                                <option value="ย้ายไป Damaged Zone" <?php echo (($_POST['movement_type'] ?? '') === 'ย้ายไป Damaged Zone') ? 'selected' : ''; ?>>ย้ายไป Damaged Zone</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกประเภทการย้าย</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">เหตุผลการย้าย</label>
                            <select name="reason" class="form-select">
                                <option value="">เลือกเหตุผล</option>
                                <option value="จัดเรียงใหม่" <?php echo (($_POST['reason'] ?? '') === 'จัดเรียงใหม่') ? 'selected' : ''; ?>>จัดเรียงใหม่</option>
                                <option value="เตรียมเบิก" <?php echo (($_POST['reason'] ?? '') === 'เตรียมเบิก') ? 'selected' : ''; ?>>เตรียมเบิก</option>
                                <option value="เปลี่ยน Zone" <?php echo (($_POST['reason'] ?? '') === 'เปลี่ยน Zone') ? 'selected' : ''; ?>>เปลี่ยน Zone</option>
                                <option value="ซ่อมแซม Location" <?php echo (($_POST['reason'] ?? '') === 'ซ่อมแซม Location') ? 'selected' : ''; ?>>ซ่อมแซม Location</option>
                                <option value="ตรวจสอบสินค้า" <?php echo (($_POST['reason'] ?? '') === 'ตรวจสอบสินค้า') ? 'selected' : ''; ?>>ตรวจสอบสินค้า</option>
                                <option value="จัดเก็บใหม่" <?php echo (($_POST['reason'] ?? '') === 'จัดเก็บใหม่') ? 'selected' : ''; ?>>จัดเก็บใหม่</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ข้อมูลต้นทาง</label>
                            <div class="card">
                                <div class="card-body p-2" id="source-info">
                                    <small class="text-muted">เลือก Location ต้นทางเพื่อดูข้อมูล</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ข้อมูลปลายทาง</label>
                            <div class="card">
                                <div class="card-body p-2" id="destination-info">
                                    <small class="text-muted">เลือก Location ปลายทางเพื่อดูข้อมูล</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="remark" class="form-control" rows="3" 
                                      placeholder="หมายเหตุเพิ่มเติม"><?php echo htmlspecialchars($_POST['remark'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <hr class="my-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-exchange-alt"></i> ย้ายสินค้า
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <a href="../../" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                                </a>
                                <a href="../reports/transactions.php" class="btn btn-outline-info">
                                    <i class="fas fa-list-alt"></i> ประวัติการย้าย
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Movement Preview Panel -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-eye"></i> ตัวอย่างการย้าย</h6>
                </div>
                <div class="card-body" id="movement-preview">
                    <p class="text-muted text-center">เลือก Location เพื่อดูตัวอย่าง</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> คำแนะนำ</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบ Location ต้นทางและปลายทาง</li>
                        <li><i class="fas fa-check text-success"></i> ให้เหตุผลการย้ายที่ชัดเจน</li>
                        <?php if(FEFO_ENABLED): ?>
                        <li><i class="fas fa-exclamation-triangle text-warning"></i> ระบบจะตรวจสอบ FEFO อัตโนมัติ</li>
                        <?php endif; ?>
                        <li><i class="fas fa-info-circle text-info"></i> ย้ายทั้ง Pallet ในครั้งเดียว</li>
                        <li><i class="fas fa-database text-primary"></i> บันทึกประวัติการย้ายอัตโนมัติ</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> สถิติการย้ายสินค้าวันนี้</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $db->query("
                            SELECT 
                                COUNT(*) as total_movements,
                                COUNT(DISTINCT sku) as unique_skus,
                                SUM(ชิ้น) as total_pieces,
                                SUM(น้ำหนัก) as total_weight
                            FROM transaction_product_flow 
                            WHERE ประเภทหลัก = 'ย้าย' 
                            AND DATE(created_at) = CURDATE()
                        ");
                        $today_stats = $stmt->fetch();
                    ?>
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="h4 text-warning"><?php echo formatNumber($today_stats['total_movements'] ?? 0); ?></div>
                                <small class="text-muted">รายการย้าย</small>
                            </div>
                            <div class="col-3">
                                <div class="h4 text-primary"><?php echo formatNumber($today_stats['unique_skus'] ?? 0); ?></div>
                                <small class="text-muted">SKU ที่ย้าย</small>
                            </div>
                            <div class="col-3">
                                <div class="h4 text-success"><?php echo formatNumber($today_stats['total_pieces'] ?? 0); ?></div>
                                <small class="text-muted">ชิ้น</small>
                            </div>
                            <div class="col-3">
                                <div class="h4 text-info"><?php echo formatWeight($today_stats['total_weight'] ?? 0); ?></div>
                                <small class="text-muted">น้ำหนัก</small>
                            </div>
                        </div>
                    <?php
                    } catch(Exception $e) {
                        echo '<p class="text-muted text-center">ไม่สามารถโหลดสถิติได้</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and JavaScript functionality
(function() {
    'use strict';
    
    // Bootstrap validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Location selection handlers
    const fromLocationSelect = document.getElementById('from-location-select');
    const toLocationSelect = document.getElementById('to-location-select');
    const sourceInfo = document.getElementById('source-info');
    const destinationInfo = document.getElementById('destination-info');
    const movementPreview = document.getElementById('movement-preview');
    
    if (fromLocationSelect) {
        fromLocationSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const sku = selectedOption.dataset.sku;
                const productName = selectedOption.dataset.productName;
                const pieces = parseInt(selectedOption.dataset.pieces);
                const weight = parseFloat(selectedOption.dataset.weight);
                const palletId = selectedOption.dataset.palletId;
                const lot = selectedOption.dataset.lot;
                const expiry = selectedOption.dataset.expiry;
                const zone = selectedOption.dataset.zone;
                
                // Update source info
                const expiryDate = expiry ? new Date(expiry * 1000).toLocaleDateString('th-TH') : '-';
                sourceInfo.innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">SKU:</small><br>
                            <strong>${sku}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Zone:</small><br>
                            <strong>${zone}</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">จำนวน:</small><br>
                            <strong>${pieces.toLocaleString()} ชิ้น</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">น้ำหนัก:</small><br>
                            <strong>${weight.toFixed(2)} กก.</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <small class="text-muted">Pallet ID:</small><br>
                    <strong>${palletId}</strong>
                `;
                
                updateMovementPreview();
            } else {
                sourceInfo.innerHTML = '<small class="text-muted">เลือก Location ต้นทางเพื่อดูข้อมูล</small>';
                updateMovementPreview();
            }
        });
    }
    
    if (toLocationSelect) {
        toLocationSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const zone = selectedOption.dataset.zone;
                const maxWeight = parseFloat(selectedOption.dataset.maxWeight) || 0;
                
                destinationInfo.innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Location:</small><br>
                            <strong>${selectedOption.value}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Zone:</small><br>
                            <strong>${zone}</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">สถานะ:</small><br>
                            <strong class="text-success">ว่าง</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">น้ำหนักสูงสุด:</small><br>
                            <strong>${maxWeight > 0 ? maxWeight.toFixed(2) + ' กก.' : 'ไม่กำหนด'}</strong>
                        </div>
                    </div>
                `;
                
                updateMovementPreview();
            } else {
                destinationInfo.innerHTML = '<small class="text-muted">เลือก Location ปลายทางเพื่อดูข้อมูล</small>';
                updateMovementPreview();
            }
        });
    }
    
    function updateMovementPreview() {
        const fromLocation = fromLocationSelect.value;
        const toLocation = toLocationSelect.value;
        
        if (fromLocation && toLocation) {
            const fromOption = fromLocationSelect.options[fromLocationSelect.selectedIndex];
            const toOption = toLocationSelect.options[toLocationSelect.selectedIndex];
            const fromZone = fromOption.dataset.zone;
            const toZone = toOption.dataset.zone;
            const palletId = fromOption.dataset.palletId;
            const sku = fromOption.dataset.sku;
            
            // Check for warnings
            let warnings = [];
            if (fromLocation === toLocation) {
                warnings.push('<div class="alert alert-danger p-2 mb-2"><small>❌ Location ต้นทางและปลายทางเหมือนกัน</small></div>');
            }
            
            const weight = parseFloat(fromOption.dataset.weight) || 0;
            const maxWeight = parseFloat(toOption.dataset.maxWeight) || 0;
            if (maxWeight > 0 && weight > maxWeight) {
                warnings.push('<div class="alert alert-warning p-2 mb-2"><small>⚠️ น้ำหนักเกินกำหนด</small></div>');
            }
            
            movementPreview.innerHTML = `
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
                    
                    ${warnings.join('')}
                </div>
            `;
        } else {
            movementPreview.innerHTML = '<p class="text-muted text-center">เลือก Location เพื่อดูตัวอย่าง</p>';
        }
    }
})();
</script>

<?php
$content = ob_get_clean();
renderMasterLayout($content, $page_title);
?>