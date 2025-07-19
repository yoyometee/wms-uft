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
checkPermission('office'); // Only office and admin can use AI optimization

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

// Include AI optimization class
require_once 'classes/PickPathAI.php';
$pickPathAI = new PickPathAI($db);

$error_message = '';
$success_message = '';
$optimization_result = null;

// Handle optimization request
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if($action === 'optimize_pick_list') {
            $pick_list = json_decode($_POST['pick_list'], true);
            $optimization_method = $_POST['optimization_method'] ?? 'shortest_path';
            $consider_fefo = isset($_POST['consider_fefo']);
            $consider_weight = isset($_POST['consider_weight']);
            $consider_picker_experience = isset($_POST['consider_picker_experience']);
            
            if(empty($pick_list)) {
                throw new Exception('กรุณาระบุรายการที่ต้องการเบิก');
            }
            
            // Run AI optimization
            $optimization_result = $pickPathAI->optimizePickPath(
                $pick_list,
                $optimization_method,
                [
                    'consider_fefo' => $consider_fefo,
                    'consider_weight' => $consider_weight,
                    'consider_picker_experience' => $consider_picker_experience,
                    'picker_id' => $current_user['user_id']
                ]
            );
            
            $success_message = 'ปรับปรุงเส้นทางการเบิกสำเร็จ';
            
        } elseif($action === 'save_optimization') {
            $optimization_data = json_decode($_POST['optimization_data'], true);
            $result = $pickPathAI->saveOptimizationResult($optimization_data, $current_user['user_id']);
            
            if($result) {
                $success_message = 'บันทึกการปรับปรุงเส้นทางสำเร็จ';
            } else {
                throw new Exception('ไม่สามารถบันทึกการปรับปรุงเส้นทางได้');
            }
            
        } elseif($action === 'train_model') {
            $result = $pickPathAI->trainOptimizationModel();
            
            if($result['success']) {
                $success_message = 'ฝึกอบรมโมเดล AI สำเร็จ - ความแม่นยำ: ' . round($result['accuracy'] * 100, 2) . '%';
            } else {
                throw new Exception('ไม่สามารถฝึกอบรมโมเดล AI ได้: ' . $result['error']);
            }
        }
        
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get AI statistics
$ai_stats = $pickPathAI->getAIStatistics();

// Get sample pick lists for testing
$sample_pick_lists = $pickPathAI->getSamplePickLists();

// Get historical optimization data
$optimization_history = $pickPathAI->getOptimizationHistory(10);

