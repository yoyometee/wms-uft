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
checkPermission('office'); // Only office and admin can access reorder management

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

// Include reorder management class
require_once 'classes/ReorderManager.php';
$reorderManager = new ReorderManager($db);

$error_message = '';
$success_message = '';

// Handle reorder actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if($action === 'generate_recommendations') {
            $result = $reorderManager->generateReorderRecommendations();
            $success_message = "สร้างคำแนะนำการสั่งซื้อสำเร็จ - พบ " . count($result['recommendations']) . " รายการ";
            
        } elseif($action === 'approve_reorder') {
            $reorder_id = $_POST['reorder_id'] ?? 0;
            $result = $reorderManager->approveReorderRecommendation($reorder_id, $current_user['user_id']);
            
            if($result) {
                $success_message = 'อนุมัติคำแนะนำการสั่งซื้อสำเร็จ';
            } else {
                throw new Exception('ไม่สามารถอนุมัติคำแนะนำการสั่งซื้อได้');
            }
            
        } elseif($action === 'reject_reorder') {
            $reorder_id = $_POST['reorder_id'] ?? 0;
            $rejection_reason = $_POST['rejection_reason'] ?? '';
            $result = $reorderManager->rejectReorderRecommendation($reorder_id, $current_user['user_id'], $rejection_reason);
            
            if($result) {
                $success_message = 'ปฏิเสธคำแนะนำการสั่งซื้อสำเร็จ';
            } else {
                throw new Exception('ไม่สามารถปฏิเสธคำแนะนำการสั่งซื้อได้');
            }
            
        } elseif($action === 'create_purchase_order') {
            $reorder_recommendations = json_decode($_POST['reorder_recommendations'], true);
            $result = $reorderManager->createPurchaseOrderFromRecommendations($reorder_recommendations, $current_user['user_id']);
            
            if($result['success']) {
                $success_message = 'สร้างใบสั่งซื้อสำเร็จ - เลขที่: ' . $result['po_number'];
            } else {
                throw new Exception($result['error']);
            }
            
        } elseif($action === 'update_reorder_settings') {
            $settings = [
                'min_stock_days' => $_POST['min_stock_days'] ?? 7,
                'max_stock_days' => $_POST['max_stock_days'] ?? 30,
                'lead_time_buffer' => $_POST['lead_time_buffer'] ?? 3,
                'demand_forecast_period' => $_POST['demand_forecast_period'] ?? 90,
                'auto_approve_threshold' => $_POST['auto_approve_threshold'] ?? 10000,
                'consider_seasonality' => isset($_POST['consider_seasonality']),
                'enable_ai_forecasting' => isset($_POST['enable_ai_forecasting'])
            ];
            
            $result = $reorderManager->updateReorderSettings($settings);
            
            if($result) {
                $success_message = 'บันทึกการตั้งค่าสำเร็จ';
            } else {
                throw new Exception('ไม่สามารถบันทึกการตั้งค่าได้');
            }
        }
        
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get reorder recommendations
$recommendations = $reorderManager->getReorderRecommendations();

// Get reorder statistics
$reorder_stats = $reorderManager->getReorderStatistics();

// Get reorder settings
$reorder_settings = $reorderManager->getReorderSettings();

