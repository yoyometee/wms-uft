<?php
require_once '../../config/app_config.php';
require_once '../../includes/master_layout.php';

// Check login and permissions
checkLogin();
checkPermission('office'); // Only office and admin can adjust inventory

$page_title = 'ปรับสต็อก';
$success_message = '';
$error_message = '';

// Get database connection
$db = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'inventory_adjustment') {
    // Validate CSRF token
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            // Validate required fields
            $required_fields = ['sku', 'adjustment_type', 'pieces_change', 'weight_change', 'reason'];
            foreach($required_fields as $field) {
                if(!isset($_POST[$field]) || $_POST[$field] === '') {
                    throw new Exception("กรุณากรอกข้อมูล: " . $field);
                }
            }

            $sku = trim($_POST['sku']);
            $adjustment_type = trim($_POST['adjustment_type']);
            $pieces_change = intval($_POST['pieces_change']);
            $weight_change = floatval($_POST['weight_change']);
            $reason = trim($_POST['reason']);
            $location_id = trim($_POST['location_id'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            // Get product data
            $stmt = $db->prepare("
                SELECT sku, product_name, barcode, จำนวนถุง_ปกติ, จำนวนน้ำหนัก_ปกติ, min_stock, max_stock
                FROM msaster_product 
                WHERE sku = ?
            ");
            $stmt->execute([$sku]);
            $product_data = $stmt->fetch();

            if (!$product_data) {
                throw new Exception("ไม่พบข้อมูลสินค้า SKU: " . $sku);
            }

            // Validate adjustment values
            if ($pieces_change == 0 && $weight_change == 0) {
                throw new Exception("กรุณากรอกจำนวนที่ต้องการปรับ");
            }

            // Check if reducing and ensure we don't go below zero
            if ($pieces_change < 0) {
                $new_pieces = $product_data['จำนวนถุง_ปกติ'] + $pieces_change;
                if ($new_pieces < 0) {
                    throw new Exception("จำนวนหลังปรับจะติดลบ (ปัจจุบัน: {$product_data['จำนวนถุง_ปกติ']}, ปรับ: {$pieces_change})");
                }
            }

            if ($weight_change < 0) {
                $new_weight = $product_data['จำนวนน้ำหนัก_ปกติ'] + $weight_change;
                if ($new_weight < 0) {
                    throw new Exception("น้ำหนักหลังปรับจะติดลบ (ปัจจุบัน: {$product_data['จำนวนน้ำหนัก_ปกติ']}, ปรับ: {$weight_change})");
                }
            }

            // Validate location if location-based adjustment
            $location_data = null;
            if ($adjustment_type === 'location' && !empty($location_id)) {
                $stmt = $db->prepare("
                    SELECT location_id, sku, ชิ้น, น้ำหนัก, status
                    FROM msaster_location_by_stock 
                    WHERE location_id = ?
                ");
                $stmt->execute([$location_id]);
                $location_data = $stmt->fetch();

                if ($location_data && $location_data['sku'] !== $sku) {
                    throw new Exception("Location นี้เก็บสินค้า SKU อื่น");
                }
            }

            // Generate Tags ID
            $tags_id = 'ADJ' . date('ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Start transaction
            $db->beginTransaction();

            // Create adjustment transaction
            $stmt = $db->prepare("
                INSERT INTO transaction_product_flow 
                (tags_id, sku, product_name, barcode, location_id, ชิ้น, น้ำหนัก,
                 ประเภทหลัก, ประเภทย่อย, remark, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ปรับสต็อก', ?, ?, ?, NOW())
            ");
            
            $adjustment_status = $pieces_change >= 0 ? 'ปรับเพิ่ม' : 'ปรับลด';
            $full_remark = "เหตุผล: {$reason}";
            if (!empty($remark)) {
                $full_remark .= " | หมายเหตุ: {$remark}";
            }
            if ($adjustment_type === 'location' && !empty($location_id)) {
                $full_remark .= " | Location: {$location_id}";
            }

            $stmt->execute([
                $tags_id, $sku, $product_data['product_name'], 
                $product_data['barcode'], $location_id,
                abs($pieces_change), abs($weight_change),
                $adjustment_status, $full_remark, $_SESSION['user_id']
            ]);

            // Update product stock
            $stmt = $db->prepare("
                UPDATE msaster_product 
                SET จำนวนถุง_ปกติ = จำนวนถุง_ปกติ + ?, 
                    จำนวนน้ำหนัก_ปกติ = จำนวนน้ำหนัก_ปกติ + ?,
                    last_updated = NOW()
                WHERE sku = ?
            ");
            $stmt->execute([$pieces_change, $weight_change, $sku]);

            // If location-based adjustment and location specified, update location
            if ($adjustment_type === 'location' && !empty($location_id) && $location_data) {
                if ($location_data['status'] === 'เก็บสินค้า' && $location_data['sku'] === $sku) {
                    // Update location quantities
                    $new_location_pieces = max(0, $location_data['ชิ้น'] + $pieces_change);
                    $new_location_weight = max(0, $location_data['น้ำหนัก'] + $weight_change);
                    
                    // If quantity becomes zero, clear the location
                    if ($new_location_pieces <= 0 || $new_location_weight <= 0) {
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
                        // Update location with new quantities
                        $stmt = $db->prepare("
                            UPDATE msaster_location_by_stock 
                            SET ชิ้น = ?, น้ำหนัก = ?, name_edit = ?, last_updated = ?, updated_at = NOW()
                            WHERE location_id = ?
                        ");
                        $stmt->execute([$new_location_pieces, $new_location_weight, $_SESSION['ชื่อ_สกุล'] ?? $_SESSION['user_name'], time(), $location_id]);
                    }
                }
            }

            $db->commit();

            $adjustment_text = $pieces_change >= 0 ? 'เพิ่ม' : 'ลด';
            $success_message = "✅ ปรับสต็อกสำเร็จ!<br>";
            $success_message .= "<strong>Tags ID:</strong> " . $tags_id . "<br>";
            $success_message .= "<strong>SKU:</strong> " . $sku . "<br>";
            $success_message .= "<strong>การปรับ:</strong> " . $adjustment_text . " " . abs($pieces_change) . " ชิ้น, " . abs($weight_change) . " กก.";

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
        SELECT sku, product_name, barcode, จำนวนถุง_ปกติ, จำนวนน้ำหนัก_ปกติ, 
               unit, min_stock, max_stock
        FROM msaster_product 
        ORDER BY sku
        LIMIT 200
    ");
    $products = $stmt->fetchAll();
} catch(Exception $e) {
    $products = [];
}

try {
    $stmt = $db->query("
        SELECT location_id, sku, product_name, ชิ้น, น้ำหนัก, status
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
                    <h4><i class="fas fa-clipboard-check"></i> ปรับสต็อก (Inventory Adjustment)</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                            <li class="breadcrumb-item active">ปรับสต็อก</li>
                        </ol>
                    </nav>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>คำเตือน:</strong> การปรับสต็อกจะส่งผลต่อรายงานและการคำนวณต่างๆ กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนบันทึก
                    </div>

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
                        <input type="hidden" name="action" value="inventory_adjustment">
                        
                        <div class="col-md-6">
                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                            <select name="sku" id="sku-select" class="form-select" required>
                                <option value="">เลือก SKU</option>
                                <?php foreach($products as $prod): ?>
                                    <option value="<?php echo htmlspecialchars($prod['sku']); ?>"
                                            data-name="<?php echo htmlspecialchars($prod['product_name']); ?>"
                                            data-current-pieces="<?php echo $prod['จำนวนถุง_ปกติ']; ?>"
                                            data-current-weight="<?php echo $prod['จำนวนน้ำหนัก_ปกติ']; ?>"
                                            data-unit="<?php echo htmlspecialchars($prod['unit']); ?>"
                                            data-min-stock="<?php echo $prod['min_stock']; ?>"
                                            data-max-stock="<?php echo $prod['max_stock']; ?>"
                                            <?php echo (($_POST['sku'] ?? '') === $prod['sku']) ? 'selected' : ''; ?>>
                                        <?php echo $prod['sku']; ?> - <?php echo htmlspecialchars($prod['product_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือก SKU</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ประเภทการปรับ <span class="text-danger">*</span></label>
                            <select name="adjustment_type" id="adjustment-type" class="form-select" required>
                                <option value="">เลือกประเภท</option>
                                <option value="pf" <?php echo (($_POST['adjustment_type'] ?? '') === 'pf') ? 'selected' : ''; ?>>ปรับสต็อก PF (Pick Face)</option>
                                <option value="location" <?php echo (($_POST['adjustment_type'] ?? '') === 'location') ? 'selected' : ''; ?>>ปรับสต็อกตาม Location</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกประเภทการปรับ</div>
                        </div>
                        
                        <div class="col-md-6" id="location-field" style="display: none;">
                            <label class="form-label">Location ID</label>
                            <select name="location_id" id="location-select" class="form-select">
                                <option value="">เลือก Location (ไม่บังคับ)</option>
                                <?php foreach($occupied_locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_id']); ?>"
                                            data-sku="<?php echo htmlspecialchars($loc['sku']); ?>"
                                            data-pieces="<?php echo $loc['ชิ้น']; ?>"
                                            data-weight="<?php echo $loc['น้ำหนัก']; ?>"
                                            <?php echo (($_POST['location_id'] ?? '') === $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo $loc['location_id']; ?> - <?php echo htmlspecialchars($loc['sku']); ?> 
                                        (<?php echo formatNumber($loc['ชิ้น']); ?> ชิ้น)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">สต็อกปัจจุบัน</label>
                            <div class="card">
                                <div class="card-body p-2" id="current-stock-info">
                                    <small class="text-muted">เลือก SKU เพื่อดูสต็อกปัจจุบัน</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ปรับจำนวน (ชิ้น) <span class="text-danger">*</span></label>
                            <input type="number" name="pieces_change" id="pieces-change" class="form-control" 
                                   step="1"
                                   placeholder="ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด"
                                   value="<?php echo htmlspecialchars($_POST['pieces_change'] ?? ''); ?>" 
                                   required>
                            <div class="form-text">ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด</div>
                            <div class="invalid-feedback">กรุณากรอกจำนวนที่ต้องการปรับ</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ปรับน้ำหนัก (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="weight_change" id="weight-change" class="form-control" 
                                   step="0.01"
                                   placeholder="ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด"
                                   value="<?php echo htmlspecialchars($_POST['weight_change'] ?? ''); ?>" 
                                   required>
                            <div class="form-text">ใส่เลขบวกเพื่อเพิ่ม, เลขลบเพื่อลด</div>
                            <div class="invalid-feedback">กรุณากรอกน้ำหนักที่ต้องการปรับ</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ผลลัพธ์หลังปรับ</label>
                            <div class="card">
                                <div class="card-body p-2" id="result-preview">
                                    <small class="text-muted">กรอกข้อมูลเพื่อดูผลลัพธ์</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">เหตุผลการปรับ <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select" required>
                                <option value="">เลือกเหตุผล</option>
                                <option value="ตรวจนับ Cycle Count" <?php echo (($_POST['reason'] ?? '') === 'ตรวจนับ Cycle Count') ? 'selected' : ''; ?>>ตรวจนับ Cycle Count</option>
                                <option value="ตรวจนับประจำปี" <?php echo (($_POST['reason'] ?? '') === 'ตรวจนับประจำปี') ? 'selected' : ''; ?>>ตรวจนับประจำปี</option>
                                <option value="สินค้าเสียหาย" <?php echo (($_POST['reason'] ?? '') === 'สินค้าเสียหาย') ? 'selected' : ''; ?>>สินค้าเสียหาย</option>
                                <option value="สินค้าหมดอายุ" <?php echo (($_POST['reason'] ?? '') === 'สินค้าหมดอายุ') ? 'selected' : ''; ?>>สินค้าหมดอายุ</option>
                                <option value="สินค้าสูญหาย" <?php echo (($_POST['reason'] ?? '') === 'สินค้าสูญหาย') ? 'selected' : ''; ?>>สินค้าสูญหาย</option>
                                <option value="ข้อผิดพลาดการบันทึก" <?php echo (($_POST['reason'] ?? '') === 'ข้อผิดพลาดการบันทึก') ? 'selected' : ''; ?>>ข้อผิดพลาดการบันทึก</option>
                                <option value="ยอดยกมา" <?php echo (($_POST['reason'] ?? '') === 'ยอดยกมา') ? 'selected' : ''; ?>>ยอดยกมา</option>
                                <option value="การแปลงหน่วย" <?php echo (($_POST['reason'] ?? '') === 'การแปลงหน่วย') ? 'selected' : ''; ?>>การแปลงหน่วย</option>
                                <option value="อื่นๆ" <?php echo (($_POST['reason'] ?? '') === 'อื่นๆ') ? 'selected' : ''; ?>>อื่นๆ</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกเหตุผลการปรับ</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ชื่อสินค้า</label>
                            <input type="text" id="product-name" class="form-control" readonly 
                                   placeholder="ชื่อสินค้าจะแสดงเมื่อเลือก SKU">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="remark" class="form-control" rows="3" 
                                      placeholder="หมายเหตุเพิ่มเติม, เลขที่เอกสาร, รายละเอียดการตรวจนับ"><?php echo htmlspecialchars($_POST['remark'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <hr class="my-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> บันทึกการปรับสต็อก
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <a href="../../" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                                </a>
                                <a href="../reports/stock.php" class="btn btn-outline-success">
                                    <i class="fas fa-chart-bar"></i> รายงานสต็อก
                                </a>
                                <a href="../reports/transactions.php?type=adjustment" class="btn btn-outline-warning">
                                    <i class="fas fa-list-alt"></i> ประวัติการปรับ
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Panels -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> คำแนะนำ</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบ SKU ให้ถูกต้อง</li>
                        <li><i class="fas fa-check text-success"></i> ระบุเหตุผลการปรับให้ชัดเจน</li>
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบจำนวนก่อนบันทึก</li>
                        <li><i class="fas fa-exclamation-triangle text-warning"></i> ระวังการปรับลดให้เกินสต็อกปัจจุบัน</li>
                        <li><i class="fas fa-info-circle text-info"></i> บันทึกเลขเอกสารในหมายเหตุ</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-calculator"></i> ตัวอย่างการปรับ</h6>
                </div>
                <div class="card-body" id="adjustment-preview">
                    <p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> สถิติการปรับสต็อกวันนี้</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $db->query("
                            SELECT 
                                COUNT(*) as total_adjustments,
                                COUNT(DISTINCT sku) as unique_skus,
                                SUM(CASE WHEN ประเภทย่อย = 'ปรับเพิ่ม' THEN ชิ้น ELSE 0 END) as total_increase,
                                SUM(CASE WHEN ประเภทย่อย = 'ปรับลด' THEN ชิ้น ELSE 0 END) as total_decrease
                            FROM transaction_product_flow 
                            WHERE ประเภทหลัก = 'ปรับสต็อก' 
                            AND DATE(created_at) = CURDATE()
                        ");
                        $today_stats = $stmt->fetch();
                    ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 text-info"><?php echo formatNumber($today_stats['total_adjustments'] ?? 0); ?></div>
                                <small class="text-muted">รายการปรับ</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 text-primary"><?php echo formatNumber($today_stats['unique_skus'] ?? 0); ?></div>
                                <small class="text-muted">SKU ที่ปรับ</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h6 text-success">+<?php echo formatNumber($today_stats['total_increase'] ?? 0); ?></div>
                                <small class="text-muted">เพิ่ม (ชิ้น)</small>
                            </div>
                            <div class="col-6">
                                <div class="h6 text-danger">-<?php echo formatNumber($today_stats['total_decrease'] ?? 0); ?></div>
                                <small class="text-muted">ลด (ชิ้น)</small>
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
    
    // SKU selection handler
    const skuSelect = document.getElementById('sku-select');
    const productName = document.getElementById('product-name');
    const currentStockInfo = document.getElementById('current-stock-info');
    const piecesChange = document.getElementById('pieces-change');
    const weightChange = document.getElementById('weight-change');
    const resultPreview = document.getElementById('result-preview');
    const adjustmentPreview = document.getElementById('adjustment-preview');
    const adjustmentType = document.getElementById('adjustment-type');
    const locationField = document.getElementById('location-field');
    const locationSelect = document.getElementById('location-select');
    
    if (skuSelect) {
        skuSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const name = selectedOption.dataset.name;
                const currentPieces = parseInt(selectedOption.dataset.currentPieces);
                const currentWeight = parseFloat(selectedOption.dataset.currentWeight);
                const unit = selectedOption.dataset.unit;
                const minStock = parseInt(selectedOption.dataset.minStock);
                const maxStock = parseInt(selectedOption.dataset.maxStock);
                
                productName.value = name;
                
                // Update current stock info
                const stockStatus = getStockStatus(currentPieces, minStock, maxStock);
                currentStockInfo.innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">จำนวน:</small><br>
                            <strong>${currentPieces.toLocaleString()} ${unit}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">น้ำหนัก:</small><br>
                            <strong>${currentWeight.toFixed(2)} กก.</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">ขั้นต่ำ:</small><br>
                            <strong>${minStock.toLocaleString()}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">ขั้นสูง:</small><br>
                            <strong>${maxStock.toLocaleString()}</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="text-center">
                        <span class="badge bg-${stockStatus.class}">${stockStatus.text}</span>
                    </div>
                `;
                
                // Filter locations for this SKU
                filterLocationsBySKU(selectedOption.value);
                updateCalculation();
            } else {
                clearForm();
            }
        });
    }
    
    // Adjustment type change event
    if (adjustmentType) {
        adjustmentType.addEventListener('change', function() {
            if (this.value === 'location') {
                locationField.style.display = 'block';
            } else {
                locationField.style.display = 'none';
                locationSelect.value = '';
            }
        });
    }
    
    // Input change events for calculation
    if (piecesChange && weightChange) {
        piecesChange.addEventListener('input', updateCalculation);
        weightChange.addEventListener('input', updateCalculation);
    }
    
    function filterLocationsBySKU(sku) {
        const options = locationSelect.options;
        for (let i = 1; i < options.length; i++) { // Skip first empty option
            const optionSKU = options[i].dataset.sku;
            if (optionSKU === sku) {
                options[i].style.display = 'block';
            } else {
                options[i].style.display = 'none';
            }
        }
    }
    
    function updateCalculation() {
        const selectedSKU = skuSelect.options[skuSelect.selectedIndex];
        const currentPieces = parseInt(selectedSKU.dataset.currentPieces) || 0;
        const currentWeight = parseFloat(selectedSKU.dataset.currentWeight) || 0;
        const piecesChangeVal = parseInt(piecesChange.value) || 0;
        const weightChangeVal = parseFloat(weightChange.value) || 0;
        
        if (selectedSKU.value) {
            const resultPieces = currentPieces + piecesChangeVal;
            const resultWeight = currentWeight + weightChangeVal;
            
            // Update result preview
            let resultClass = 'text-primary';
            if (resultPieces < 0 || resultWeight < 0) {
                resultClass = 'text-danger';
            } else if (resultPieces === 0 && resultWeight === 0) {
                resultClass = 'text-warning';
            }
            
            resultPreview.innerHTML = `
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">จำนวนใหม่:</small><br>
                        <strong class="${resultClass}">${resultPieces.toLocaleString()} ชิ้น</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">น้ำหนักใหม่:</small><br>
                        <strong class="${resultClass}">${resultWeight.toFixed(2)} กก.</strong>
                    </div>
                </div>
            `;
            
            // Update adjustment preview
            let changeType = '';
            let changeClass = '';
            if (piecesChangeVal > 0 || weightChangeVal > 0) {
                changeType = 'เพิ่ม';
                changeClass = 'success';
            } else if (piecesChangeVal < 0 || weightChangeVal < 0) {
                changeType = 'ลด';
                changeClass = 'danger';
            } else {
                changeType = 'ไม่เปลี่ยนแปลง';
                changeClass = 'secondary';
            }
            
            let warningHtml = '';
            if (resultPieces < 0 || resultWeight < 0) {
                warningHtml = '<div class="alert alert-danger p-2 mt-2"><small>⚠️ ผลลัพธ์เป็นลบ!</small></div>';
            } else if (resultPieces === 0 && resultWeight === 0) {
                warningHtml = '<div class="alert alert-warning p-2 mt-2"><small>⚠️ สต็อกจะเป็น 0</small></div>';
            }
            
            adjustmentPreview.innerHTML = `
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
            `;
        } else {
            resultPreview.innerHTML = '<small class="text-muted">เลือก SKU เพื่อดูผลลัพธ์</small>';
            adjustmentPreview.innerHTML = '<p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>';
        }
    }
    
    function getStockStatus(current, min, max) {
        if (current <= min) {
            return {class: 'danger', text: 'ต่ำกว่าขั้นต่ำ'};
        } else if (current >= max) {
            return {class: 'warning', text: 'สูงกว่าขั้นสูง'};
        } else {
            return {class: 'success', text: 'ปกติ'};
        }
    }
    
    function clearForm() {
        productName.value = '';
        currentStockInfo.innerHTML = '<small class="text-muted">เลือก SKU เพื่อดูสต็อกปัจจุบัน</small>';
        resultPreview.innerHTML = '<small class="text-muted">กรอกข้อมูลเพื่อดูผลลัพธ์</small>';
        adjustmentPreview.innerHTML = '<p class="text-muted text-center">กรอกข้อมูลเพื่อดูตัวอย่าง</p>';
        
        // Show all location options
        const options = locationSelect.options;
        for (let i = 1; i < options.length; i++) {
            options[i].style.display = 'block';
        }
    }
    
    // Initialize display based on current form state
    if (adjustmentType.value === 'location') {
        locationField.style.display = 'block';
    }
})();
</script>

<?php
$content = ob_get_clean();
renderMasterLayout($content, $page_title);
?>