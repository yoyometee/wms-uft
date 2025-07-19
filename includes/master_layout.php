<?php
// File: includes/master_layout.php
function renderMasterLayout($content, $page_title = 'WMS System', $additional_css = '', $additional_js = '') {
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.4rem;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem;
            font-weight: 600;
        }
        .card-body {
            padding: 1.5rem;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .dashboard-card {
            transition: transform 0.3s ease;
            cursor: pointer;
            border-radius: 15px;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stats-card {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stats-card h2 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .bg-danger-gradient {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .menu-card {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-weight: bold;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .menu-card:hover {
            transform: scale(1.05);
            text-decoration: none;
            color: white;
        }
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .menu-card:hover::before {
            left: 100%;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #007bff !important;
            transform: translateY(-1px);
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #007bff;
            transform: translateX(5px);
        }
        .table {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.1);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .alert-info {
            background-color: #cce7f0;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 0;
        }
        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb-item.active {
            color: rgba(255,255,255,0.9);
            font-weight: 600;
        }
        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .stats-card h2 {
                font-size: 2rem;
            }
            .menu-card {
                height: 100px;
            }
            .card-body {
                padding: 1rem;
            }
        }
    </style>
    <?php echo $additional_css; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo getBaseUrl(); ?>">
                <i class="fas fa-warehouse"></i> <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>">
                            <i class="fas fa-home"></i> หน้าหลัก
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-boxes"></i> การดำเนินการ
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/receive/">
                                <i class="fas fa-truck-loading"></i> รับสินค้า</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/picking/">
                                <i class="fas fa-hand-paper"></i> จัดเตรียมสินค้า</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/movement/">
                                <i class="fas fa-exchange-alt"></i> ย้ายสินค้า</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/inventory/">
                                <i class="fas fa-clipboard-check"></i> ปรับสต็อก</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-bar"></i> รายงาน
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/reports/">
                                <i class="fas fa-file-alt"></i> รายงานทั้งหมด</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/reports/stock.php">
                                <i class="fas fa-boxes"></i> รายงานสต็อก</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/analytics/">
                                <i class="fas fa-chart-line"></i> Analytics</a></li>
                        </ul>
                    </li>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> จัดการ
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/admin/users.php">
                                <i class="fas fa-users"></i> จัดการผู้ใช้</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/admin/products.php">
                                <i class="fas fa-box"></i> จัดการสินค้า</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>modules/admin/locations.php">
                                <i class="fas fa-map-marker-alt"></i> จัดการตำแหน่ง</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> 
                            <?php echo isset($_SESSION['ชื่อ_สกุล']) ? $_SESSION['ชื่อ_สกุล'] : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'ผู้ใช้งาน'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>logout.php">
                                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <?php echo $content; ?>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Global JavaScript functions
        function showAlert(message, type = 'success') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertDiv);
            
            setTimeout(() => {
                $('.alert').fadeOut(() => $('.alert').remove());
            }, 5000);
        }
        
        // Initialize DataTables with Thai language
        function initDataTable(tableId) {
            if ($(`#${tableId}`).length) {
                $(`#${tableId}`).DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
                    },
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']]
                });
            }
        }
        
        // Initialize tooltips
        $(document).ready(function() {
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Auto-initialize DataTables
            $('.table').each(function() {
                if ($(this).attr('id')) {
                    initDataTable($(this).attr('id'));
                }
            });
            
            // Auto-hide alerts
            setTimeout(() => {
                $('.alert').fadeOut();
            }, 5000);
        });
        
        // Loading overlay functions
        function showLoading() {
            $('body').append('<div id="loading-overlay" class="position-fixed w-100 h-100 d-flex justify-content-center align-items-center" style="top:0;left:0;background:rgba(255,255,255,0.8);z-index:9999;"><div class="spinner-border text-primary" style="width:3rem;height:3rem;"><span class="visually-hidden">Loading...</span></div></div>');
        }
        
        function hideLoading() {
            $('#loading-overlay').remove();
        }
    </script>
    
    <?php echo $additional_js; ?>
</body>
</html>
<?php
}
?>