$page_title = 'AI Pick Path Optimization';
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
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .ai-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
        }
        .ai-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .ai-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .optimization-path {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        .path-step {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .step-number {
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .ai-metrics {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        .metric-item {
            text-align: center;
            padding: 15px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
        }
        .pick-list-editor {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        .location-map {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            min-height: 300px;
        }
        .map-zone {
            border: 2px dashed #adb5bd;
            border-radius: 8px;
            padding: 10px;
            margin: 10px;
            min-height: 60px;
            position: relative;
        }
        .map-location {
            background: #e9ecef;
            border: 1px solid #adb5bd;
            border-radius: 5px;
            padding: 5px 10px;
            margin: 3px;
            display: inline-block;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .map-location.picked {
            background: #28a745;
            color: white;
        }
        .map-location.current {
            background: #ffc107;
            color: black;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .algorithm-selector {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- AI Header -->
    <div class="ai-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><i class="fas fa-robot"></i> AI Pick Path Optimization</h1>
                            <p class="mb-0">ระบบปรับปรุงเส้นทางการเบิกสินค้าด้วย AI</p>
                            <nav aria-label="breadcrumb" class="mt-2">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="../" class="text-white">โมดูล</a></li>
                                    <li class="breadcrumb-item active text-white">AI Optimization</li>
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

    <div class="container-fluid mt-4">
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

        <!-- AI Metrics -->
        <div class="ai-metrics">
            <div class="row">
                <div class="col-md-3 metric-item">
                    <div class="metric-value"><?php echo number_format($ai_stats['total_optimizations']); ?></div>
                    <div>การปรับปรุงทั้งหมด</div>
                </div>
                <div class="col-md-3 metric-item">
                    <div class="metric-value"><?php echo number_format($ai_stats['avg_time_saved'], 1); ?>%</div>
                    <div>ประหยัดเวลาเฉลี่ย</div>
                </div>
                <div class="col-md-3 metric-item">
                    <div class="metric-value"><?php echo number_format($ai_stats['avg_distance_reduced'], 1); ?>%</div>
                    <div>ลดระยะทางเฉลี่ย</div>
                </div>
                <div class="col-md-3 metric-item">
                    <div class="metric-value"><?php echo number_format($ai_stats['model_accuracy'] * 100, 1); ?>%</div>
                    <div>ความแม่นยำของโมเดล</div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Optimization Input -->
            <div class="col-lg-6 mb-4">
                <div class="card ai-card" style="border-left-color: #007bff;">
                    <div class="card-header">
                        <h5><i class="fas fa-list-alt"></i> รายการสินค้าที่ต้องการเบิก</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="optimization-form">
                            <input type="hidden" name="action" value="optimize_pick_list">
                            
                            <!-- Pick List Editor -->
                            <div class="pick-list-editor">
                                <h6>สร้างรายการเบิกสินค้า</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SKU</label>
                                        <input type="text" id="item-sku" class="form-control" placeholder="กรอก SKU">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">จำนวน</label>
                                        <input type="number" id="item-quantity" class="form-control" placeholder="จำนวน" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-primary d-block" onclick="addPickItem()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Pick List Items -->
                                <div id="pick-list-items">
                                    <!-- Items will be added here -->
                                </div>
                                
                                <!-- Sample Pick Lists -->
                                <div class="mt-3">
                                    <h6>รายการตัวอย่าง</h6>
                                    <div class="btn-group-vertical w-100">
                                        <?php foreach($sample_pick_lists as $index => $sample): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="loadSamplePickList(<?php echo $index; ?>)">
                                            <?php echo htmlspecialchars($sample['name']); ?> 
                                            (<?php echo count($sample['items']); ?> รายการ)
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Algorithm Selection -->
                            <div class="algorithm-selector">
                                <h6>เลือกอัลกอริทึมการปรับปรุง</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="optimization_method" 
                                                   id="shortest_path" value="shortest_path" checked>
                                            <label class="form-check-label" for="shortest_path">
                                                <strong>Shortest Path</strong><br>
                                                <small class="text-muted">เส้นทางที่สั้นที่สุด</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="optimization_method" 
                                                   id="genetic_algorithm" value="genetic_algorithm">
                                            <label class="form-check-label" for="genetic_algorithm">
                                                <strong>Genetic Algorithm</strong><br>
                                                <small class="text-muted">อัลกอริทึมพันธุกรรม</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="optimization_method" 
                                                   id="machine_learning" value="machine_learning">
                                            <label class="form-check-label" for="machine_learning">
                                                <strong>Machine Learning</strong><br>
                                                <small class="text-muted">การเรียนรู้ของเครื่อง</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="optimization_method" 
                                                   id="hybrid_ai" value="hybrid_ai">
                                            <label class="form-check-label" for="hybrid_ai">
                                                <strong>Hybrid AI</strong><br>
                                                <small class="text-muted">AI แบบผสมผสาน</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Optimization Options -->
                            <div class="mt-3">
                                <h6>ตัวเลือกการปรับปรุง</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consider_fefo" id="consider_fefo" checked>
                                    <label class="form-check-label" for="consider_fefo">
                                        คำนึงถึง FEFO (First Expired First Out)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consider_weight" id="consider_weight" checked>
                                    <label class="form-check-label" for="consider_weight">
                                        คำนึงถึงน้ำหนักสินค้า
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consider_picker_experience" id="consider_picker_experience">
                                    <label class="form-check-label" for="consider_picker_experience">
                                        คำนึงถึงประสบการณ์ของผู้เบิก
                                    </label>
                                </div>
                            </div>
                            
                            <input type="hidden" name="pick_list" id="pick-list-data">
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-robot"></i> ปรับปรุงเส้นทางด้วย AI
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearPickList()">
                                    <i class="fas fa-trash"></i> ล้างรายการ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Optimization Result -->
            <div class="col-lg-6 mb-4">
                <div class="card ai-card" style="border-left-color: #28a745;">
                    <div class="card-header">
                        <h5><i class="fas fa-route"></i> ผลการปรับปรุงเส้นทาง</h5>
                    </div>
                    <div class="card-body">
                        <?php if($optimization_result): ?>
                        <div class="optimization-path">
                            <div class="row mb-3">
                                <div class="col-md-4 text-center">
                                    <h6>ระยะทางรวม</h6>
                                    <div class="h4 text-primary">
                                        <?php echo number_format($optimization_result['total_distance'], 1); ?> ม.
                                    </div>
                                    <small class="text-success">
                                        ลดลง <?php echo number_format($optimization_result['distance_saved'], 1); ?>%
                                    </small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>เวลาโดยประมาณ</h6>
                                    <div class="h4 text-info">
                                        <?php echo number_format($optimization_result['estimated_time'], 1); ?> นาที
                                    </div>
                                    <small class="text-success">
                                        ลดลง <?php echo number_format($optimization_result['time_saved'], 1); ?>%
                                    </small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>ประสิทธิภาพ</h6>
                                    <div class="h4 text-success">
                                        <?php echo number_format($optimization_result['efficiency_score'], 1); ?>%
                                    </div>
                                    <small class="text-muted">คะแนนประสิทธิภาพ</small>
                                </div>
                            </div>
                            
                            <h6>เส้นทางที่แนะนำ</h6>
                            <div class="optimized-path">
                                <?php foreach($optimization_result['optimized_path'] as $index => $step): ?>
                                <div class="path-step">
                                    <div class="step-number"><?php echo $index + 1; ?></div>
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($step['location_id']); ?></strong>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($step['sku']); ?> - 
                                            <?php echo number_format($step['quantity']); ?> ชิ้น
                                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($step['zone']); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo number_format($step['distance_from_previous'], 1); ?> ม.
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="saveOptimization()">
                                    <i class="fas fa-save"></i> บันทึกการปรับปรุง
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="visualizePath()">
                                    <i class="fas fa-map"></i> แสดงเส้นทางบนแผนที่
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="exportToMobile()">
                                    <i class="fas fa-mobile-alt"></i> ส่งไปมือถือ
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">ยังไม่มีผลการปรับปรุง</h5>
                            <p class="text-muted">กรุณาเพิ่มรายการสินค้าและกดปรับปรุงเส้นทาง</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Map Visualization -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-map"></i> แผนที่คลังสินค้า</h5>
                    </div>
                    <div class="card-body">
                        <div class="location-map" id="warehouse-map">
                            <!-- Map will be generated here -->
                            <div class="text-center py-5">
                                <i class="fas fa-map fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">แผนที่คลังสินค้า</h5>
                                <p class="text-muted">แผนที่จะแสดงเมื่อมีการปรับปรุงเส้นทาง</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Training and History -->
        <div class="row">
            <!-- AI Training -->
            <div class="col-lg-6 mb-4">
                <div class="card ai-card" style="border-left-color: #6f42c1;">
                    <div class="card-header">
                        <h5><i class="fas fa-brain"></i> การฝึกอบรมโมเดล AI</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p>โมเดล AI จะเรียนรู้จากข้อมูลการเบิกสินค้าในอดีตเพื่อปรับปรุงความแม่นยำ</p>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="h5 text-primary"><?php echo number_format($ai_stats['training_data_count']); ?></div>
                                    <small class="text-muted">ข้อมูลฝึกอบรม</small>
                                </div>
                                <div class="col-4">
                                    <div class="h5 text-success"><?php echo date('d/m/Y', strtotime($ai_stats['last_training_date'])); ?></div>
                                    <small class="text-muted">ฝึกอบรมล่าสุด</small>
                                </div>
                                <div class="col-4">
                                    <div class="h5 text-info"><?php echo $ai_stats['model_version']; ?></div>
                                    <small class="text-muted">เวอร์ชันโมเดล</small>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="train_model">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-purple" style="background: #6f42c1; border-color: #6f42c1; color: white;">
                                    <i class="fas fa-brain"></i> ฝึกอบรมโมเดล AI
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                การฝึกอบรมอาจใช้เวลาสักครู่ ระบบจะปรับปรุงอัลกอริทึมตามข้อมูลล่าสุด
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optimization History -->
            <div class="col-lg-6 mb-4">
                <div class="card ai-card" style="border-left-color: #fd7e14;">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> ประวัติการปรับปรุง</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>รายการ</th>
                                        <th>อัลกอริทึม</th>
                                        <th>ประหยัด</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($optimization_history as $history): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($history['created_at'])); ?></td>
                                        <td><?php echo $history['items_count']; ?> รายการ</td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($history['algorithm']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-success">
                                                <?php echo number_format($history['time_saved'], 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if(empty($optimization_history)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <p class="text-muted">ยังไม่มีประวัติการปรับปรุง</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        // Sample pick lists data
        const samplePickLists = <?php echo json_encode($sample_pick_lists); ?>;
        let currentPickList = [];
        let optimizationResult = <?php echo $optimization_result ? json_encode($optimization_result) : 'null'; ?>;
        
        // Add pick item
        function addPickItem() {
            const sku = document.getElementById('item-sku').value.trim();
            const quantity = parseInt(document.getElementById('item-quantity').value);
            
            if(!sku || !quantity || quantity <= 0) {
                alert('กรุณากรอก SKU และจำนวนให้ถูกต้อง');
                return;
            }
            
            // Check if item already exists
            const existingIndex = currentPickList.findIndex(item => item.sku === sku);
            if(existingIndex >= 0) {
                currentPickList[existingIndex].quantity += quantity;
            } else {
                currentPickList.push({
                    sku: sku,
                    quantity: quantity
                });
            }
            
            updatePickListDisplay();
            
            // Clear inputs
            document.getElementById('item-sku').value = '';
            document.getElementById('item-quantity').value = '';
        }
        
        // Update pick list display
        function updatePickListDisplay() {
            const container = document.getElementById('pick-list-items');
            
            if(currentPickList.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">ยังไม่มีรายการ</p>';
            } else {
                let html = '<div class="list-group">';
                currentPickList.forEach((item, index) => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.sku}</strong> - ${item.quantity.toLocaleString()} ชิ้น
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePickItem(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            }
            
            // Update hidden input
            document.getElementById('pick-list-data').value = JSON.stringify(currentPickList);
        }
        
        // Remove pick item
        function removePickItem(index) {
            currentPickList.splice(index, 1);
            updatePickListDisplay();
        }
        
        // Clear pick list
        function clearPickList() {
            if(confirm('ต้องการล้างรายการทั้งหมดหรือไม่?')) {
                currentPickList = [];
                updatePickListDisplay();
            }
        }
        
        // Load sample pick list
        function loadSamplePickList(index) {
            if(samplePickLists[index]) {
                currentPickList = [...samplePickLists[index].items];
                updatePickListDisplay();
            }
        }
        
        // Save optimization result
        function saveOptimization() {
            if(!optimizationResult) {
                alert('ไม่มีผลการปรับปรุงให้บันทึก');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'save_optimization';
            
            const dataInput = document.createElement('input');
            dataInput.name = 'optimization_data';
            dataInput.value = JSON.stringify(optimizationResult);
            
            form.appendChild(actionInput);
            form.appendChild(dataInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Visualize path on map
        function visualizePath() {
            if(!optimizationResult) {
                alert('ไม่มีเส้นทางให้แสดง');
                return;
            }
            
            const mapContainer = document.getElementById('warehouse-map');
            
            // Create a simple warehouse map visualization
            let mapHtml = '<div class="row">';
            
            // Group locations by zone
            const zones = {};
            optimizationResult.optimized_path.forEach((step, index) => {
                if(!zones[step.zone]) {
                    zones[step.zone] = [];
                }
                zones[step.zone].push({
                    ...step,
                    step_number: index + 1
                });
            });
            
            // Render zones
            Object.keys(zones).forEach(zoneName => {
                mapHtml += `
                    <div class="col-md-4">
                        <div class="map-zone">
                            <h6>${zoneName}</h6>
                `;
                
                zones[zoneName].forEach(location => {
                    mapHtml += `
                        <div class="map-location" title="ขั้นตอนที่ ${location.step_number}: ${location.sku}">
                            ${location.location_id}
                            <span class="badge bg-primary">${location.step_number}</span>
                        </div>
                    `;
                });
                
                mapHtml += '</div></div>';
            });
            
            mapHtml += '</div>';
            mapContainer.innerHTML = mapHtml;
            
            // Animate the path
            animatePickingPath();
        }
        
        // Animate picking path
        function animatePickingPath() {
            const locations = document.querySelectorAll('.map-location');
            let currentStep = 0;
            
            function highlightStep() {
                // Reset all locations
                locations.forEach(loc => {
                    loc.classList.remove('current', 'picked');
                });
                
                // Highlight picked locations
                for(let i = 0; i < currentStep; i++) {
                    if(locations[i]) {
                        locations[i].classList.add('picked');
                    }
                }
                
                // Highlight current location
                if(locations[currentStep]) {
                    locations[currentStep].classList.add('current');
                    currentStep++;
                    
                    if(currentStep <= locations.length) {
                        setTimeout(highlightStep, 1000);
                    }
                }
            }
            
            highlightStep();
        }
        
        // Export to mobile device
        function exportToMobile() {
            if(!optimizationResult) {
                alert('ไม่มีข้อมูลให้ส่งออก');
                return;
            }
            
            // Create mobile-friendly format
            let mobileData = "🤖 AI Pick Path Optimization\\n\\n";
            mobileData += `📊 สรุป:\\n`;
            mobileData += `• ระยะทาง: ${optimizationResult.total_distance.toFixed(1)} เมตร\\n`;
            mobileData += `• เวลา: ${optimizationResult.estimated_time.toFixed(1)} นาที\\n`;
            mobileData += `• ประสิทธิภาพ: ${optimizationResult.efficiency_score.toFixed(1)}%\\n\\n`;
            
            mobileData += "📋 เส้นทางการเบิก:\\n";
            optimizationResult.optimized_path.forEach((step, index) => {
                mobileData += `${index + 1}. ${step.location_id} - ${step.sku} (${step.quantity} ชิ้น)\\n`;
            });
            
            // Copy to clipboard
            navigator.clipboard.writeText(mobileData).then(() => {
                alert('คัดลอกข้อมูลเรียบร้อย สามารถนำไปใส่ในแอปมือถือได้');
            }).catch(() => {
                // Fallback: show in modal
                const modal = document.createElement('div');
                modal.innerHTML = `
                    <div class="modal fade" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">ข้อมูลสำหรับมือถือ</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <textarea class="form-control" rows="15" readonly>${mobileData}</textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                const bootstrapModal = new bootstrap.Modal(modal.querySelector('.modal'));
                bootstrapModal.show();
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updatePickListDisplay();
            
            // If we have optimization result, visualize it
            if(optimizationResult) {
                setTimeout(visualizePath, 1000);
            }
        });
        
        // Handle form submission
        document.getElementById('optimization-form').addEventListener('submit', function(e) {
            if(currentPickList.length === 0) {
                e.preventDefault();
                alert('กรุณาเพิ่มรายการสินค้าที่ต้องการเบิก');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังปรับปรุงเส้นทาง...';
            
            // Restore button after form submission
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 3000);
        });
    </script>
</body>
</html>