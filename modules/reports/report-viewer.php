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

// Get report type from URL
$report_type = $_GET['type'] ?? '';
if(empty($report_type)) {
    header('Location: index.php');
    exit;
}

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

// Report configuration
$report_configs = [
    'abc-analysis' => [
        'title' => 'ABC Analysis Report',
        'subtitle' => 'วิเคราะห์ประเภทสินค้าตามมูลค่าการเบิก',
        'icon' => 'fas fa-chart-pie',
        'color' => 'primary'
    ],
    'stock-aging' => [
        'title' => 'Stock Aging Report',
        'subtitle' => 'รายงานอายุสินค้าคงคลังและการหมดอายุ',
        'icon' => 'fas fa-clock',
        'color' => 'warning'
    ],
    'inventory-valuation' => [
        'title' => 'Inventory Valuation Report',
        'subtitle' => 'รายงานมูลค่าสินค้าคงคลังแต่ละ Zone',
        'icon' => 'fas fa-dollar-sign',
        'color' => 'success'
    ],
    'low-stock' => [
        'title' => 'Low Stock Alert Report',
        'subtitle' => 'รายงานสินค้าที่มีสต็อกต่ำและหมดสต็อก',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'danger'
    ],
    'transaction-history' => [
        'title' => 'Transaction History Report',
        'subtitle' => 'รายงานประวัติธุรกรรมทั้งหมด',
        'icon' => 'fas fa-history',
        'color' => 'info'
    ],
    'pick-efficiency' => [
        'title' => 'Pick Efficiency Report',
        'subtitle' => 'รายงานประสิทธิภาพการเบิกสินค้าของผู้ใช้งาน',
        'icon' => 'fas fa-tachometer-alt',
        'color' => 'purple'
    ],
    'movement-summary' => [
        'title' => 'Movement Summary Report',
        'subtitle' => 'รายงานสรุปการย้ายสินค้าระหว่าง Location',
        'icon' => 'fas fa-truck',
        'color' => 'warning'
    ],
    'fefo-compliance' => [
        'title' => 'FEFO Compliance Report',
        'subtitle' => 'รายงานการปฏิบัติตามหลัก FEFO',
        'icon' => 'fas fa-check-circle',
        'color' => 'success'
    ],
    'space-utilization' => [
        'title' => 'Space Utilization Report',
        'subtitle' => 'รายงานการใช้พื้นที่จัดเก็บแต่ละ Zone',
        'icon' => 'fas fa-warehouse',
        'color' => 'info'
    ],
    'productivity-analysis' => [
        'title' => 'Productivity Analysis Report',
        'subtitle' => 'รายงานวิเคราะห์ประสิทธิภาพผู้ใช้งาน',
        'icon' => 'fas fa-chart-line',
        'color' => 'primary'
    ]
];

$config = $report_configs[$report_type] ?? [
    'title' => 'Unknown Report',
    'subtitle' => 'รายงานไม่ทราบประเภท',
    'icon' => 'fas fa-question',
    'color' => 'secondary'
];

