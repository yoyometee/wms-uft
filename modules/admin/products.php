<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Product.php';

// Check login and admin permission
checkPermission('admin');

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);
$product = new Product($db);

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
                    $product_data = [
                        'sku' => sanitizeInput($_POST['sku']),
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'barcode' => sanitizeInput($_POST['barcode']),
                        'category' => sanitizeInput($_POST['category']),
                        'brand' => sanitizeInput($_POST['brand']),
                        'unit' => sanitizeInput($_POST['unit']),
                        'น้ำหนัก_ต่อ_ถุง' => (float)$_POST['น้ำหนัก_ต่อ_ถุง'],
                        'จำนวนถุง_ต่อ_แพ็ค' => (int)$_POST['จำนวนถุง_ต่อ_แพ็ค'],
                        'min_stock' => (int)$_POST['min_stock'],
                        'max_stock' => (int)$_POST['max_stock'],
                        'cost_price' => (float)($_POST['cost_price'] ?? 0),
                        'sell_price' => (float)($_POST['sell_price'] ?? 0),
                        'status' => sanitizeInput($_POST['status']),
                        'description' => sanitizeInput($_POST['description']),
                        'supplier' => sanitizeInput($_POST['supplier'])
                    ];
                    
                    if($product->createProduct($product_data)) {
                        $success_message = 'เพิ่มสินค้าเรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถเพิ่มสินค้าได้';
                    }
                    break;
                    
                case 'update':
                    $sku = sanitizeInput($_POST['sku']);
                    $product_data = [
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'barcode' => sanitizeInput($_POST['barcode']),
                        'category' => sanitizeInput($_POST['category']),
                        'brand' => sanitizeInput($_POST['brand']),
                        'unit' => sanitizeInput($_POST['unit']),
                        'น้ำหนัก_ต่อ_ถุง' => (float)$_POST['น้ำหนัก_ต่อ_ถุง'],
                        'จำนวนถุง_ต่อ_แพ็ค' => (int)$_POST['จำนวนถุง_ต่อ_แพ็ค'],
                        'min_stock' => (int)$_POST['min_stock'],
                        'max_stock' => (int)$_POST['max_stock'],
                        'cost_price' => (float)($_POST['cost_price'] ?? 0),
                        'sell_price' => (float)($_POST['sell_price'] ?? 0),
                        'status' => sanitizeInput($_POST['status']),
                        'description' => sanitizeInput($_POST['description']),
                        'supplier' => sanitizeInput($_POST['supplier'])
                    ];
                    
                    if($product->updateProduct($sku, $product_data)) {
                        $success_message = 'อัพเดตข้อมูลสินค้าเรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถอัพเดตข้อมูลได้';
                    }
                    break;
                    
                case 'delete':
                    $sku = sanitizeInput($_POST['sku']);
                    if($product->deleteProduct($sku)) {
                        $success_message = 'ลบสินค้าเรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถลบสินค้าได้ อาจมีการใช้งานอยู่';
                    }
                    break;
                    
                case 'adjust_stock':
                    $sku = sanitizeInput($_POST['sku']);
                    $adjustment = (int)$_POST['adjustment'];
                    $weight_adjustment = (float)$_POST['weight_adjustment'];
                    $type = sanitizeInput($_POST['adjustment_type']);
                    $remark = sanitizeInput($_POST['remark']);
                    
                    if($product->adjustStock($sku, $adjustment, $weight_adjustment, $type, $remark)) {
                        $success_message = 'ปรับสต็อกเรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถปรับสต็อกได้';
                    }
                    break;
            }
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all products with stock information
$products = $product->getAllProductsWithStock();

