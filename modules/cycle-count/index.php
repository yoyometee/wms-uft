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

// Check login and permissions
checkLogin();
checkPermission('office'); // Office and admin can access cycle counting

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize classes
$user = new User($db);
$product = new Product($db);
$location = new Location($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Include cycle count management class
require_once 'classes/CycleCountManager.php';
$cycleCountManager = new CycleCountManager($db);

$error_message = '';
$success_message = '';

// Handle cycle count actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if($action === 'create_cycle_count') {
            $count_type = $_POST['count_type'] ?? 'manual';
            $locations = $_POST['locations'] ?? [];
            $skus = $_POST['skus'] ?? [];
            $schedule_date = $_POST['schedule_date'] ?? date('Y-m-d');
            $priority = $_POST['priority'] ?? 'medium';
            $notes = $_POST['notes'] ?? '';
            
            $result = $cycleCountManager->createCycleCount([
                'count_type' => $count_type,
                'locations' => $locations,
                'skus' => $skus,
                'schedule_date' => $schedule_date,
                'priority' => $priority,
                'notes' => $notes,
                'created_by' => $current_user['user_id']
            ]);
            
            if($result['success']) {
                $success_message = 'สร้างรายการนับสต็อกสำเร็จ - ID: ' . $result['cycle_id'];
            } else {
                throw new Exception($result['error']);
            }
            
        } elseif($action === 'start_counting') {
            $cycle_id = $_POST['cycle_id'] ?? 0;
            $result = $cycleCountManager->startCycleCount($cycle_id, $current_user['user_id']);
            
            if($result) {
                $success_message = 'เริ่มการนับสต็อกแล้ว';
            } else {
                throw new Exception('ไม่สามารถเริ่มการนับสต็อกได้');
            }
            
        } elseif($action === 'submit_count') {
            $cycle_id = $_POST['cycle_id'] ?? 0;
            $count_data = json_decode($_POST['count_data'], true);
            
            $result = $cycleCountManager->submitCounts($cycle_id, $count_data, $current_user['user_id']);
            
            if($result['success']) {
                $success_message = 'บันทึกการนับสำเร็จ - พบความแตกต่าง ' . $result['variances'] . ' รายการ';
            } else {
                throw new Exception($result['error']);
            }
            
        } elseif($action === 'approve_adjustments') {
            $cycle_id = $_POST['cycle_id'] ?? 0;
            $adjustments = json_decode($_POST['adjustments'], true);
            
            $result = $cycleCountManager->approveAdjustments($cycle_id, $adjustments, $current_user['user_id']);
            
            if($result) {
                $success_message = 'อนุมัติการปรับสต็อกสำเร็จ';
            } else {
                throw new Exception('ไม่สามารถอนุมัติการปรับสต็อกได้');
            }
            
        } elseif($action === 'generate_auto_cycle') {
            $method = $_POST['auto_method'] ?? 'abc_analysis';
            $count_percentage = floatval($_POST['count_percentage'] ?? 10);
            
            $result = $cycleCountManager->generateAutoCycleCount($method, $count_percentage, $current_user['user_id']);
            
            if($result['success']) {
                $success_message = 'สร้างรายการนับอัตโนมัติสำเร็จ - ' . $result['count'] . ' รายการ';
            } else {
                throw new Exception($result['error']);
            }
        }
        
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get cycle count list
$cycle_counts = $cycleCountManager->getCycleCounts();

// Get cycle count statistics
$cycle_stats = $cycleCountManager->getCycleCountStatistics();

// Get pending counts for current user (if worker)
$pending_counts = [];
if($current_user['role'] === 'worker') {
    $pending_counts = $cycleCountManager->getPendingCountsForUser($current_user['user_id']);
}

