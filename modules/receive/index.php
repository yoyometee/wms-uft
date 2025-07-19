<?php
require_once '../../config/app_config.php';
require_once '../../includes/master_layout.php';

// Check login
checkLogin();

$page_title = 'รับสินค้าเข้าคลัง';
$success_message = '';
$error_message = '';

// Get database connection
$db = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_product') {
    // Validate CSRF token
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            // Process the product receiving
            $sku = trim($_POST['sku']);
            $product_name = trim($_POST['product_name']);
            $packs = intval($_POST['packs']);
            $pieces = intval($_POST['pieces']);
            $weight = floatval($_POST['weight']);
            $location_id = trim($_POST['location_id']);
            $expiration_date = $_POST['expiration_date'];
            $remark = trim($_POST['remark']);
            
            if (empty($sku) || $packs <= 0 || $pieces <= 0 || $weight <= 0 || empty($location_id)) {
                throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
            }
            
            // Create transaction record
            $stmt = $db->prepare("
                INSERT INTO transaction_product_flow 
                (sku, product_name, จำนวนถุง_ปกติ, ชิ้น, น้ำหนัก, location_id, expiration_date, 
                 ประเภทหลัก, remark, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'รับเข้า', ?, ?, NOW())
            ");
            
            $expiry_timestamp = !empty($expiration_date) ? strtotime($expiration_date) : null;
            
            $stmt->execute([
                $sku, $product_name, $packs, $pieces, $weight, 
                $location_id, $expiry_timestamp, $remark, $_SESSION['user_id']
            ]);
            
            $success_message = '✅ บันทึกการรับสินค้าสำเร็จ! SKU: ' . htmlspecialchars($sku) . ' จำนวน: ' . number_format($pieces) . ' ชิ้น';
            
            // Reset form values
            $_POST = [];
            
        } catch(Exception $e) {
            $error_message = '❌ เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// Get available locations
try {
    $stmt = $db->query("
        SELECT location_id, zone, status 
        FROM msaster_location_by_stock 
        WHERE status = 'ว่าง' 
        ORDER BY zone, location_id 
        LIMIT 50
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
                    <h4><i class="fas fa-truck-loading"></i> รับสินค้าเข้าคลัง</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                            <li class="breadcrumb-item active">รับสินค้า</li>
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
                        <input type="hidden" name="action" value="receive_product">
                        
                        <div class="col-md-6">
                            <label class="form-label">รหัสสินค้า (SKU) <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" 
                                   placeholder="เช่น ATG001, PRM002" 
                                   value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">กรุณากรอกรหัสสินค้า</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ชื่อสินค้า</label>
                            <input type="text" name="product_name" class="form-control" 
                                   placeholder="ชื่อสินค้า" 
                                   value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">จำนวนแพ็ค <span class="text-danger">*</span></label>
                            <input type="number" name="packs" class="form-control" 
                                   min="1" step="1" 
                                   placeholder="1"
                                   value="<?php echo htmlspecialchars($_POST['packs'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">กรุณากรอกจำนวนแพ็ค (ต้องมากกว่า 0)</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">จำนวนชิ้น <span class="text-danger">*</span></label>
                            <input type="number" name="pieces" class="form-control" 
                                   min="1" step="1" 
                                   placeholder="24"
                                   value="<?php echo htmlspecialchars($_POST['pieces'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">กรุณากรอกจำนวนชิ้น (ต้องมากกว่า 0)</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">น้ำหนัก (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="weight" class="form-control" 
                                   step="0.01" min="0.01" 
                                   placeholder="15.50"
                                   value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">กรุณากรอกน้ำหนัก (ต้องมากกว่า 0)</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Location ID <span class="text-danger">*</span></label>
                            <select name="location_id" class="form-select" required>
                                <option value="">เลือก Location</option>
                                <?php foreach($available_locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location['location_id']); ?>"
                                            <?php echo (($_POST['location_id'] ?? '') === $location['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_id']); ?> 
                                        (<?php echo htmlspecialchars($location['zone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                                
                                <?php if(empty($available_locations)): ?>
                                    <option value="A-01-01-01">A-01-01-01 (Selective Rack)</option>
                                    <option value="PF-Zone-01">PF-Zone-01 (PF-Zone)</option>
                                    <option value="Packaging-01">Packaging-01 (Packaging)</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือก Location</div>
                            <?php if(empty($available_locations)): ?>
                                <small class="text-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    ไม่พบ Location ว่าง - แสดงตัวอย่าง
                                </small>
                            <?php else: ?>
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> 
                                    มี <?php echo count($available_locations); ?> ตำแหน่งว่าง
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">วันหมดอายุ <span class="text-danger">*</span></label>
                            <input type="date" name="expiration_date" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo htmlspecialchars($_POST['expiration_date'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">กรุณาระบุวันหมดอายุ</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="remark" class="form-control" rows="3" 
                                      placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"><?php echo htmlspecialchars($_POST['remark'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <hr class="my-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> บันทึกข้อมูล
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> ล้างข้อมูล
                                </button>
                                <a href="../../" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                                </a>
                                <a href="../reports/stock.php" class="btn btn-outline-info">
                                    <i class="fas fa-chart-bar"></i> ดูรายงานสต็อก
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
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบ SKU ให้ถูกต้องก่อนบันทึก</li>
                        <li><i class="fas fa-check text-success"></i> เลือก Location ที่ว่างเท่านั้น</li>
                        <li><i class="fas fa-check text-success"></i> ระบุวันหมดอายุตาม FEFO</li>
                        <li><i class="fas fa-check text-success"></i> ตรวจสอบน้ำหนักให้ตรงกับจริง</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> สถิติการรับสินค้าวันนี้</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $db->query("
                            SELECT 
                                COUNT(*) as total_receives,
                                SUM(ชิ้น) as total_pieces,
                                SUM(น้ำหนัก) as total_weight
                            FROM transaction_product_flow 
                            WHERE ประเภทหลัก = 'รับเข้า' 
                            AND DATE(created_at) = CURDATE()
                        ");
                        $today_stats = $stmt->fetch();
                    ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 text-primary"><?php echo formatNumber($today_stats['total_receives'] ?? 0); ?></div>
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
// Form validation
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
    
    // Auto-calculate pieces from packs
    const packsInput = document.querySelector('input[name="packs"]');
    const piecesInput = document.querySelector('input[name="pieces"]');
    
    if (packsInput && piecesInput) {
        packsInput.addEventListener('input', function() {
            const packs = parseInt(this.value) || 0;
            if (packs > 0) {
                // Assume 24 pieces per pack if not specified
                piecesInput.value = packs * 24;
            }
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
renderMasterLayout($content, $page_title);
?>