$page_title = 'จัดการสินค้า';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-box"></i> จัดการสินค้า</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="index.php" class="text-white">จัดการระบบ</a></li>
                                    <li class="breadcrumb-item active text-white">จัดการสินค้า</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus"></i> เพิ่มสินค้าใหม่
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

    <!-- Products Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายการสินค้า</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="exportProducts()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-info btn-sm" onclick="refreshTable()">
                            <i class="fas fa-sync"></i> รีเฟรช
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="productsTable">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>ชื่อสินค้า</th>
                                    <th>หมวดหมู่</th>
                                    <th>หน่วย</th>
                                    <th>น้ำหนัก/ถุง</th>
                                    <th>ถุง/แพ็ค</th>
                                    <th>สต็อกปกติ</th>
                                    <th>สต็อกแล้ว</th>
                                    <th>สถานะ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['sku']); ?></strong>
                                        <?php if($p['barcode']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($p['barcode']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['product_name']); ?></strong>
                                        <?php if($p['brand']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($p['brand']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['category']); ?></td>
                                    <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                    <td><?php echo formatWeight($p['น้ำหนัก_ต่อ_ถุง']); ?></td>
                                    <td><?php echo formatNumber($p['จำนวนถุง_ต่อ_แพ็ค']); ?></td>
                                    <td>
                                        <span class="fw-bold <?php echo ($p['normal_stock'] < $p['min_stock']) ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatNumber($p['normal_stock']); ?>
                                        </span>
                                        <?php if($p['normal_stock'] < $p['min_stock']): ?>
                                        <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> ต่ำกว่าขั้นต่ำ</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-warning"><?php echo formatNumber($p['finished_stock']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'ใช้งาน' => 'success',
                                            'ระงับ' => 'danger',
                                            'หมด' => 'secondary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_colors[$p['status']] ?? 'secondary'; ?>">
                                            <?php echo $p['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="adjustStock('<?php echo htmlspecialchars($p['sku']); ?>', '<?php echo htmlspecialchars($p['product_name']); ?>')">
                                                <i class="fas fa-adjust"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteProduct('<?php echo htmlspecialchars($p['sku']); ?>', '<?php echo htmlspecialchars($p['product_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> เพิ่มสินค้าใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" required pattern="[A-Z0-9-]+" 
                                   placeholder="เช่น PROD-001">
                            <div class="form-text">ใช้ตัวพิมพ์ใหญ่ ตัวเลข และ - เท่านั้น</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control" placeholder="บาร์โค้ด">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <input type="text" name="category" class="form-control" placeholder="หมวดหมู่สินค้า">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">แบรนด์</label>
                            <input type="text" name="brand" class="form-control" placeholder="แบรนด์">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หน่วย <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select" required>
                                <option value="">เลือกหน่วย</option>
                                <option value="ชิ้น">ชิ้น</option>
                                <option value="ถุง">ถุง</option>
                                <option value="กล่อง">กล่อง</option>
                                <option value="แพ็ค">แพ็ค</option>
                                <option value="โหล">โหล</option>
                                <option value="กิโลกรัม">กิโลกรัม</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">น้ำหนักต่อถุง (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="น้ำหนัก_ต่อ_ถุง" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">จำนวนถุงต่อแพ็ค <span class="text-danger">*</span></label>
                            <input type="number" name="จำนวนถุง_ต่อ_แพ็ค" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สต็อกขั้นต่ำ</label>
                            <input type="number" name="min_stock" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สต็อกสูงสุด</label>
                            <input type="number" name="max_stock" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ราคาต้นทุน</label>
                            <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ราคาขาย</label>
                            <input type="number" name="sell_price" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สถานะ <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="ใช้งาน">ใช้งาน</option>
                                <option value="ระงับ">ระงับ</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ผู้จำหน่าย</label>
                            <input type="text" name="supplier" class="form-control" placeholder="ผู้จำหน่าย">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">รายละเอียด</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="รายละเอียดสินค้า"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> แก้ไขข้อมูลสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editProductForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="sku" id="edit_sku">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" id="edit_sku_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="edit_barcode" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <input type="text" name="category" id="edit_category" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">แบรนด์</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หน่วย <span class="text-danger">*</span></label>
                            <select name="unit" id="edit_unit" class="form-select" required>
                                <option value="ชิ้น">ชิ้น</option>
                                <option value="ถุง">ถุง</option>
                                <option value="กล่อง">กล่อง</option>
                                <option value="แพ็ค">แพ็ค</option>
                                <option value="โหล">โหล</option>
                                <option value="กิโลกรัม">กิโลกรัม</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">น้ำหนักต่อถุง (กก.) <span class="text-danger">*</span></label>
                            <input type="number" name="น้ำหนัก_ต่อ_ถุง" id="edit_น้ำหนัก_ต่อ_ถุง" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">จำนวนถุงต่อแพ็ค <span class="text-danger">*</span></label>
                            <input type="number" name="จำนวนถุง_ต่อ_แพ็ค" id="edit_จำนวนถุง_ต่อ_แพ็ค" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สต็อกขั้นต่ำ</label>
                            <input type="number" name="min_stock" id="edit_min_stock" class="form-control" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สต็อกสูงสุด</label>
                            <input type="number" name="max_stock" id="edit_max_stock" class="form-control" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ราคาต้นทุน</label>
                            <input type="number" name="cost_price" id="edit_cost_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ราคาขาย</label>
                            <input type="number" name="sell_price" id="edit_sell_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สถานะ <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="ใช้งาน">ใช้งาน</option>
                                <option value="ระงับ">ระงับ</option>
                                <option value="หมด">หมด</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ผู้จำหน่าย</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">รายละเอียด</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
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

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-adjust"></i> ปรับสต็อก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="adjustStockForm">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="sku" id="adjust_sku">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">สินค้า</label>
                        <input type="text" id="adjust_product_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ประเภทการปรับ <span class="text-danger">*</span></label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="ปกติ">ปกติ</option>
                            <option value="แล้ว">แล้ว</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวนปรับ (ชิ้น) <span class="text-danger">*</span></label>
                        <input type="number" name="adjustment" class="form-control" required>
                        <div class="form-text">ใช้เลขบวกเพื่อเพิ่ม เลขลบเพื่อลด</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">น้ำหนักปรับ (กก.) <span class="text-danger">*</span></label>
                        <input type="number" name="weight_adjustment" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea name="remark" class="form-control" rows="3" placeholder="ระบุเหตุผลในการปรับสต็อก"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">ปรับสต็อก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#productsTable').DataTable({
        order: [[0, 'asc']], // Sort by SKU
        pageLength: 25,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        },
        columnDefs: [
            { targets: -1, orderable: false } // Disable sorting for actions column
        ]
    });
});

