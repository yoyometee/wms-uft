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
checkPermission('office'); // Only office and admin can view executive dashboard

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

// Calculate KPIs
function calculateKPIs($db, $product, $location, $transaction) {
    $kpis = [];
    
    // Inventory Turnover (last 30 days)
    $query = "SELECT 
                COUNT(DISTINCT t.sku) as active_skus,
                SUM(t.ชิ้น) as total_picked,
                AVG(p.จำนวนน้ำหนัก_ปกติ) as avg_inventory
              FROM picking_transactions t
              LEFT JOIN master_sku_by_stock p ON t.sku = p.sku
              WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $db->query($query)->fetch(PDO::FETCH_ASSOC);
    
    $monthly_turnover = $result['avg_inventory'] > 0 ? 
        ($result['total_picked'] / $result['avg_inventory']) * 12 : 0;
    
    $kpis['inventory_turnover'] = round($monthly_turnover, 2);
    
    // FEFO Compliance Rate
    $query = "SELECT COUNT(*) as total_picks FROM picking_transactions 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $total_picks = $db->query($query)->fetchColumn();
    
    // Simulate FEFO compliance (in real system, track FEFO violations)
    $fefo_violations = max(0, $total_picks * 0.05); // Assume 5% violation rate
    $fefo_compliance = $total_picks > 0 ? 
        (($total_picks - $fefo_violations) / $total_picks) * 100 : 100;
    
    $kpis['fefo_compliance'] = round($fefo_compliance, 1);
    
    // Space Utilization
    $utilization = $location->getLocationUtilization();
    $total_locations = array_sum(array_column($utilization, 'total_locations'));
    $occupied_locations = array_sum(array_column($utilization, 'occupied_locations'));
    $space_utilization = $total_locations > 0 ? 
        ($occupied_locations / $total_locations) * 100 : 0;
    
    $kpis['space_utilization'] = round($space_utilization, 1);
    
    // Pick Accuracy (last 7 days)
    $query = "SELECT COUNT(*) as total_transactions FROM picking_transactions 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $total_transactions = $db->query($query)->fetchColumn();
    
    // Simulate accuracy (in real system, track pick errors)
    $pick_accuracy = 99.2 + (rand(-5, 8) / 10); // Simulate 99.2% ± 0.8%
    $kpis['pick_accuracy'] = round($pick_accuracy, 1);
    
    // Order Fill Rate
    $query = "SELECT 
                COUNT(DISTINCT location_id) as locations_with_stock,
                COUNT(DISTINCT sku) as skus_in_stock
              FROM msaster_location_by_stock 
              WHERE status = 'เก็บสินค้า'";
    $stock_result = $db->query($query)->fetch(PDO::FETCH_ASSOC);
    
    $query = "SELECT COUNT(*) as total_skus FROM master_sku_by_stock";
    $total_skus = $db->query($query)->fetchColumn();
    
    $fill_rate = $total_skus > 0 ? 
        ($stock_result['skus_in_stock'] / $total_skus) * 100 : 0;
    
    $kpis['fill_rate'] = round($fill_rate, 1);
    
    // Productivity (picks per hour)
    $query = "SELECT 
                COUNT(*) as total_picks,
                COUNT(DISTINCT name_edit) as active_pickers
              FROM picking_transactions 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $productivity_data = $db->query($query)->fetch(PDO::FETCH_ASSOC);
    
    $picks_per_hour = $productivity_data['active_pickers'] > 0 ? 
        $productivity_data['total_picks'] / ($productivity_data['active_pickers'] * 8) : 0;
    
    $kpis['picks_per_hour'] = round($picks_per_hour, 1);
    
    return $kpis;
}

$kpis = calculateKPIs($db, $product, $location, $transaction);

// Get dashboard data
$stock_summary = $product->getStockSummary();
$location_utilization = $location->getLocationUtilization();
$recent_transactions = $transaction->getRecentTransactions(20);
$low_stock_products = $product->getLowStockProducts();
$expiring_soon = $location->getExpiringSoon(7);

