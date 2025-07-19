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
checkPermission('office');

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get reorder recommendation ID
$reorder_id = $_GET['id'] ?? 0;

if(!$reorder_id) {
    header('Location: index.php');
    exit;
}

// Include reorder management class
require_once 'classes/ReorderManager.php';
$reorderManager = new ReorderManager($db);

// Get recommendation details
$query = "SELECT 
            r.*,
            p.ชื่อ_สินค้า as product_name,
            p.หน่วยนับ as unit,
            p.ราคาต้นทุน as unit_cost,
            p.ผู้จำหน่าย as supplier,
            u.ชื่อ_สกุล as approved_by_name
          FROM reorder_recommendations r
          LEFT JOIN master_sku_by_stock p ON r.sku = p.sku
          LEFT JOIN users u ON r.approved_by = u.user_id
          WHERE r.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$reorder_id]);
$recommendation = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$recommendation) {
    header('Location: index.php');
    exit;
}

// Parse demand forecast data
$demand_forecast = json_decode($recommendation['demand_forecast'], true);

// Get historical demand for this SKU
$query = "SELECT 
            DATE(created_at) as date,
            SUM(ชิ้น) as daily_demand
          FROM picking_transactions 
          WHERE sku = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
          GROUP BY DATE(created_at)
          ORDER BY date";

$stmt = $db->prepare($query);
$stmt->execute([$recommendation['sku']]);
$historical_demand = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions for this SKU
$query = "SELECT 
            t.*,
            u.ชื่อ_สกุล as picker_name
          FROM picking_transactions t
          LEFT JOIN users u ON t.ผู้ใช้งาน = u.user_id
          WHERE t.sku = ?
          ORDER BY t.created_at DESC
          LIMIT 20";

$stmt = $db->prepare($query);
$stmt->execute([$recommendation['sku']]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'รายละเอียดคำแนะนำการสั่งซื้อ';
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
    
    <style>
        .detail-card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .metric-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .priority-urgent { border-left: 5px solid #dc3545; }
        .priority-high { border-left: 5px solid #fd7e14; }
        .priority-medium { border-left: 5px solid #ffc107; }
        .priority-low { border-left: 5px solid #28a745; }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .ai-insights {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-info-circle"></i> รายละเอียดคำแนะนำการสั่งซื้อ</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../">หน้าหลัก</a></li>
                                <li class="breadcrumb-item"><a href="index.php">การสั่งซื้ออัตโนมัติ</a></li>
                                <li class="breadcrumb-item active">รายละเอียด</li>
                            </ol>
                        </nav>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="fas fa-times"></i> ปิด
                    </button>
                </div>
            </div>
        </div>

        <!-- Product Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card detail-card priority-<?php echo strtolower($recommendation['priority']); ?>">
                    <div class="card-header">
                        <h5><i class="fas fa-box"></i> ข้อมูลสินค้า</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>SKU:</strong></td>
                                        <td><?php echo htmlspecialchars($recommendation['sku']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ชื่อสินค้า:</strong></td>
                                        <td><?php echo htmlspecialchars($recommendation['product_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>หน่วยนับ:</strong></td>
                                        <td><?php echo htmlspecialchars($recommendation['unit']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ผู้จำหน่าย:</strong></td>
                                        <td><?php echo htmlspecialchars($recommendation['supplier']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>ราคาต้นทุน:</strong></td>
                                        <td>฿<?php echo number_format($recommendation['unit_cost'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Lead Time:</strong></td>
                                        <td><?php echo $recommendation['lead_time_days']; ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <td><strong>ลำดับความสำคัญ:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $recommendation['priority'] === 'urgent' ? 'danger' : ($recommendation['priority'] === 'high' ? 'warning' : 'success'); ?>">
                                                <?php echo strtoupper($recommendation['priority']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>สถานะ:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $recommendation['status'] === 'pending' ? 'warning' : ($recommendation['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                                <?php echo strtoupper($recommendation['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-value"><?php echo number_format($recommendation['current_stock']); ?></div>
                    <div>สต็อกปัจจุบัน</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-value"><?php echo number_format($recommendation['reorder_point']); ?></div>
                    <div>จุดสั่งซื้อ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-value"><?php echo number_format($recommendation['recommended_quantity']); ?></div>
                    <div>แนะนำสั่งซื้อ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-box">
                    <div class="metric-value">฿<?php echo number_format($recommendation['estimated_cost']); ?></div>
                    <div>ราคาประมาณ</div>
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="ai-insights">
                    <h5><i class="fas fa-robot"></i> AI Analysis & Insights</h5>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="h3"><?php echo round($recommendation['ai_confidence'] * 100, 1); ?>%</div>
                                <small>AI Confidence Score</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="h3"><?php echo number_format($recommendation['seasonality_factor'], 2); ?>x</div>
                                <small>Seasonality Factor</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="h3"><?php echo $demand_forecast['method'] ?? 'N/A'; ?></div>
                                <small>Forecast Method</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(isset($demand_forecast['components'])): ?>
                    <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                    <div class="row">
                        <div class="col-md-4">
                            <small>Moving Average: <?php echo number_format($demand_forecast['components']['moving_avg'], 2); ?></small>
                        </div>
                        <div class="col-md-4">
                            <small>Exponential Smoothing: <?php echo number_format($demand_forecast['components']['exponential'], 2); ?></small>
                        </div>
                        <div class="col-md-4">
                            <small>Linear Trend: <?php echo number_format($demand_forecast['components']['linear'], 2); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Demand History Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card detail-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> ประวัติความต้องการ (90 วันที่ผ่านมา)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="demandHistoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row">
            <div class="col-12">
                <div class="card detail-card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> รายการเบิกล่าสุด (20 รายการ)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>วันที่เวลา</th>
                                        <th>จำนวน</th>
                                        <th>ผู้เบิก</th>
                                        <th>ที่ตั้ง</th>
                                        <th>หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($recent_transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">ไม่พบข้อมูลการเบิก</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach($recent_transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo formatDateTime($transaction['created_at']); ?></td>
                                            <td><?php echo number_format($transaction['ชิ้น']); ?> <?php echo htmlspecialchars($recommendation['unit']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['picker_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['ตำแหน่ง'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['หมายเหตุ'] ?? '-'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Create demand history chart
        const historicalData = <?php echo json_encode($historical_demand); ?>;
        const ctx = document.getElementById('demandHistoryChart').getContext('2d');
        
        const dates = historicalData.map(item => item.date);
        const demands = historicalData.map(item => parseFloat(item.daily_demand));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'ความต้องการรายวัน',
                    data: demands,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'แนวโน้มความต้องการสินค้า'
                    },
                    legend: {
                        display: false
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
                            text: 'จำนวน (<?php echo htmlspecialchars($recommendation['unit']); ?>)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
        
        function formatDateTime(dateTime) {
            return new Date(dateTime).toLocaleString('th-TH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>