function editProduct(productData) {
    $('#edit_sku').val(productData.sku);
    $('#edit_sku_display').val(productData.sku);
    $('#edit_product_name').val(productData.product_name);
    $('#edit_barcode').val(productData.barcode);
    $('#edit_category').val(productData.category);
    $('#edit_brand').val(productData.brand);
    $('#edit_unit').val(productData.unit);
    $('#edit_น้ำหนัก_ต่อ_ถุง').val(productData.น้ำหนัก_ต่อ_ถุง);
    $('#edit_จำนวนถุง_ต่อ_แพ็ค').val(productData.จำนวนถุง_ต่อ_แพ็ค);
    $('#edit_min_stock').val(productData.min_stock);
    $('#edit_max_stock').val(productData.max_stock);
    $('#edit_cost_price').val(productData.cost_price);
    $('#edit_sell_price').val(productData.sell_price);
    $('#edit_status').val(productData.status);
    $('#edit_supplier').val(productData.supplier);
    $('#edit_description').val(productData.description);
    
    $('#editProductModal').modal('show');
}

function adjustStock(sku, productName) {
    $('#adjust_sku').val(sku);
    $('#adjust_product_name').val(productName);
    $('#adjustStockModal').modal('show');
}

function deleteProduct(sku, productName) {
    if(confirm(`ต้องการลบสินค้า "${productName}" หรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="sku" value="${sku}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportProducts() {
    // Export products to CSV
    const table = document.getElementById('productsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'SKU,ชื่อสินค้า,หมวดหมู่,หน่วย,น้ำหนัก/ถุง,ถุง/แพ็ค,สต็อกปกติ,สต็อกแล้ว,สถานะ\n';
    
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
    link.download = 'products_export_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

function refreshTable() {
    location.reload();
}
</script>

<?php include '../../includes/footer.php'; ?>