<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/Product.php';
require_once '../../classes/Location.php';
require_once '../../classes/Transaction.php';

// Check login and permissions
checkPermission('office'); // Only office and admin can view reports

// Initialize database connection
$db = getDBConnection();

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

// Get report statistics
function getReportStats($db) {
    $stats = [];
    
    // Transaction volume last 30 days
    $query = "SELECT COUNT(*) as total_transactions FROM picking_transactions 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stats['transactions_30days'] = $db->query($query)->fetchColumn();
    
    // Active SKUs
    $query = "SELECT COUNT(DISTINCT sku) as active_skus FROM master_sku_by_stock 
              WHERE จำนวนถุง_ปกติ > 0";
    $stats['active_skus'] = $db->query($query)->fetchColumn();
    
    // Location utilization
    $query = "SELECT 
                COUNT(*) as total_locations,
                SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) as occupied_locations
              FROM msaster_location_by_stock";
    $result = $db->query($query)->fetch(PDO::FETCH_ASSOC);
    $stats['location_utilization'] = $result['total_locations'] > 0 ? 
        round(($result['occupied_locations'] / $result['total_locations']) * 100, 1) : 0;
    
    // Expiring soon (7 days)
    $query = "SELECT COUNT(*) as expiring_count FROM msaster_location_by_stock 
              WHERE status = 'เก็บสินค้า' AND expiration_date <= UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 7 DAY))";
    $stats['expiring_soon'] = $db->query($query)->fetchColumn();
    
    return $stats;
}

$report_stats = getReportStats($db);