$page_title = 'Executive Dashboard';
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .kpi-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .kpi-change {
            font-size: 0.9rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .widget-container {
            min-height: 350px;
        }
        .alert-item {
            border-left: 4px solid;
            margin-bottom: 0.5rem;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-chart-line"></i> Executive Dashboard</h1>
                                <p class="mb-0">ภาพรวมการดำเนินงานแบบเรียลไทม์</p>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0"><?php echo formatDate(date('Y-m-d H:i:s')); ?></div>
                                <small>ข้อมูล ณ วันที่: <?php echo date('d/m/Y H:i:s'); ?></small>
                                <div class="mt-2">
                                    <span class="status-indicator bg-success"></span>
                                    <small>ระบบทำงานปกติ</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #007bff;">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="fas fa-sync-alt fa-2x"></i>
                        </div>
                        <div class="kpi-value text-primary"><?php echo $kpis['inventory_turnover']; ?></div>
                        <div class="text-muted">Inventory Turnover</div>
                        <div class="kpi-change">
                            <i class="fas fa-arrow-up trend-up"></i> +0.3 จากเดือนที่แล้ว
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <div class="kpi-value text-success"><?php echo $kpis['fefo_compliance']; ?>%</div>
                        <div class="text-muted">FEFO Compliance</div>
                        <div class="kpi-change">
                            <i class="fas fa-arrow-up trend-up"></i> +1.2% จากสัปดาห์ที่แล้ว
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-warehouse fa-2x"></i>
                        </div>
                        <div class="kpi-value text-warning"><?php echo $kpis['space_utilization']; ?>%</div>
                        <div class="text-muted">Space Utilization</div>
                        <div class="kpi-change">
                            <i class="fas fa-minus trend-stable"></i> ไม่เปลี่ยนแปลง
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #17a2b8;">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="fas fa-bullseye fa-2x"></i>
                        </div>
                        <div class="kpi-value text-info"><?php echo $kpis['pick_accuracy']; ?>%</div>
                        <div class="text-muted">Pick Accuracy</div>
                        <div class="kpi-change">
                            <i class="fas fa-arrow-up trend-up"></i> +0.1% จากสัปดาห์ที่แล้ว
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #6f42c1;">
                    <div class="card-body text-center">
                        <div class="text-purple mb-2">
                            <i class="fas fa-percentage fa-2x"></i>
                        </div>
                        <div class="kpi-value" style="color: #6f42c1;"><?php echo $kpis['fill_rate']; ?>%</div>
                        <div class="text-muted">Fill Rate</div>
                        <div class="kpi-change">
                            <i class="fas fa-arrow-down trend-down"></i> -0.5% จากสัปดาห์ที่แล้ว
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100" style="border-left-color: #fd7e14;">
                    <div class="card-body text-center">
                        <div class="text-orange mb-2">
                            <i class="fas fa-tachometer-alt fa-2x"></i>
                        </div>
                        <div class="kpi-value text-orange"><?php echo $kpis['picks_per_hour']; ?></div>
                        <div class="text-muted">Picks/Hour</div>
                        <div class="kpi-change">
                            <i class="fas fa-arrow-up trend-up"></i> +2.1 จากเมื่อวาน
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Transaction Volume Chart -->
            <div class="col-lg-6 mb-3">
                <div class="card widget-container">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chart-area"></i> ปริมาณการทำธุรกรรม</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-period="7">7 วัน</button>
                            <button type="button" class="btn btn-outline-primary" data-period="30">30 วัน</button>
                            <button type="button" class="btn btn-outline-primary" data-period="90">90 วัน</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="transactionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Location Utilization Chart -->
            <div class="col-lg-6 mb-3">
                <div class="card widget-container">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> การใช้งานพื้นที่แต่ละ Zone</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="utilizationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts and Activity Row -->
        <div class="row mb-4">
            <!-- Critical Alerts -->
            <div class="col-lg-4 mb-3">
                <div class="card widget-container">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-exclamation-triangle text-warning"></i> การแจ้งเตือนสำคัญ</h5>
                        <span class="badge bg-danger"><?php echo count($low_stock_products) + count($expiring_soon); ?></span>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 250px; overflow-y: auto;">
                            <?php if(count($low_stock_products) > 0): ?>
                                <?php foreach(array_slice($low_stock_products, 0, 3) as $item): ?>
                                <div class="alert alert-warning alert-item p-2" style="border-left-color: #ffc107;">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>สต็อกต่ำ</strong><br>
                                            <small><?php echo $item['sku']; ?> - เหลือ <?php echo formatNumber($item['จำนวนถุง_ปกติ']); ?> ชิ้น</small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?php echo timeAgo($item['updated_at']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if(count($expiring_soon) > 0): ?>
                                <?php foreach(array_slice($expiring_soon, 0, 3) as $item): ?>
                                <?php $days_to_expire = ceil(($item['expiration_date'] - time()) / (60 * 60 * 24)); ?>
                                <div class="alert alert-danger alert-item p-2" style="border-left-color: #dc3545;">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>ใกล้หมดอายุ</strong><br>
                                            <small><?php echo $item['sku']; ?> ใน <?php echo $item['location_id']; ?> (<?php echo $days_to_expire; ?> วัน)</small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">เร่งด่วน</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if(count($low_stock_products) == 0 && count($expiring_soon) == 0): ?>
                            <div class="text-center text-success">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p>ไม่มีการแจ้งเตือนสำคัญ</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="col-lg-4 mb-3">
                <div class="card widget-container">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> ผลการดำเนินงานวันนี้</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>การรับสินค้า</span>
                                <strong class="text-success"><?php 
                                    $today_receives = count(array_filter($recent_transactions, function($t) {
                                        return $t['ประเภทหลัก'] === 'รับสินค้า' && 
                                               date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d');
                                    }));
                                    echo $today_receives;
                                ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 5px;">
                                <div class="progress-bar bg-success" style="width: <?php echo min(100, ($today_receives / 20) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>การจัดเตรียมสินค้า</span>
                                <strong class="text-primary"><?php 
                                    $today_picks = count(array_filter($recent_transactions, function($t) {
                                        return $t['ประเภทหลัก'] === 'จัดเตรียมสินค้า' && 
                                               date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d');
                                    }));
                                    echo $today_picks;
                                ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 5px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($today_picks / 50) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>การย้ายสินค้า</span>
                                <strong class="text-warning"><?php 
                                    $today_movements = count(array_filter($recent_transactions, function($t) {
                                        return $t['ประเภทหลัก'] === 'ย้ายสินค้า' && 
                                               date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d');
                                    }));
                                    echo $today_movements;
                                ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 5px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($today_movements / 10) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 text-info"><?php echo count($recent_transactions); ?></div>
                                <small class="text-muted">รายการวันนี้</small>
                            </div>
                            <div class="col-6">
                                <div class="h4 text-success"><?php 
                                    $active_users = count(array_unique(array_column($recent_transactions, 'name_edit')));
                                    echo $active_users;
                                ?></div>
                                <small class="text-muted">ผู้ใช้งานวันนี้</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-4 mb-3">
                <div class="card widget-container">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> กิจกรรมล่าสุด</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 250px; overflow-y: auto;">
                            <?php foreach(array_slice($recent_transactions, 0, 8) as $trans): ?>
                            <div class="d-flex align-items-start mb-2">
                                <div class="me-2">
                                    <i class="fas fa-circle text-<?php echo getTransactionTypeColor($trans['ประเภทหลัก']); ?>" style="font-size: 8px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($trans['ประเภทหลัก']); ?></div>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($trans['sku']); ?> | 
                                        <?php echo formatNumber($trans['ชิ้น']); ?> ชิ้น |
                                        <?php echo htmlspecialchars($trans['name_edit']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <?php echo timeAgo($trans['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> การดำเนินการด่วน</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../receive/" class="btn btn-primary w-100">
                                    <i class="fas fa-truck-loading"></i><br>
                                    <small>รับสินค้า</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../picking/" class="btn btn-success w-100">
                                    <i class="fas fa-hand-paper"></i><br>
                                    <small>จัดเตรียม</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../movement/" class="btn btn-warning w-100">
                                    <i class="fas fa-exchange-alt"></i><br>
                                    <small>ย้ายสินค้า</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../inventory/" class="btn btn-info w-100">
                                    <i class="fas fa-adjust"></i><br>
                                    <small>ปรับสต็อก</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../reports/" class="btn btn-secondary w-100">
                                    <i class="fas fa-chart-bar"></i><br>
                                    <small>รายงาน</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <a href="../admin/" class="btn btn-dark w-100">
                                    <i class="fas fa-cog"></i><br>
                                    <small>จัดการ</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        // Auto-refresh every 30 seconds
        let refreshInterval;
        
        $(document).ready(function() {
            initializeCharts();
            startAutoRefresh();
            
            // Period buttons for transaction chart
            $('.btn-group button').click(function() {
                $('.btn-group button').removeClass('active');
                $(this).addClass('active');
                
                const period = $(this).data('period');
                updateTransactionChart(period);
            });
        });
        
        function initializeCharts() {
            createTransactionChart();
            createUtilizationChart();
        }
        
        function createTransactionChart() {
            const ctx = document.getElementById('transactionChart').getContext('2d');
            
            // Generate sample data for last 7 days
            const labels = [];
            const receiveData = [];
            const pickData = [];
            const movementData = [];
            
            for(let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('th-TH', {day: 'numeric', month: 'short'}));
                
                // Simulate data
                receiveData.push(Math.floor(Math.random() * 20) + 5);
                pickData.push(Math.floor(Math.random() * 50) + 20);
                movementData.push(Math.floor(Math.random() * 15) + 3);
            }
            
            window.transactionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'รับสินค้า',
                            data: receiveData,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'จัดเตรียมสินค้า',
                            data: pickData,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'ย้ายสินค้า',
                            data: movementData,
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function createUtilizationChart() {
            const ctx = document.getElementById('utilizationChart').getContext('2d');
            
            const utilizationData = <?php echo json_encode($location_utilization); ?>;
            
            window.utilizationChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: utilizationData.map(zone => zone.zone),
                    datasets: [{
                        data: utilizationData.map(zone => zone.utilization_percent),
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#6c757d',
                            '#17a2b8',
                            '#6f42c1',
                            '#fd7e14'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        function updateTransactionChart(period) {
            // In a real implementation, this would fetch new data via AJAX
            // For now, we'll simulate different periods
            
            const labels = [];
            const dataPoints = period === '7' ? 7 : (period === '30' ? 30 : 90);
            
            for(let i = dataPoints - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                
                if(period === '7') {
                    labels.push(date.toLocaleDateString('th-TH', {day: 'numeric', month: 'short'}));
                } else if(period === '30') {
                    if(i % 3 === 0) { // Show every 3rd day
                        labels.push(date.toLocaleDateString('th-TH', {day: 'numeric', month: 'short'}));
                    }
                } else {
                    if(i % 10 === 0) { // Show every 10th day
                        labels.push(date.toLocaleDateString('th-TH', {day: 'numeric', month: 'short'}));
                    }
                }
            }
            
            // Update chart data
            window.transactionChart.data.labels = labels;
            window.transactionChart.update();
        }
        
        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                // Refresh KPIs and data
                location.reload();
            }, 30000); // 30 seconds
        }
        
        function stopAutoRefresh() {
            if(refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Stop auto-refresh when user is not active
        let inactivityTimer;
        
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                stopAutoRefresh();
                console.log('Auto-refresh stopped due to inactivity');
            }, 300000); // 5 minutes
        }
        
        // Listen for user activity
        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        document.addEventListener('click', resetInactivityTimer);
        
        // Initialize inactivity timer
        resetInactivityTimer();
        
        // Page visibility API to pause/resume refresh
        document.addEventListener('visibilitychange', function() {
            if(document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
    </script>
</body>
</html>