$page_title = $config['title'];
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
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
        }
        .filter-panel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .export-toolbar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>กำลังโหลดข้อมูล...</div>
        </div>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><i class="<?php echo $config['icon']; ?>"></i> <?php echo $config['title']; ?></h1>
                            <p class="mb-0"><?php echo $config['subtitle']; ?></p>
                            <nav aria-label="breadcrumb" class="mt-2">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="index.php" class="text-white">รายงาน</a></li>
                                    <li class="breadcrumb-item active text-white"><?php echo $config['title']; ?></li>
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
        <!-- Filter Panel -->
        <div class="filter-panel">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">ช่วงวันที่</label>
                    <select id="date-range" class="form-select">
                        <option value="today">วันนี้</option>
                        <option value="yesterday">เมื่อวาน</option>
                        <option value="last7days" selected>7 วันที่แล้ว</option>
                        <option value="last30days">30 วันที่แล้ว</option>
                        <option value="thismonth">เดือนนี้</option>
                        <option value="lastmonth">เดือนที่แล้ว</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Zone</label>
                    <select id="zone-filter" class="form-select">
                        <option value="">ทุก Zone</option>
                        <option value="PF-Zone">PF-Zone</option>
                        <option value="Premium Zone">Premium Zone</option>
                        <option value="Packaging Zone">Packaging Zone</option>
                        <option value="Damaged Zone">Damaged Zone</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">การดำเนินการ</label>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="loadReportData()">
                            <i class="fas fa-sync"></i> รีเฟรชข้อมูล
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">แสดงผล</label>
                    <div class="btn-group d-grid" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-view="table" onclick="switchView('table')">
                            <i class="fas fa-table"></i> ตาราง
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-view="chart" onclick="switchView('chart')">
                            <i class="fas fa-chart-bar"></i> กราф
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mb-4" id="summary-stats">
            <!-- Summary cards will be populated here -->
        </div>

        <!-- Export Toolbar -->
        <div class="export-toolbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">รายละเอียดรายงาน</h5>
                    <small class="text-muted">อัปเดตล่าสุด: <span id="last-updated">-</span></small>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-success" onclick="exportData('excel')">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="exportData('pdf')">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> พิมพ์
                    </button>
                </div>
            </div>
        </div>

        <!-- Chart View -->
        <div id="chart-view" style="display: none;">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-chart-bar"></i> กราฟแสดงข้อมูล</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="main-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-chart-pie"></i> สัดส่วน</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="pie-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div id="table-view">
            <div class="table-container">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="report-table" class="table table-striped table-hover">
                                <thead>
                                    <!-- Headers will be populated dynamically -->
                                </thead>
                                <tbody>
                                    <!-- Data will be populated dynamically -->
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        let reportTable;
        let mainChart;
        let pieChart;
        let currentData = [];
        
        const reportType = '<?php echo $report_type; ?>';
        
        $(document).ready(function() {
            loadReportData();
        });
        
        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }
        
        function loadReportData() {
            showLoading();
            
            const dateRange = document.getElementById('date-range').value;
            const zoneFilter = document.getElementById('zone-filter').value;
            
            const formData = new FormData();
            formData.append('report_type', reportType);
            formData.append('date_range', dateRange);
            formData.append('zone_filter', zoneFilter);
            formData.append('action', 'load_data');
            
            fetch('report-data-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    currentData = data;
                    updateSummaryStats(data.summary);
                    updateTable(data);
                    updateCharts(data);
                    document.getElementById('last-updated').textContent = new Date().toLocaleString('th-TH');
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error loading report data:', error);
                alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
            })
            .finally(() => {
                hideLoading();
            });
        }
        
        function updateSummaryStats(summary) {
            const container = document.getElementById('summary-stats');
            container.innerHTML = '';
            
            if(!summary) return;
            
            const stats = [];
            
            // Configure stats based on report type
            switch(reportType) {
                case 'abc-analysis':
                    stats.push(
                        {label: 'รายการทั้งหมด', value: summary.total_items || 0, icon: 'fas fa-boxes', color: 'primary'},
                        {label: 'มูลค่ารวม', value: (summary.total_value || 0).toLocaleString(), icon: 'fas fa-dollar-sign', color: 'success'},
                        {label: 'ประเภท A', value: summary.class_a_count || 0, icon: 'fas fa-star', color: 'warning'},
                        {label: 'ประเภท B', value: summary.class_b_count || 0, icon: 'fas fa-star-half-alt', color: 'info'}
                    );
                    break;
                case 'stock-aging':
                    stats.push(
                        {label: 'รายการทั้งหมด', value: summary.total_items || 0, icon: 'fas fa-boxes', color: 'primary'},
                        {label: 'หมดอายุแล้ว', value: summary.expired || 0, icon: 'fas fa-exclamation-triangle', color: 'danger'},
                        {label: 'หมดอายุ 7 วัน', value: summary.expiring_7days || 0, icon: 'fas fa-clock', color: 'warning'},
                        {label: 'หมดอายุ 30 วัน', value: summary.expiring_30days || 0, icon: 'fas fa-calendar', color: 'info'}
                    );
                    break;
                default:
                    stats.push(
                        {label: 'รายการทั้งหมด', value: summary.total_items || summary.total_transactions || 0, icon: 'fas fa-list', color: 'primary'},
                        {label: 'จำนวนรวม', value: (summary.total_quantity || 0).toLocaleString(), icon: 'fas fa-cubes', color: 'success'},
                        {label: 'น้ำหนักรวม', value: (summary.total_weight || 0).toFixed(2) + ' กก.', icon: 'fas fa-weight', color: 'info'},
                        {label: 'ผู้ใช้งาน', value: summary.unique_users || summary.total_users || 0, icon: 'fas fa-users', color: 'warning'}
                    );
            }
            
            stats.forEach(stat => {
                container.innerHTML += `
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card h-100" style="border-left-color: var(--bs-${stat.color});">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h4 text-${stat.color} mb-0">${stat.value}</div>
                                        <small class="text-muted">${stat.label}</small>
                                    </div>
                                    <div class="text-${stat.color}">
                                        <i class="${stat.icon} fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        function updateTable(data) {
            if(reportTable) {
                reportTable.destroy();
            }
            
            const table = document.getElementById('report-table');
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            
            // Update headers
            thead.innerHTML = '<tr>' + data.headers.map(header => `<th>${header}</th>`).join('') + '</tr>';
            
            // Update data
            tbody.innerHTML = '';
            data.data.forEach(row => {
                const tr = document.createElement('tr');
                Object.values(row).forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell || '-';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            
            // Initialize DataTable
            reportTable = $('#report-table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });
        }
        
        function updateCharts(data) {
            // Destroy existing charts
            if(mainChart) mainChart.destroy();
            if(pieChart) pieChart.destroy();
            
            // Create charts based on report type
            switch(reportType) {
                case 'abc-analysis':
                    createABCCharts(data);
                    break;
                case 'stock-aging':
                    createStockAgingCharts(data);
                    break;
                case 'space-utilization':
                    createSpaceUtilizationCharts(data);
                    break;
                default:
                    createGenericCharts(data);
            }
        }
        
        function createABCCharts(data) {
            const abcData = data.data.reduce((acc, item) => {
                acc[item.abc_class] = (acc[item.abc_class] || 0) + parseFloat(item.total_value || 0);
                return acc;
            }, {});
            
            // Bar chart
            const ctx1 = document.getElementById('main-chart').getContext('2d');
            mainChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.data.slice(0, 10).map(item => item.sku),
                    datasets: [{
                        label: 'มูลค่า',
                        data: data.data.slice(0, 10).map(item => parseFloat(item.total_value || 0)),
                        backgroundColor: 'rgba(54, 162, 235, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 10 SKU ตามมูลค่า'
                        }
                    }
                }
            });
            
            // Pie chart
            const ctx2 = document.getElementById('pie-chart').getContext('2d');
            pieChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(abcData),
                    datasets: [{
                        data: Object.values(abcData),
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'การกระจายมูลค่าตาม ABC'
                        }
                    }
                }
            });
        }
        
        function createStockAgingCharts(data) {
            const agingData = data.data.reduce((acc, item) => {
                acc[item.aging_category] = (acc[item.aging_category] || 0) + 1;
                return acc;
            }, {});
            
            // Bar chart
            const ctx1 = document.getElementById('main-chart').getContext('2d');
            mainChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: Object.keys(agingData),
                    datasets: [{
                        label: 'จำนวน Location',
                        data: Object.values(agingData),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'การกระจายอายุสินค้า'
                        }
                    }
                }
            });
            
            // Pie chart - same data
            const ctx2 = document.getElementById('pie-chart').getContext('2d');
            pieChart = new Chart(ctx2, {
                type: 'pie',
                data: {
                    labels: Object.keys(agingData),
                    datasets: [{
                        data: Object.values(agingData),
                        backgroundColor: [
                            '#FF6384',
                            '#FF9F40',
                            '#FFCD56',
                            '#4BC0C0',
                            '#36A2EB',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'สัดส่วนอายุสินค้า'
                        }
                    }
                }
            });
        }
        
        function createSpaceUtilizationCharts(data) {
            // Bar chart
            const ctx1 = document.getElementById('main-chart').getContext('2d');
            mainChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.data.map(item => item.zone),
                    datasets: [
                        {
                            label: 'มีสินค้า',
                            data: data.data.map(item => parseInt(item.occupied_locations || 0)),
                            backgroundColor: 'rgba(75, 192, 192, 0.8)'
                        },
                        {
                            label: 'ว่าง',
                            data: data.data.map(item => parseInt(item.empty_locations || 0)),
                            backgroundColor: 'rgba(255, 206, 86, 0.8)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'การใช้งาน Location แต่ละ Zone'
                        }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true }
                    }
                }
            });
            
            // Pie chart
            const totalOccupied = data.data.reduce((sum, item) => sum + parseInt(item.occupied_locations || 0), 0);
            const totalEmpty = data.data.reduce((sum, item) => sum + parseInt(item.empty_locations || 0), 0);
            
            const ctx2 = document.getElementById('pie-chart').getContext('2d');
            pieChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['มีสินค้า', 'ว่าง'],
                    datasets: [{
                        data: [totalOccupied, totalEmpty],
                        backgroundColor: ['#4BC0C0', '#FFCD56']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'สัดส่วนการใช้งานรวม'
                        }
                    }
                }
            });
        }
        
        function createGenericCharts(data) {
            // Simple bar chart for first numeric column
            if(data.data.length === 0) return;
            
            const firstRow = data.data[0];
            const numericColumns = Object.keys(firstRow).filter(key => {
                const value = firstRow[key];
                return !isNaN(value) && value !== null && value !== '';
            });
            
            if(numericColumns.length === 0) return;
            
            const ctx1 = document.getElementById('main-chart').getContext('2d');
            mainChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: data.data.slice(0, 20).map((item, index) => `#${index + 1}`),
                    datasets: [{
                        label: numericColumns[0],
                        data: data.data.slice(0, 20).map(item => parseFloat(item[numericColumns[0]] || 0)),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'แนวโน้มข้อมูล'
                        }
                    }
                }
            });
        }
        
        function switchView(viewType) {
            const tableView = document.getElementById('table-view');
            const chartView = document.getElementById('chart-view');
            const buttons = document.querySelectorAll('[data-view]');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-view="${viewType}"]`).classList.add('active');
            
            if(viewType === 'table') {
                tableView.style.display = 'block';
                chartView.style.display = 'none';
            } else {
                tableView.style.display = 'none';
                chartView.style.display = 'block';
            }
        }
        
        function exportData(format) {
            const dateRange = document.getElementById('date-range').value;
            const zoneFilter = document.getElementById('zone-filter').value;
            
            const formData = new FormData();
            formData.append('report_type', reportType);
            formData.append('format', format);
            formData.append('date_range', dateRange);
            formData.append('zone_filter', zoneFilter);
            
            showLoading();
            
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
                hideLoading();
            });
        }
        
        function printReport() {
            window.print();
        }
        
        function showNotification(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            
            const notification = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <i class="fas fa-${type === 'success' ? 'check' : 'times'}-circle"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', notification);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                if(alerts.length > 0) {
                    alerts[alerts.length - 1].remove();
                }
            }, 5000);
        }
        
        // Auto refresh every 5 minutes
        setInterval(loadReportData, 300000);
    </script>
</body>
</html>