$page_title = 'รายงานขั้นสูง';
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
        .report-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .report-icon {
            font-size: 2.5rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .export-btn {
            margin: 2px;
        }
        .quick-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                                <h1><i class="fas fa-chart-line"></i> รายงานขั้นสูง (Advanced Reports)</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item active text-white">รายงาน</li>
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

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                        <div class="stat-number text-primary"><?php echo formatNumber($report_stats['transactions_30days']); ?></div>
                        <small class="text-muted">รายการธุรกรรม (30 วัน)</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-boxes fa-2x"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo formatNumber($report_stats['active_skus']); ?></div>
                        <small class="text-muted">SKU ที่มีสต็อก</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-warehouse fa-2x"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo $report_stats['location_utilization']; ?>%</div>
                        <small class="text-muted">การใช้งาน Location</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div class="stat-number text-danger"><?php echo formatNumber($report_stats['expiring_soon']); ?></div>
                        <small class="text-muted">ใกล้หมดอายุ (7 วัน)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="quick-filter">
                    <h5><i class="fas fa-filter"></i> ตัวกรองด่วน</h5>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="form-label">ช่วงวันที่</label>
                            <select id="date-range" class="form-select">
                                <option value="today">วันนี้</option>
                                <option value="yesterday">เมื่อวาน</option>
                                <option value="last7days" selected>7 วันที่แล้ว</option>
                                <option value="last30days">30 วันที่แล้ว</option>
                                <option value="thismonth">เดือนนี้</option>
                                <option value="lastmonth">เดือนที่แล้ว</option>
                                <option value="custom">กำหนดเอง</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Zone</label>
                            <select id="zone-filter" class="form-select">
                                <option value="">ทุก Zone</option>
                                <option value="PF-Zone">PF-Zone</option>
                                <option value="Premium Zone">Premium Zone</option>
                                <option value="Packaging Zone">Packaging Zone</option>
                                <option value="Damaged Zone">Damaged Zone</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">ประเภทรายงาน</label>
                            <select id="report-category" class="form-select">
                                <option value="">ทุกประเภท</option>
                                <option value="inventory">สินค้าคงคลัง</option>
                                <option value="transactions">ธุรกรรม</option>
                                <option value="performance">ประสิทธิภาพ</option>
                                <option value="analytics">วิเคราะห์</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">การดำเนินการ</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-outline-primary" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Categories -->
        <div class="row">
            <!-- Inventory Reports -->
            <div class="col-xl-6 col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-boxes"></i> รายงานสินค้าคงคลัง</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- ABC Analysis -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #007bff;" 
                                     onclick="openReport('abc-analysis')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>ABC Analysis</h6>
                                                <small class="text-muted">วิเคราะห์ประเภทสินค้า</small>
                                            </div>
                                            <div class="text-primary report-icon">
                                                <i class="fas fa-chart-pie"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('abc-analysis', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('abc-analysis', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock Aging -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #ffc107;" 
                                     onclick="openReport('stock-aging')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Stock Aging</h6>
                                                <small class="text-muted">อายุสินค้าคงคลัง</small>
                                            </div>
                                            <div class="text-warning report-icon">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('stock-aging', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('stock-aging', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Inventory Valuation -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #28a745;" 
                                     onclick="openReport('inventory-valuation')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Inventory Valuation</h6>
                                                <small class="text-muted">มูลค่าสินค้าคงคลัง</small>
                                            </div>
                                            <div class="text-success report-icon">
                                                <i class="fas fa-dollar-sign"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('inventory-valuation', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('inventory-valuation', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Low Stock Alert -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #dc3545;" 
                                     onclick="openReport('low-stock')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Low Stock Alert</h6>
                                                <small class="text-muted">สต็อกต่ำ</small>
                                            </div>
                                            <div class="text-danger report-icon">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('low-stock', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('low-stock', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Reports -->
            <div class="col-xl-6 col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-exchange-alt"></i> รายงานธุรกรรม</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Transaction History -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #17a2b8;" 
                                     onclick="openReport('transaction-history')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Transaction History</h6>
                                                <small class="text-muted">ประวัติธุรกรรม</small>
                                            </div>
                                            <div class="text-info report-icon">
                                                <i class="fas fa-history"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('transaction-history', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('transaction-history', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pick Efficiency -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #6f42c1;" 
                                     onclick="openReport('pick-efficiency')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Pick Efficiency</h6>
                                                <small class="text-muted">ประสิทธิภาพการเบิก</small>
                                            </div>
                                            <div class="text-purple report-icon" style="color: #6f42c1;">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('pick-efficiency', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('pick-efficiency', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Movement Summary -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #fd7e14;" 
                                     onclick="openReport('movement-summary')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Movement Summary</h6>
                                                <small class="text-muted">สรุปการย้ายสินค้า</small>
                                            </div>
                                            <div class="text-orange report-icon" style="color: #fd7e14;">
                                                <i class="fas fa-truck"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('movement-summary', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('movement-summary', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FEFO Compliance -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #20c997;" 
                                     onclick="openReport('fefo-compliance')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>FEFO Compliance</h6>
                                                <small class="text-muted">การปฏิบัติตาม FEFO</small>
                                            </div>
                                            <div class="text-teal report-icon" style="color: #20c997;">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('fefo-compliance', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('fefo-compliance', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Analytics Row -->
        <div class="row">
            <!-- Performance Reports -->
            <div class="col-xl-6 col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-chart-bar"></i> รายงานประสิทธิภาพ</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Space Utilization -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #ffc107;" 
                                     onclick="openReport('space-utilization')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Space Utilization</h6>
                                                <small class="text-muted">การใช้พื้นที่</small>
                                            </div>
                                            <div class="text-warning report-icon">
                                                <i class="fas fa-warehouse"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('space-utilization', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('space-utilization', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Productivity Analysis -->
                            <div class="col-md-6 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #e83e8c;" 
                                     onclick="openReport('productivity-analysis')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Productivity Analysis</h6>
                                                <small class="text-muted">วิเคราะห์ประสิทธิภาพ</small>
                                            </div>
                                            <div class="text-pink report-icon" style="color: #e83e8c;">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="export-buttons">
                                                <button class="btn btn-sm btn-outline-success export-btn" 
                                                        onclick="exportReport('productivity-analysis', 'excel'); event.stopPropagation();">
                                                    <i class="fas fa-file-excel"></i> Excel
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger export-btn" 
                                                        onclick="exportReport('productivity-analysis', 'pdf'); event.stopPropagation();">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Reports -->
            <div class="col-xl-6 col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5><i class="fas fa-cogs"></i> รายงานแบบกำหนดเอง</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Report Builder -->
                            <div class="col-md-12 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #6c757d;" 
                                     onclick="openReport('report-builder')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Report Builder</h6>
                                                <small class="text-muted">สร้างรายงานแบบกำหนดเอง</small>
                                            </div>
                                            <div class="text-secondary report-icon">
                                                <i class="fas fa-tools"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <p class="small text-muted">สร้างรายงานตามความต้องการของคุณ</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduled Reports -->
                            <div class="col-md-12 mb-3">
                                <div class="report-card card h-100" style="border-left-color: #17a2b8;" 
                                     onclick="openReport('scheduled-reports')">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Scheduled Reports</h6>
                                                <small class="text-muted">รายงานแบบตั้งเวลา</small>
                                            </div>
                                            <div class="text-info report-icon">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <p class="small text-muted">ตั้งค่าการส่งรายงานอัตโนมัติทาง Email</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Exports -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-download"></i> ไฟล์ที่ส่งออกล่าสุด</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>รายงาน</th>
                                        <th>รูปแบบ</th>
                                        <th>วันที่สร้าง</th>
                                        <th>ขนาดไฟล์</th>
                                        <th>สถานะ</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-exports">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">ยังไม่มีการส่งออกรายงาน</td>
                                    </tr>
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
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        function openReport(reportType) {
            // Open detailed report page
            window.open(`report-viewer.php?type=${reportType}`, '_blank');
        }
        
        function exportReport(reportType, format) {
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';
            
            // Get filter values
            const dateRange = document.getElementById('date-range').value;
            const zoneFilter = document.getElementById('zone-filter').value;
            
            // Create form data
            const formData = new FormData();
            formData.append('report_type', reportType);
            formData.append('format', format);
            formData.append('date_range', dateRange);
            formData.append('zone_filter', zoneFilter);
            
            // Send export request
            fetch('export-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Download the file
                    const a = document.createElement('a');
                    a.href = data.file_url;
                    a.download = data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    
                    // Update recent exports table
                    updateRecentExports();
                    
                    // Show success message
                    showNotification('success', 'ส่งออกรายงานสำเร็จ');
                } else {
                    showNotification('error', 'เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Export error:', error);
                showNotification('error', 'เกิดข้อผิดพลาดในการส่งออก');
            })
            .finally(() => {
                // Restore button state
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        }
        
        function applyFilters() {
            const dateRange = document.getElementById('date-range').value;
            const zoneFilter = document.getElementById('zone-filter').value;
            const reportCategory = document.getElementById('report-category').value;
            
            // Filter report cards based on category
            const cards = document.querySelectorAll('.report-card');
            cards.forEach(card => {
                if(reportCategory === '') {
                    card.style.display = 'block';
                } else {
                    // Simple category filtering - in real implementation, use data attributes
                    card.style.display = 'block';
                }
            });
            
            showNotification('info', 'ตัวกรองถูกนำไปใช้แล้ว');
        }
        
        function updateRecentExports() {
            // Fetch and update recent exports table
            fetch('get-recent-exports.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('recent-exports');
                if(data.length > 0) {
                    tbody.innerHTML = data.map(export_item => `
                        <tr>
                            <td>${export_item.report_name}</td>
                            <td><span class="badge bg-${export_item.format === 'excel' ? 'success' : 'danger'}">${export_item.format.toUpperCase()}</span></td>
                            <td>${export_item.created_at}</td>
                            <td>${export_item.file_size}</td>
                            <td><span class="badge bg-success">พร้อม</span></td>
                            <td>
                                <a href="${export_item.file_url}" class="btn btn-sm btn-outline-primary" download>
                                    <i class="fas fa-download"></i> ดาวน์โหลด
                                </a>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">ยังไม่มีการส่งออกรายงาน</td></tr>';
                }
            })
            .catch(error => console.error('Error fetching recent exports:', error));
        }
        
        function showNotification(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'error' ? 'alert-danger' : 'alert-info';
            
            const notification = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                if(alerts.length > 0) {
                    alerts[alerts.length - 1].remove();
                }
            }, 5000);
        }
        
        // Initialize page
        $(document).ready(function() {
            updateRecentExports();
            
            // Auto-refresh recent exports every 30 seconds
            setInterval(updateRecentExports, 30000);
        });
    </script>
</body>
</html>