$page_title = 'ระบบนับสต็อกวัฏจักร (Cycle Count)';
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .stat-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .cycle-card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .cycle-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .status-new { border-left: 5px solid #6c757d; }
        .status-scheduled { border-left: 5px solid #17a2b8; }
        .status-in-progress { border-left: 5px solid #ffc107; }
        .status-completed { border-left: 5px solid #28a745; }
        .status-reviewed { border-left: 5px solid #007bff; }
        .status-closed { border-left: 5px solid #6c757d; }
        .priority-high { background: linear-gradient(45deg, #dc3545, #fd7e14); color: white; }
        .priority-medium { background: linear-gradient(45deg, #ffc107, #fd7e14); color: white; }
        .priority-low { background: linear-gradient(45deg, #28a745, #20c997); color: white; }
        .count-input {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }
        .variance-positive { color: #28a745; font-weight: bold; }
        .variance-negative { color: #dc3545; font-weight: bold; }
        .variance-zero { color: #6c757d; }
        .mobile-scanner {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .scanner-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #6610f2);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .scanner-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
    </style>
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
                                <h1><i class="fas fa-calculator"></i> ระบบนับสต็อกวัฏจักร</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">Cycle Count</li>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo number_format($cycle_stats['in_progress']); ?></div>
                        <small class="text-muted">กำลังดำเนินการ</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #17a2b8;">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="fas fa-calendar-alt fa-2x"></i>
                        </div>
                        <div class="stat-number text-info"><?php echo number_format($cycle_stats['scheduled']); ?></div>
                        <small class="text-muted">กำหนดการนับ</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo number_format($cycle_stats['completed_this_month']); ?></div>
                        <small class="text-muted">เสร็จเดือนนี้</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #dc3545;">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div class="stat-number text-danger"><?php echo number_format($cycle_stats['accuracy'], 1); ?>%</div>
                        <small class="text-muted">ความแม่นยำ</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <?php if(in_array($current_user['role'], ['admin', 'office'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCycleModal">
                                <i class="fas fa-plus"></i> สร้างการนับสต็อก
                            </button>
                            
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#autoCycleModal">
                                <i class="fas fa-robot"></i> สร้างอัตโนมัติ
                            </button>
                            
                            <button type="button" class="btn btn-info" onclick="exportCycleData()">
                                <i class="fas fa-file-export"></i> ส่งออกข้อมูล
                            </button>
                            
                            <button type="button" class="btn btn-warning" onclick="printCycleSheets()">
                                <i class="fas fa-print"></i> พิมพ์ใบนับ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Worker Dashboard -->
        <?php if($current_user['role'] === 'worker' && !empty($pending_counts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-tasks"></i> งานนับสต็อกของคุณ</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($pending_counts as $count): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card cycle-card">
                                    <div class="card-body">
                                        <h6 class="card-title">รายการนับ #<?php echo $count['id']; ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">กำหนดเสร็จ: <?php echo formatDate($count['schedule_date']); ?></small>
                                        </p>
                                        <span class="badge priority-<?php echo $count['priority']; ?> mb-2">
                                            <?php echo strtoupper($count['priority']); ?>
                                        </span>
                                        <div class="d-grid">
                                            <a href="count.php?id=<?php echo $count['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-play"></i> เริ่มนับ
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cycle Count List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> รายการนับสต็อก</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="cycle-counts-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ประเภท</th>
                                        <th>กำหนดการ</th>
                                        <th>สถานะ</th>
                                        <th>ลำดับความสำคัญ</th>
                                        <th>ความคืบหน้า</th>
                                        <th>ผู้สร้าง</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($cycle_counts as $cycle): ?>
                                    <tr>
                                        <td><strong>#<?php echo $cycle['id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo strtoupper($cycle['count_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($cycle['schedule_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $cycle['status'] === 'completed' ? 'success' : ($cycle['status'] === 'in_progress' ? 'warning' : 'info'); ?>">
                                                <?php echo strtoupper($cycle['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge priority-<?php echo $cycle['priority']; ?>">
                                                <?php echo strtoupper($cycle['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $cycle['progress_percentage']; ?>%">
                                                    <?php echo round($cycle['progress_percentage']); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cycle['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="count.php?id=<?php echo $cycle['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if($cycle['status'] === 'completed'): ?>
                                                <a href="review.php?id=<?php echo $cycle['id']; ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if(in_array($current_user['role'], ['admin', 'office'])): ?>
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteCycleCount(<?php echo $cycle['id']; ?>)">
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

    <!-- Create Cycle Count Modal -->
    <div class="modal fade" id="createCycleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> สร้างการนับสต็อก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_cycle_count">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ประเภทการนับ</label>
                                    <select name="count_type" class="form-select" required>
                                        <option value="manual">Manual Count</option>
                                        <option value="abc_analysis">ABC Analysis</option>
                                        <option value="random">Random Sample</option>
                                        <option value="location_based">Location Based</option>
                                        <option value="product_based">Product Based</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ลำดับความสำคัญ</label>
                                    <select name="priority" class="form-select" required>
                                        <option value="low">ต่ำ</option>
                                        <option value="medium" selected>ปานกลาง</option>
                                        <option value="high">สูง</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">วันที่กำหนดนับ</label>
                            <input type="date" name="schedule_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ตำแหน่งที่ต้องการนับ (ถ้าต้องการระบุ)</label>
                            <select name="locations[]" class="form-select" multiple size="5">
                                <?php
                                $locations_query = "SELECT DISTINCT location_id FROM msaster_location_by_stock ORDER BY location_id";
                                $locations_result = $db->query($locations_query);
                                while($loc = $locations_result->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($loc['location_id']) . '">' . htmlspecialchars($loc['location_id']) . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">กด Ctrl+Click เพื่อเลือกหลายตำแหน่ง</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">SKU ที่ต้องการนับ (ถ้าต้องการระบุ)</label>
                            <textarea name="skus" class="form-control" rows="3" placeholder="ระบุ SKU คั่นด้วยเครื่องหมายจุลภาค (,)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">สร้างการนับ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Auto Cycle Modal -->
    <div class="modal fade" id="autoCycleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-robot"></i> สร้างการนับอัตโนมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_auto_cycle">
                        
                        <div class="mb-3">
                            <label class="form-label">วิธีการเลือก</label>
                            <select name="auto_method" class="form-select" required>
                                <option value="abc_analysis">ABC Analysis (A-class items)</option>
                                <option value="high_value">High Value Items</option>
                                <option value="fast_moving">Fast Moving Items</option>
                                <option value="random">Random Selection</option>
                                <option value="overdue">Overdue for Count</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เปอร์เซ็นต์ของสินค้าทั้งหมด</label>
                            <input type="number" name="count_percentage" class="form-control" value="10" min="1" max="100" required>
                            <small class="text-muted">ระบุเปอร์เซ็นต์ของสินค้าที่ต้องการนับ</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">สร้างอัตโนมัติ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Scanner Button -->
    <div class="mobile-scanner d-md-none">
        <button type="button" class="scanner-btn" onclick="openBarcodeScanner()">
            <i class="fas fa-qrcode"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#cycle-counts-table').DataTable({
                order: [[0, 'desc']], // Sort by ID descending
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
                }
            });
        });
        
        function deleteCycleCount(cycleId) {
            if(confirm('ต้องการลบการนับสต็อกนี้หรือไม่?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'delete_cycle_count';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.name = 'cycle_id';
                idInput.value = cycleId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportCycleData() {
            window.open('export-cycle-data.php', '_blank');
        }
        
        function printCycleSheets() {
            window.open('print-cycle-sheets.php', '_blank');
        }
        
        function openBarcodeScanner() {
            // Open barcode scanner modal or redirect to scanner page
            window.open('scanner.php', '_blank', 'width=400,height=600');
        }
        
        // Auto-refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>