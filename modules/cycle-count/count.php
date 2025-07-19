<?php
session_start();

// Include configuration and classes
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

// Check login and permissions
checkLogin();

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get cycle count ID
$cycle_count_id = $_GET['id'] ?? 0;

if(!$cycle_count_id) {
    header('Location: index.php');
    exit;
}

// Include cycle count management class
require_once 'classes/CycleCountManager.php';
$cycleCountManager = new CycleCountManager($db);

// Get current user
$user = new User($db);
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

$error_message = '';
$success_message = '';

// Handle count submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if($action === 'start_counting') {
            $result = $cycleCountManager->startCycleCount($cycle_count_id, $current_user['user_id']);
            if($result) {
                $success_message = 'เริ่มการนับสต็อกแล้ว';
            } else {
                throw new Exception('ไม่สามารถเริ่มการนับได้');
            }
            
        } elseif($action === 'submit_counts') {
            $count_data = [];
            
            // Process form data
            foreach($_POST as $key => $value) {
                if(strpos($key, 'count_') === 0) {
                    $item_id = substr($key, 6); // Remove 'count_' prefix
                    $count_data[] = [
                        'item_id' => $item_id,
                        'counted_quantity' => $value,
                        'notes' => $_POST['notes_' . $item_id] ?? ''
                    ];
                }
            }
            
            $result = $cycleCountManager->submitCounts($cycle_count_id, $count_data, $current_user['user_id']);
            
            if($result['success']) {
                $success_message = 'บันทึกการนับสำเร็จ';
                if($result['variances'] > 0) {
                    $success_message .= ' - พบความแตกต่าง ' . $result['variances'] . ' รายการ';
                }
            } else {
                throw new Exception($result['error']);
            }
        }
        
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get cycle count details
$cycle_count = $cycleCountManager->getCycleCountDetails($cycle_count_id);

if(!$cycle_count) {
    header('Location: index.php');
    exit;
}

// Get cycle count items
$items = $cycleCountManager->getCycleCountItems($cycle_count_id);

// Group items by location for better organization
$items_by_location = [];
foreach($items as $item) {
    $location = $item['location_id'];
    if(!isset($items_by_location[$location])) {
        $items_by_location[$location] = [];
    }
    $items_by_location[$location][] = $item;
}

