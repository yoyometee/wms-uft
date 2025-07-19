<?php
require_once '../../config/app_config.php';
require_once '../../includes/master_layout.php';

// Check login
checkLogin();

$page_title = 'จัดเตรียมสินค้า';
$success_message = '';
$error_message = '';

// Get database connection
$db = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'picking_operation') {
    // Validate CSRF token
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            // Validate required fields
            $required_fields = ['location_id', 'quantity_picked', 'picking_type'];
            foreach($required_fields as $field) {
                if(empty($_POST[$field])) {
                    throw new Exception("กรุณากรอกข้อมูล: " . $field);
                }
            }

            $location_id = trim($_POST['location_id']);
            $quantity_picked = intval($_POST['quantity_picked']);
            $weight_picked = floatval($_POST['weight_picked']);
            $picking_type = trim($_POST['picking_type']);
            $customer_code = trim($_POST['customer_code'] ?? '');
            $shop_name = trim($_POST['shop_name'] ?? '');
            $document_no = trim($_POST['document_no'] ?? '');
            $delivery_no = trim($_POST['delivery_no'] ?? '');
            $destination = trim($_POST['destination'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            // Get location data
            $stmt = $db->prepare("
                SELECT location_id, sku, product_name, barcode, pallet_id, ชิ้น, น้ำหนัก, 
                       lot, received_date, expiration_date, สีพาเลท, zone, status
                FROM msaster_location_by_stock 
                WHERE location_id = ? AND status = 'เก็บสินค้า'
            ");
            $stmt->execute([$location_id]);
            $location_data = $stmt->fetch();

            if (!$location_data) {
                throw new Exception("❌ Location นี้ไม่มีสินค้า หรือไม่สามารถเบิกได้");
            }

            // Validate quantities
            if ($quantity_picked <= 0 || $quantity_picked > $location_data['ชิ้น']) {
                throw new Exception("❌ จำนวนที่เบิกไม่ถูกต้อง (มีอยู่: {$location_data['ชิ้น']} ชิ้น)");
            }

            if ($weight_picked <= 0 || $weight_picked > $location_data['น้ำหนัก']) {
                throw new Exception("❌ น้ำหนักที่เบิกไม่ถูกต้อง (มีอยู่: {$location_data['น้ำหนัก']} กก.)");
            }

            // Generate Tags ID
            $tags_id = 'TG' . date('ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Start transaction
            $db->beginTransaction();

            // Create picking transaction
            $stmt = $db->prepare("
                INSERT INTO transaction_product_flow 
                (tags_id, sku, product_name, barcode, pallet_id, location_id, 
                 zone_location, status_location, จำนวนถุง_ปกติ, ชิ้น, น้ำหนัก, 
                 lot, received_date, expiration_date, สีพาเลท, ประเภทหลัก, ประเภทย่อย,
                 customer_code, shop_name, เลขเอกสาร, จุดที่, เลขงานจัดส่ง,
                 remark, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'จัดเตรียม', ?, ?, ?, ?, ?, ?, ?, 'เบิก', ?, 
                        ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tags_id, $location_data['sku'], $location_data['product_name'], 
                $location_data['barcode'], $location_data['pallet_id'], $location_id,
                $location_data['zone'], 1, $quantity_picked, $weight_picked,
                $location_data['lot'], $location_data['received_date'], 
                $location_data['expiration_date'], $location_data['สีพาเลท'],
                $picking_type, $customer_code, $shop_name, $document_no, 
                $destination, $delivery_no, $remark, $_SESSION['user_id']
            ]);

            // Update location (reduce quantity or clear if empty)
            $new_pieces = $location_data['ชิ้น'] - $quantity_picked;
            $new_weight = $location_data['น้ำหนัก'] - $weight_picked;

            if ($new_pieces <= 0 || $new_weight <= 0) {
                // Clear location
                $stmt = $db->prepare("
                    UPDATE msaster_location_by_stock 
                    SET sku = NULL, product_name = NULL, barcode = NULL, pallet_id = NULL,
                        จำนวนถุง_ปกติ = 0, ชิ้น = 0, น้ำหนัก = 0, lot = NULL,
                        received_date = NULL, expiration_date = NULL, สีพาเลท = NULL,
                        status = 'ว่าง', name_edit = ?, last_updated = ?, updated_at = NOW()
                    WHERE location_id = ?
                ");
                $stmt->execute([$_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'], time(), $location_id]);
            } else {
                // Update quantities
                $stmt = $db->prepare("
                    UPDATE msaster_location_by_stock 
                    SET ชิ้น = ?, น้ำหนัก = ?, name_edit = ?, last_updated = ?, updated_at = NOW()
                    WHERE location_id = ?
                ");
                $stmt->execute([$new_pieces, $new_weight, $_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'], time(), $location_id]);
            }

            // Update product stock (reduce from normal stock)
            $stmt = $db->prepare("
                UPDATE msaster_product 
                SET จำนวนถุง_ปกติ = จำนวนถุง_ปกติ - ?, จำนวนน้ำหนัก_ปกติ = จำนวนน้ำหนัก_ปกติ - ?,
                    last_updated = NOW()
                WHERE sku = ?
            ");
            $stmt->execute([1, $weight_picked, $location_data['sku']]);

            $db->commit();

            $success_message = "✅ จัดเตรียมสินค้าสำเร็จ!<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $tags_id . "<br>";
            $success_message .= "<strong>จำนวนที่เบิก:</strong> " . formatNumber($quantity_picked) . " ชิ้น<br>";
            $success_message .= "<strong>น้ำหนัก:</strong> " . formatWeight($weight_picked);

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

// Get occupied locations
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

ob_start();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-clipboard-list"></i> จัดเตรียมสินค้า (Picking)</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                            <li class="breadcrumb-item active">จัดเตรียมสินค้า</li>
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
                        <input type="hidden" name="action" value="picking_operation">
                        
                        <div class="col-md-6">
                            <label class="form-label">Location ID <span class="text-danger">*</span></label>
                            <select name="location_id" id="location-select" class="form-select" required>
                                <option value="">เลือก Location</option>
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
                                            <?php echo (($_POST['location_id'] ?? '') === $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo $loc['location_id']; ?> - <?php echo htmlspecialchars($loc['sku']); ?> 
                                        (<?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือก Location</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ประเภทการเบิก <span class="text-danger">*</span></label>
                            <select name="picking_type" class="form-select" required>
                                <option value="">เลือกประเภท</option>
                                <option value="เบิกขาย" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกขาย') ? 'selected' : ''; ?>>เบิกขาย</option>
                                <option value="เบิกผลิต" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกผลิต') ? 'selected' : ''; ?>>เบิกผลิต</option>
                                <option value="เบิกตัวอย่าง" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกตัวอย่าง') ? 'selected' : ''; ?>>เบิกตัวอย่าง</option>
                                <option value="เบิกใช้ภายใน" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกใช้ภายใน') ? 'selected' : ''; ?>>เบิกใช้ภายใน</option>
                                <option value="เบิกเสีย" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกเสีย') ? 'selected' : ''; ?>>เบิกเสีย</option>
                                <option value="เบิกส่งคืน" <?php echo (($_POST['picking_type'] ?? '') === 'เบิกส่งคืน') ? 'selected' : ''; ?>>เบิกส่งคืน</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกประเภทการเบิก</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">จำนวนที่เบิก (ชิ้น) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity_picked" id="quantity-picked" class="form-control" 
                                   min="1" step="1" 
                                   placeholder="จำนวนที่ต้องการเบิก"
                                   value="<?php echo htmlspecialchars($_POST['quantity_picked'] ?? ''); ?>" 
                                   required>
                            <div class="form-text">มีอยู่: <span id="available-pieces">-</span> ชิ้น</div>
                            <div class="invalid-feedback">กรุณากรอกจำนวนที่เบิก</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">น้ำหนักที่เบิก (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="weight_picked" id="weight-picked" class="form-control" 
                                   step="0.01" min="0.01" 
                                   placeholder="น้ำหนักที่ต้องการเบิก"
                                   value="<?php echo htmlspecialchars($_POST['weight_picked'] ?? ''); ?>" 
                                   required>
                            <div class="form-text">มีอยู่: <span id="available-weight">-</span> กก.</div>
                            <div class="invalid-feedback">กรุณากรอกน้ำหนักที่เบิก</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">เลขเอกสาร</label>
                            <input type="text" name="document_no" class="form-control" 
                                   placeholder="เลขเอกสารการเบิก"
                                   value="<?php echo htmlspecialchars($_POST['document_no'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">เลขงานจัดส่ง</label>
                            <input type="text" name="delivery_no" class="form-control" 
                                   placeholder="เลขงานจัดส่ง"
                                   value="<?php echo htmlspecialchars($_POST['delivery_no'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">รหัสลูกค้า</label>
                            <input type="text" name="customer_code" class="form-control" 
                                   placeholder="รหัสลูกค้า"
                                   value="<?php echo htmlspecialchars($_POST['customer_code'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ชื่อร้านค้า</label>
                            <input type="text" name="shop_name" class="form-control" 
                                   placeholder="ชื่อร้านค้า"
                                   value="<?php echo htmlspecialchars($_POST['shop_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">จุดหมาย</label>
                            <select name="destination" class="form-select">
                                <option value="">เลือกจุดหมาย</option>
                                <option value="ลานจัดส่ง" <?php echo (($_POST['destination'] ?? '') === 'ลานจัดส่ง') ? 'selected' : ''; ?>>ลานจัดส่ง</option>
                                <option value="โรงงาน" <?php echo (($_POST['destination'] ?? '') === 'โรงงาน') ? 'selected' : ''; ?>>โรงงาน</option>
                                <option value="คลังย่อย" <?php echo (($_POST['destination'] ?? '') === 'คลังย่อย') ? 'selected' : ''; ?>>คลังย่อย</option>
                                <option value="ลูกค้า" <?php echo (($_POST['destination'] ?? '') === 'ลูกค้า') ? 'selected' : ''; ?>>ลูกค้า</option>
                                <option value="ทำลาย" <?php echo (($_POST['destination'] ?? '') === 'ทำลาย') ? 'selected' : ''; ?>>ทำลาย</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ข้อมูลสินค้า</label>
                            <div class="card">
                                <div class="card-body p-2" id="product-info">
                                    <small class="text-muted">เลือก Location เพื่อดูข้อมูลสินค้า</small>
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
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-hand-paper"></i> เบิกสินค้า
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <a href="../../" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                                </a>
                                <a href="../reports/transactions.php" class="btn btn-outline-info">
                                    <i class="fas fa-list-alt"></i> ประวัติการเบิก
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Info Panel -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> คำแนะนำ</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบ Location ให้ถูกต้อง</li>
                        <li><i class="fas fa-check text-success"></i> เบิกตาม FEFO (หมดอายุก่อน เบิกก่อน)</li>
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบจำนวนให้ถูกต้อง</li>
                        <li><i class="fas fa-exclamation-triangle text-warning"></i> ระบุประเภทการเบิกให้ชัดเจน</li>
                        <li><i class="fas fa-info-circle text-info"></i> บันทึกเลขเอกสารเพื่อการตรวจสอบ</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> สถิติการเบิกสินค้าวันนี้</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $db->query("
                            SELECT 
                                COUNT(*) as total_picks,
                                SUM(ชิ้น) as total_pieces,
                                SUM(น้ำหนัก) as total_weight
                            FROM transaction_product_flow 
                            WHERE ประเภทหลัก = 'เบิก' 
                            AND DATE(created_at) = CURDATE()
                        ");
                        $today_stats = $stmt->fetch();
                    ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 text-primary"><?php echo formatNumber($today_stats['total_picks'] ?? 0); ?></div>
                                <small class="text-muted">รายการ</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 text-success"><?php echo formatNumber($today_stats['total_pieces'] ?? 0); ?></div>
                                <small class="text-muted">ชิ้น</small>
                            </div>
                            <div class="col-4">
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
    
    // Location selection handler
    const locationSelect = document.getElementById('location-select');
    const quantityInput = document.getElementById('quantity-picked');
    const weightInput = document.getElementById('weight-picked');
    const availablePieces = document.getElementById('available-pieces');
    const availableWeight = document.getElementById('available-weight');
    const productInfo = document.getElementById('product-info');
    
    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
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
                
                // Update available quantities
                availablePieces.textContent = pieces.toLocaleString();
                availableWeight.textContent = weight.toFixed(2);
                
                // Set max values for inputs
                quantityInput.max = pieces;
                weightInput.max = weight;
                
                // Clear current values
                quantityInput.value = '';
                weightInput.value = '';
                
                // Update product info
                const expiryDate = expiry ? new Date(expiry * 1000).toLocaleDateString('th-TH') : '-';
                productInfo.innerHTML = `
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
                            <small class="text-muted">Pallet ID:</small><br>
                            <strong>${palletId}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">LOT:</small><br>
                            <strong>${lot || '-'}</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <small class="text-muted">วันหมดอายุ:</small><br>
                    <strong>${expiryDate}</strong>
                `;
                
            } else {
                availablePieces.textContent = '-';
                availableWeight.textContent = '-';
                quantityInput.removeAttribute('max');
                weightInput.removeAttribute('max');
                quantityInput.value = '';
                weightInput.value = '';
                productInfo.innerHTML = '<small class="text-muted">เลือก Location เพื่อดูข้อมูลสินค้า</small>';
            }
        });
    }
    
    // Auto calculate weight based on quantity
    if (quantityInput && weightInput) {
        quantityInput.addEventListener('input', function() {
            const selectedOption = locationSelect.options[locationSelect.selectedIndex];
            if (selectedOption.value) {
                const availableWeight = parseFloat(selectedOption.dataset.weight) || 0;
                const availablePieces = parseInt(selectedOption.dataset.pieces) || 0;
                const pickedPieces = parseInt(this.value) || 0;
                
                if (availablePieces > 0 && pickedPieces > 0) {
                    const weightPerPiece = availableWeight / availablePieces;
                    const totalWeight = (weightPerPiece * pickedPieces).toFixed(2);
                    weightInput.value = totalWeight;
                }
            }
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
renderMasterLayout($content, $page_title);
?>