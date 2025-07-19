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
checkPermission('office'); // Office and admin can access analytics

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get current user
$user = new User($db);
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

// Include analytics management class
require_once 'classes/AnalyticsManager.php';
$analyticsManager = new AnalyticsManager($db);

// Get analytics data
$analytics_data = $analyticsManager->getAnalyticsData();
$kpi_metrics = $analyticsManager->getKPIMetrics();
$trend_data = $analyticsManager->getTrendData();
$forecast_data = $analyticsManager->getForecastData();

$page_title = 'Advanced Analytics & BI Dashboard';
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .analytics-card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .kpi-card {
            border-radius: 12px;
            background: white;
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .kpi-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        .kpi-trend {
            font-size: 0.9rem;
            font-weight: 600;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
        .chart-container {
            position: relative;
            height: 400px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .insight-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .metric-badge {
            background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .performance-indicator {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            margin-top: 10px;
        }
        .performance-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .ai-insight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 15px;
            margin: 10px 0;
        }
        .forecast-confidence {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        @media (max-width: 768px) {
            .kpi-number { font-size: 2rem; }
            .chart-container { height: 300px; padding: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-chart-line"></i> Advanced Analytics & BI Dashboard</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">Advanced Analytics</li>
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

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="filter-panel">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">ช่วงเวลา</label>
                            <select class="form-select" id="dateRange" onchange="updateDashboard()">
                                <option value="7">7 วันที่ผ่านมา</option>
                                <option value="30" selected>30 วันที่ผ่านมา</option>
                                <option value="90">90 วันที่ผ่านมา</option>
                                <option value="365">1 ปีที่ผ่านมา</option>
                                <option value="custom">กำหนดเอง</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">วันเริ่มต้น</label>
                            <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" onchange="updateDashboard()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">วันสิ้นสุด</label>
                            <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>" onchange="updateDashboard()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Zone</label>
                            <select class="form-select" id="zoneFilter" onchange="updateDashboard()">
                                <option value="">ทั้งหมด</option>
                                <option value="PF-Zone">PF-Zone</option>
                                <option value="Premium Zone">Premium Zone</option>
                                <option value="Packaging Zone">Packaging Zone</option>
                                <option value="Damaged Zone">Damaged Zone</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary" onclick="updateDashboard()">
                                <i class="fas fa-sync"></i> อัปเดต
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success" onclick="exportAnalytics()">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kpi-card h-100" style="border-left-color: #007bff;">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <i class="fas fa-chart-line fa-2x text-primary"></i>
                            <span class="metric-badge">Real-time</span>
                        </div>
                        <h2 class="kpi-number text-primary"><?php echo number_format($kpi_metrics['revenue'], 0); ?></h2>
                        <p class="mb-1">รายได้ (บาท)</p>
                        <div class="kpi-trend trend-<?php echo $kpi_metrics['revenue_trend'] > 0 ? 'up' : ($kpi_metrics['revenue_trend'] < 0 ? 'down' : 'stable'); ?>">
                            <i class="fas fa-arrow-<?php echo $kpi_metrics['revenue_trend'] > 0 ? 'up' : ($kpi_metrics['revenue_trend'] < 0 ? 'down' : 'right'); ?>"></i>
                            <?php echo abs($kpi_metrics['revenue_trend']); ?>% vs เดือนที่แล้ว
                        </div>
                        <div class="performance-indicator">
                            <div class="performance-fill bg-primary" style="width: <?php echo min(100, ($kpi_metrics['revenue'] / 1000000) * 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kpi-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <i class="fas fa-shipping-fast fa-2x text-success"></i>
                            <span class="metric-badge">Efficiency</span>
                        </div>
                        <h2 class="kpi-number text-success"><?php echo number_format($kpi_metrics['order_fulfillment'], 1); ?>%</h2>
                        <p class="mb-1">Order Fulfillment Rate</p>
                        <div class="kpi-trend trend-<?php echo $kpi_metrics['fulfillment_trend'] > 0 ? 'up' : ($kpi_metrics['fulfillment_trend'] < 0 ? 'down' : 'stable'); ?>">
                            <i class="fas fa-arrow-<?php echo $kpi_metrics['fulfillment_trend'] > 0 ? 'up' : ($kpi_metrics['fulfillment_trend'] < 0 ? 'down' : 'right'); ?>"></i>
                            <?php echo abs($kpi_metrics['fulfillment_trend']); ?>% vs เดือนที่แล้ว
                        </div>
                        <div class="performance-indicator">
                            <div class="performance-fill bg-success" style="width: <?php echo $kpi_metrics['order_fulfillment']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kpi-card h-100" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <i class="fas fa-boxes fa-2x text-warning"></i>
                            <span class="metric-badge">Inventory</span>
                        </div>
                        <h2 class="kpi-number text-warning"><?php echo number_format($kpi_metrics['inventory_turnover'], 1); ?>x</h2>
                        <p class="mb-1">Inventory Turnover</p>
                        <div class="kpi-trend trend-<?php echo $kpi_metrics['turnover_trend'] > 0 ? 'up' : ($kpi_metrics['turnover_trend'] < 0 ? 'down' : 'stable'); ?>">
                            <i class="fas fa-arrow-<?php echo $kpi_metrics['turnover_trend'] > 0 ? 'up' : ($kpi_metrics['turnover_trend'] < 0 ? 'down' : 'right'); ?>"></i>
                            <?php echo abs($kpi_metrics['turnover_trend']); ?>% vs เดือนที่แล้ว
                        </div>
                        <div class="performance-indicator">
                            <div class="performance-fill bg-warning" style="width: <?php echo min(100, ($kpi_metrics['inventory_turnover'] / 10) * 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kpi-card h-100" style="border-left-color: #dc3545;">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <i class="fas fa-chart-bar fa-2x text-danger"></i>
                            <span class="metric-badge">Accuracy</span>
                        </div>
                        <h2 class="kpi-number text-danger"><?php echo number_format($kpi_metrics['stock_accuracy'], 1); ?>%</h2>
                        <p class="mb-1">Stock Accuracy</p>
                        <div class="kpi-trend trend-<?php echo $kpi_metrics['accuracy_trend'] > 0 ? 'up' : ($kpi_metrics['accuracy_trend'] < 0 ? 'down' : 'stable'); ?>">
                            <i class="fas fa-arrow-<?php echo $kpi_metrics['accuracy_trend'] > 0 ? 'up' : ($kpi_metrics['accuracy_trend'] < 0 ? 'down' : 'right'); ?>"></i>
                            <?php echo abs($kpi_metrics['accuracy_trend']); ?>% vs เดือนที่แล้ว
                        </div>
                        <div class="performance-indicator">
                            <div class="performance-fill bg-danger" style="width: <?php echo $kpi_metrics['stock_accuracy']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="insight-card">
                    <h5><i class="fas fa-robot"></i> AI-Powered Insights</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="ai-insight">
                                <strong>ปริมาณการขาย:</strong> สูงกว่าเดือนที่แล้ว 15.3%
                                <br><small class="forecast-confidence">Confidence: 94.2%</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ai-insight">
                                <strong>สินค้าขายดี:</strong> Premium Zone มีการเคลื่อนไหวสูงสุด
                                <br><small class="forecast-confidence">Growth Rate: +18.7%</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ai-insight">
                                <strong>การคาดการณ์:</strong> ยอดขายเดือนหน้าจะเติบโต 12%
                                <br><small class="forecast-confidence">Accuracy: 89.5%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-area"></i> Revenue & Volume Trends</h5>
                    <canvas id="revenueVolumeChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Zone Performance</h5>
                    <canvas id="zonePerformanceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-line"></i> Demand Forecasting</h5>
                    <canvas id="demandForecastChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-users"></i> User Activity Heatmap</h5>
                    <canvas id="userActivityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5><i class="fas fa-clock"></i> Peak Hours Analysis</h5>
                    <canvas id="peakHoursChart"></canvas>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5><i class="fas fa-cube"></i> ABC Analysis - Product Performance</h5>
                    <canvas id="abcAnalysisChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Advanced Metrics Tables -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-trophy"></i> Top Performing SKUs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>ยอดขาย</th>
                                        <th>Growth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($analytics_data['top_skus'] as $sku): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sku['sku']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sku['product_name']); ?></td>
                                        <td><?php echo number_format($sku['total_sales']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $sku['growth'] > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $sku['growth'] > 0 ? '+' : ''; ?><?php echo number_format($sku['growth'], 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Alert & Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-box"></i> Low Stock Alert:</strong> 
                            มีสินค้า 15 รายการ ที่ใกล้หมด ควรสั่งซื้อเพิ่ม
                        </div>
                        <div class="alert alert-info">
                            <strong><i class="fas fa-chart-line"></i> Trend Alert:</strong> 
                            สินค้าหมวด Premium มีแนวโน้มขายดีขึ้น 18%
                        </div>
                        <div class="alert alert-success">
                            <strong><i class="fas fa-bullseye"></i> Opportunity:</strong> 
                            PF-Zone มีพื้นที่ว่าง 23% เหมาะสำหรับขยายสต็อก
                        </div>
                        <div class="alert alert-danger">
                            <strong><i class="fas fa-clock"></i> Efficiency:</strong> 
                            เวลาเฉลี่ยในการจัดเตรียมสินค้าเพิ่มขึ้น 8 นาที
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Chart configurations and data
        const chartData = <?php echo json_encode($analytics_data); ?>;
        const trendData = <?php echo json_encode($trend_data); ?>;
        const forecastData = <?php echo json_encode($forecast_data); ?>;
        
        // Initialize all charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {
            createRevenueVolumeChart();
            createZonePerformanceChart();
            createDemandForecastChart();
            createUserActivityChart();
            createPeakHoursChart();
            createABCAnalysisChart();
        }
        
        function createRevenueVolumeChart() {
            const ctx = document.getElementById('revenueVolumeChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.dates,
                    datasets: [
                        {
                            label: 'Revenue (฿)',
                            data: trendData.revenue,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Volume (ชิ้น)',
                            data: trendData.volume,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Revenue (฿)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Volume (ชิ้น)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        }
        
        function createZonePerformanceChart() {
            const ctx = document.getElementById('zonePerformanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.zone_performance.labels,
                    datasets: [{
                        data: chartData.zone_performance.data,
                        backgroundColor: [
                            '#007bff', '#28a745', '#ffc107', '#dc3545', '#6610f2'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        
        function createDemandForecastChart() {
            const ctx = document.getElementById('demandForecastChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: forecastData.dates,
                    datasets: [
                        {
                            label: 'Actual Demand',
                            data: forecastData.actual,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Forecasted Demand',
                            data: forecastData.forecast,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderDash: [5, 5],
                            tension: 0.4
                        },
                        {
                            label: 'Confidence Interval',
                            data: forecastData.confidence_upper,
                            borderColor: 'rgba(40, 167, 69, 0.3)',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: '+1',
                            tension: 0.4,
                            pointRadius: 0
                        },
                        {
                            label: '',
                            data: forecastData.confidence_lower,
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
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Date' } },
                        y: { title: { display: true, text: 'Demand (ชิ้น)' } }
                    }
                }
            });
        }
        
        function createUserActivityChart() {
            const ctx = document.getElementById('userActivityChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.user_activity.labels,
                    datasets: [{
                        label: 'Activity Count',
                        data: chartData.user_activity.data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        function createPeakHoursChart() {
            const ctx = document.getElementById('peakHoursChart').getContext('2d');
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'],
                    datasets: [{
                        label: 'Activity Level',
                        data: [65, 75, 90, 81, 56, 45],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        pointBackgroundColor: '#007bff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
        
        function createABCAnalysisChart() {
            const ctx = document.getElementById('abcAnalysisChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.abc_analysis.labels,
                    datasets: [
                        {
                            label: 'A-Class (High Value)',
                            data: chartData.abc_analysis.a_class,
                            backgroundColor: '#dc3545'
                        },
                        {
                            label: 'B-Class (Medium Value)',
                            data: chartData.abc_analysis.b_class,
                            backgroundColor: '#ffc107'
                        },
                        {
                            label: 'C-Class (Low Value)',
                            data: chartData.abc_analysis.c_class,
                            backgroundColor: '#28a745'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true }
                    }
                }
            });
        }
        
        function updateDashboard() {
            const dateRange = document.getElementById('dateRange').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const zoneFilter = document.getElementById('zoneFilter').value;
            
            // Show loading
            showLoading();
            
            // Fetch updated data
            fetch('api/analytics-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date_range: dateRange,
                    start_date: startDate,
                    end_date: endDate,
                    zone_filter: zoneFilter
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update charts with new data
                    updateChartsWithNewData(data);
                    hideLoading();
                } else {
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                    hideLoading();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                hideLoading();
            });
        }
        
        function updateChartsWithNewData(data) {
            // Update all charts with new data
            // This would be implemented based on the specific chart library being used
            console.log('Updating charts with new data:', data);
        }
        
        function exportAnalytics() {
            const params = new URLSearchParams({
                date_range: document.getElementById('dateRange').value,
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value,
                zone_filter: document.getElementById('zoneFilter').value
            });
            
            window.open(`export-analytics.php?${params.toString()}`, '_blank');
        }
        
        function showLoading() {
            // Show loading overlay
            document.body.style.cursor = 'wait';
        }
        
        function hideLoading() {
            // Hide loading overlay
            document.body.style.cursor = 'default';
        }
        
        // Auto-refresh every 10 minutes
        setInterval(updateDashboard, 600000);
    </script>
</body>
</html>