$page_title = 'นับสต็อก #' . $cycle_count['id'];
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
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .count-card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .location-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 15px;
            border-radius: 15px 15px 0 0;
            margin-bottom: 0;
        }
        .count-input {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .count-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .expected-qty {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
        .variance-positive { color: #28a745; font-weight: bold; }
        .variance-negative { color: #dc3545; font-weight: bold; }
        .variance-zero { color: #6c757d; }
        .scanner-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        .progress-sticky {
            position: sticky;
            top: 76px;
            z-index: 100;
            background: white;
            padding: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .item-card {
            transition: all 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .counted {
            background: #d4edda;
            border-left: 5px solid #28a745;
        }
        .variance {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
        }
        .pending {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
        }
        @media (max-width: 768px) {
            .count-input {
                font-size: 1rem;
            }
            .location-header {
                font-size: 0.9rem;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../">
                <i class="fas fa-warehouse"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-calculator"></i> นับสต็อก #<?php echo $cycle_count['id']; ?></h1>
                                <p class="mb-0">
                                    ประเภท: <?php echo strtoupper($cycle_count['count_type']); ?> | 
                                    สถานะ: <?php echo strtoupper($cycle_count['status']); ?> |
                                    กำหนดเสร็จ: <?php echo formatDate($cycle_count['schedule_date']); ?>
                                </p>
                                <?php if($cycle_count['notes']): ?>
                                <small class="text-light">หมายเหตุ: <?php echo htmlspecialchars($cycle_count['notes']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0"><?php echo formatDate(date('Y-m-d H:i:s')); ?></div>
                                <small>ผู้นับ: <?php echo htmlspecialchars($current_user['ชื่อ_สกุล']); ?></small>
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

        <!-- Progress Bar -->
        <div class="progress-sticky">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>ความคืบหน้า:</strong></span>
                        <span id="progress-text">0 / <?php echo count($items); ?> รายการ</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" id="progress-bar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Start Counting Button -->
        <?php if($cycle_count['status'] === 'scheduled'): ?>
        <div class="row mb-4">
            <div class="col-12 text-center">
                <form method="POST">
                    <input type="hidden" name="action" value="start_counting">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-play"></i> เริ่มการนับสต็อก
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Counting Form -->
        <?php if(in_array($cycle_count['status'], ['in_progress', 'completed'])): ?>
        <form method="POST" id="countingForm">
            <input type="hidden" name="action" value="submit_counts">
            
            <?php foreach($items_by_location as $location => $location_items): ?>
            <div class="count-card">
                <div class="location-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt"></i> ตำแหน่ง: <?php echo htmlspecialchars($location); ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo count($location_items); ?> รายการ</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($location_items as $item): ?>
                        <div class="col-lg-6 col-xl-4 mb-3">
                            <div class="card item-card <?php echo $item['counted_quantity'] !== null ? ($item['count_status'] === 'variance' ? 'variance' : 'counted') : 'pending'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?php echo htmlspecialchars($item['sku']); ?></h6>
                                        <span class="badge bg-<?php echo $item['count_status'] === 'variance' ? 'danger' : ($item['counted_quantity'] !== null ? 'success' : 'warning'); ?>">
                                            <?php echo strtoupper($item['count_status']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="card-text small text-muted mb-2">
                                        <?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?>
                                    </p>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="form-label small">คาดหวัง:</label>
                                            <div class="expected-qty">
                                                <?php echo number_format($item['expected_quantity'], 2); ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">นับได้:</label>
                                            <input type="number" 
                                                   name="count_<?php echo $item['id']; ?>" 
                                                   class="form-control count-input" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?php echo $item['counted_quantity'] ?? ''; ?>"
                                                   data-expected="<?php echo $item['expected_quantity']; ?>"
                                                   data-item-id="<?php echo $item['id']; ?>"
                                                   <?php echo $cycle_count['status'] === 'completed' ? 'readonly' : ''; ?>
                                                   onchange="calculateVariance(this)"
                                                   onkeyup="calculateVariance(this)">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">ความแตกต่าง:</small>
                                        <div class="variance-display" id="variance_<?php echo $item['id']; ?>">
                                            <?php if($item['counted_quantity'] !== null): ?>
                                                <?php 
                                                $variance = $item['variance_quantity'];
                                                $class = $variance > 0 ? 'variance-positive' : ($variance < 0 ? 'variance-negative' : 'variance-zero');
                                                ?>
                                                <span class="<?php echo $class; ?>">
                                                    <?php echo $variance > 0 ? '+' : ''; ?><?php echo number_format($variance, 2); ?>
                                                    (<?php echo number_format($item['variance_percentage'], 1); ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <textarea name="notes_<?php echo $item['id']; ?>" 
                                                  class="form-control form-control-sm" 
                                                  rows="2" 
                                                  placeholder="หมายเหตุ..."
                                                  <?php echo $cycle_count['status'] === 'completed' ? 'readonly' : ''; ?>><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Submit Button -->
            <?php if($cycle_count['status'] === 'in_progress'): ?>
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirmSubmit()">
                        <i class="fas fa-save"></i> บันทึกการนับ
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg ms-2" onclick="saveDraft()">
                        <i class="fas fa-file-alt"></i> บันทึกแบบร่าง
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <!-- Review Results -->
        <?php if($cycle_count['status'] === 'completed'): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-chart-bar"></i> สรุปผลการนับ</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 text-primary"><?php echo $cycle_count['total_items']; ?></div>
                                    <small>รายการทั้งหมด</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 text-success"><?php echo $cycle_count['counted_items']; ?></div>
                                    <small>นับเสร็จแล้ว</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 text-danger"><?php echo $cycle_count['variance_items']; ?></div>
                                    <small>มีความแตกต่าง</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 text-info"><?php echo number_format($cycle_count['accuracy_percentage'], 1); ?>%</div>
                                    <small>ความแม่นยำ</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($cycle_count['variance_items'] > 0): ?>
                        <hr>
                        <div class="text-center">
                            <a href="review.php?id=<?php echo $cycle_count['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> ตรวจสอบความแตกต่าง
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scanner Button -->
    <button type="button" class="scanner-btn d-md-none" onclick="openScanner()">
        <i class="fas fa-qrcode"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let countedItems = 0;
        const totalItems = <?php echo count($items); ?>;
        
        function calculateVariance(input) {
            const itemId = input.dataset.itemId;
            const expected = parseFloat(input.dataset.expected);
            const counted = parseFloat(input.value) || 0;
            const variance = counted - expected;
            const percentage = expected > 0 ? (variance / expected) * 100 : 0;
            
            const varianceDiv = document.getElementById('variance_' + itemId);
            let varianceClass = 'variance-zero';
            let sign = '';
            
            if(variance > 0) {
                varianceClass = 'variance-positive';
                sign = '+';
            } else if(variance < 0) {
                varianceClass = 'variance-negative';
            }
            
            varianceDiv.innerHTML = `<span class="${varianceClass}">
                ${sign}${variance.toFixed(2)} (${percentage.toFixed(1)}%)
            </span>`;
            
            // Update item card class
            const card = input.closest('.item-card');
            card.classList.remove('pending', 'counted', 'variance');
            
            if(input.value !== '') {
                if(Math.abs(variance) > 0.01) {
                    card.classList.add('variance');
                } else {
                    card.classList.add('counted');
                }
                updateProgress();
            } else {
                card.classList.add('pending');
            }
        }
        
        function updateProgress() {
            countedItems = document.querySelectorAll('.count-input').length - 
                          document.querySelectorAll('.count-input[value=""]').length;
            
            const percentage = (countedItems / totalItems) * 100;
            
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-text').textContent = countedItems + ' / ' + totalItems + ' รายการ';
        }
        
        function confirmSubmit() {
            const emptyInputs = document.querySelectorAll('.count-input[value=""]').length;
            
            if(emptyInputs > 0) {
                return confirm(`ยังมีรายการที่ยังไม่ได้นับ ${emptyInputs} รายการ ต้องการบันทึกหรือไม่?`);
            }
            
            return confirm('ต้องการบันทึกการนับสต็อกหรือไม่?');
        }
        
        function saveDraft() {
            // Auto-save functionality
            const formData = new FormData(document.getElementById('countingForm'));
            formData.set('action', 'save_draft');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('บันทึกแบบร่างสำเร็จ');
                }
            })
            .catch(error => {
                console.error('Save draft error:', error);
            });
        }
        
        function openScanner() {
            // Open barcode scanner for mobile
            window.open('scanner.php?cycle_id=<?php echo $cycle_count_id; ?>', '_blank', 'width=400,height=600');
        }
        
        // Auto-save every 5 minutes
        setInterval(saveDraft, 300000);
        
        // Initialize progress
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
            
            // Focus on first empty input
            const firstEmpty = document.querySelector('.count-input[value=""]');
            if(firstEmpty) {
                firstEmpty.focus();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if(e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveDraft();
            }
        });
        
        // Add barcode scanning support
        let barcodeBuffer = '';
        let barcodeTimeout;
        
        document.addEventListener('keypress', function(e) {
            // Clear buffer if too much time has passed
            clearTimeout(barcodeTimeout);
            barcodeTimeout = setTimeout(() => {
                barcodeBuffer = '';
            }, 100);
            
            // Add character to buffer
            barcodeBuffer += e.key;
            
            // If Enter is pressed, process as barcode
            if(e.key === 'Enter' && barcodeBuffer.length > 5) {
                const sku = barcodeBuffer.slice(0, -1); // Remove Enter character
                const input = document.querySelector(`input[name*="${sku}"]`);
                if(input) {
                    input.focus();
                    input.select();
                }
                barcodeBuffer = '';
            }
        });
    </script>
</body>
</html>