$page_title = 'ระบบจัดการการสั่งซื้ออัตโนมัติ';
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
        .priority-urgent {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            color: white;
        }
        .priority-high {
            background: linear-gradient(45deg, #fd7e14, #ffc107);
            color: white;
        }
        .priority-medium {
            background: linear-gradient(45deg, #ffc107, #28a745);
            color: white;
        }
        .priority-low {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        .forecast-chart {
            position: relative;
            height: 300px;
        }
        .recommendation-card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stock-level-indicator {
            height: 20px;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        .stock-level-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .ai-badge {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
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
                                <h1><i class="fas fa-robot"></i> ระบบจัดการการสั่งซื้ออัตโนมัติ</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">การสั่งซื้ออัตโนมัติ</li>
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
                <div class="card stat-card h-100" style="border-left-color: #dc3545;">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div class="stat-number text-danger"><?php echo number_format($reorder_stats['urgent_reorders']); ?></div>
                        <small class="text-muted">ต้องสั่งซื้อด่วน</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo number_format($reorder_stats['pending_recommendations']); ?></div>
                        <small class="text-muted">รอการอนุมัติ</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo number_format($reorder_stats['approved_today']); ?></div>
                        <small class="text-muted">อนุมัติวันนี้</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #17a2b8;">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <div class="stat-number text-info"><?php echo number_format($reorder_stats['forecast_accuracy']); ?>%</div>
                        <small class="text-muted">ความแม่นยำ AI</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="generate_recommendations">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-robot"></i> สร้างคำแนะนำใหม่
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-success" onclick="bulkApproveRecommendations()">
                                <i class="fas fa-check-double"></i> อนุมัติทั้งหมด
                            </button>
                            
                            <button type="button" class="btn btn-info" onclick="exportRecommendations()">
                                <i class="fas fa-file-export"></i> ส่งออกข้อมูล
                            </button>
                            
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                                <i class="fas fa-cogs"></i> ตั้งค่า
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reorder Recommendations -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list-ul"></i> คำแนะนำการสั่งซื้อ</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="recommendations-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                        <th>SKU</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>สต็อกปัจจุบัน</th>
                                        <th>จุดสั่งซื้อ</th>
                                        <th>แนะนำสั่งซื้อ</th>
                                        <th>ลำดับความสำคัญ</th>
                                        <th>AI Score</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recommendations as $rec): ?>
                                    <tr data-reorder-id="<?php echo $rec['id']; ?>">
                                        <td>
                                            <input type="checkbox" class="form-check-input reorder-select" 
                                                   value="<?php echo $rec['id']; ?>">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($rec['sku']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($rec['product_name']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?php echo number_format($rec['current_stock']); ?></span>
                                                <div class="stock-level-indicator flex-grow-1" style="background: #e9ecef;">
                                                    <div class="stock-level-fill" 
                                                         style="width: <?php echo min(100, ($rec['current_stock'] / $rec['reorder_point']) * 100); ?>%; 
                                                                background: <?php echo $rec['current_stock'] <= $rec['reorder_point'] ? '#dc3545' : '#28a745'; ?>;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($rec['reorder_point']); ?></td>
                                        <td>
                                            <strong class="text-primary"><?php echo number_format($rec['recommended_quantity']); ?></strong>
                                            <small class="text-muted d-block">
                                                ≈ ฿<?php echo number_format($rec['estimated_cost']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge priority-<?php echo strtolower($rec['priority']); ?>">
                                                <?php echo $rec['priority']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="ai-badge me-2"><?php echo number_format($rec['ai_confidence'] * 100, 1); ?>%</span>
                                                <i class="fas fa-robot text-primary"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="approveRecommendation(<?php echo $rec['id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="rejectRecommendation(<?php echo $rec['id']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" 
                                                        onclick="viewDetails(<?php echo $rec['id']; ?>)">
                                                    <i class="fas fa-info"></i>
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

        <!-- Demand Forecast Chart -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> การพยากรณ์ความต้องการ (AI)</h5>
                    </div>
                    <div class="card-body">
                        <div class="forecast-chart">
                            <canvas id="demandForecastChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cogs"></i> ตั้งค่าระบบสั่งซื้ออัตโนมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_reorder_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">จำนวนวันสต็อกขั้นต่ำ</label>
                                    <input type="number" name="min_stock_days" class="form-control" 
                                           value="<?php echo $reorder_settings['min_stock_days']; ?>" min="1" max="365">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">จำนวนวันสต็อกสูงสุด</label>
                                    <input type="number" name="max_stock_days" class="form-control" 
                                           value="<?php echo $reorder_settings['max_stock_days']; ?>" min="1" max="365">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Buffer Lead Time (วัน)</label>
                                    <input type="number" name="lead_time_buffer" class="form-control" 
                                           value="<?php echo $reorder_settings['lead_time_buffer']; ?>" min="0" max="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ช่วงพยากรณ์ความต้องการ (วัน)</label>
                                    <input type="number" name="demand_forecast_period" class="form-control" 
                                           value="<?php echo $reorder_settings['demand_forecast_period']; ?>" min="30" max="365">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">จำนวนเงินสำหรับอนุมัติอัตโนมัติ (บาท)</label>
                            <input type="number" name="auto_approve_threshold" class="form-control" 
                                   value="<?php echo $reorder_settings['auto_approve_threshold']; ?>" min="0" step="100">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consider_seasonality" 
                                           <?php echo $reorder_settings['consider_seasonality'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        พิจารณาฤดูกาล
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="enable_ai_forecasting" 
                                           <?php echo $reorder_settings['enable_ai_forecasting'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        เปิดใช้งาน AI พยากรณ์
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#recommendations-table').DataTable({
                order: [[6, 'asc']], // Sort by priority
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
                }
            });
            
            // Select all checkbox
            $('#selectAll').change(function() {
                $('.reorder-select').prop('checked', this.checked);
            });
            
            $('.reorder-select').change(function() {
                const allChecked = $('.reorder-select:checked').length === $('.reorder-select').length;
                $('#selectAll').prop('checked', allChecked);
            });
        });
        
        // Create demand forecast chart
        const demandData = <?php echo json_encode($reorderManager->getDemandForecastData()); ?>;
        const ctx = document.getElementById('demandForecastChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: demandData.dates,
                datasets: [
                    {
                        label: 'ความต้องการจริง',
                        data: demandData.actual,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'AI พยากรณ์',
                        data: demandData.forecast,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4
                    },
                    {
                        label: 'ช่วงความเชื่อมั่น',
                        data: demandData.confidence_upper,
                        borderColor: 'rgba(40, 167, 69, 0.3)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: '+1',
                        tension: 0.4,
                        pointRadius: 0
                    },
                    {
                        label: '',
                        data: demandData.confidence_lower,
                        borderColor: 'rgba(40, 167, 69, 0.3)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'การพยากรณ์ความต้องการสินค้า 30 วันข้างหน้า'
                    },
                    legend: {
                        display: true
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'วันที่'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'จำนวน (ชิ้น)'
                        }
                    }
                }
            }
        });
        
        function approveRecommendation(reorderId) {
            if(confirm('ต้องการอนุมัติคำแนะนำการสั่งซื้อนี้หรือไม่?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'approve_reorder';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.name = 'reorder_id';
                idInput.value = reorderId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectRecommendation(reorderId) {
            const reason = prompt('กรุณาระบุเหตุผลในการปฏิเสธ:');
            if(reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'reject_reorder';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.name = 'reorder_id';
                idInput.value = reorderId;
                form.appendChild(idInput);
                
                const reasonInput = document.createElement('input');
                reasonInput.name = 'rejection_reason';
                reasonInput.value = reason;
                form.appendChild(reasonInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function bulkApproveRecommendations() {
            const selectedIds = $('.reorder-select:checked').map(function() {
                return this.value;
            }).get();
            
            if(selectedIds.length === 0) {
                alert('กรุณาเลือกรายการที่ต้องการอนุมัติ');
                return;
            }
            
            if(confirm(`ต้องการอนุมัติคำแนะนำการสั่งซื้อ ${selectedIds.length} รายการหรือไม่?`)) {
                // Create purchase order from selected recommendations
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'create_purchase_order';
                form.appendChild(actionInput);
                
                const dataInput = document.createElement('input');
                dataInput.name = 'reorder_recommendations';
                dataInput.value = JSON.stringify(selectedIds);
                form.appendChild(dataInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportRecommendations() {
            const table = document.getElementById('recommendations-table');
            exportTable('recommendations-table', 'reorder_recommendations_' + new Date().toISOString().split('T')[0]);
        }
        
        function viewDetails(reorderId) {
            // Implementation for viewing detailed analysis
            window.open(`reorder-details.php?id=${reorderId}`, '_blank', 'width=800,height=600');
        }
        
        // Auto-refresh every 10 minutes
        setInterval(function() {
            location.reload();
        }, 600000);
    </script>
</body>
</html>