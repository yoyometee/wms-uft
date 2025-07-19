<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/custom.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--light-color);
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--light-color);
            border-bottom: 1px solid #dee2e6;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
            background-color: var(--light-color);
        }
        
        .badge {
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-footer {
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 15px 15px;
        }
        
        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }
        
        .loading-overlay.show {
            visibility: visible;
            opacity: 1;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
        }
        
        /* Custom Components */
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-online {
            background-color: var(--success-color);
            animation: pulse 2s infinite;
        }
        
        .status-offline {
            background-color: var(--danger-color);
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <meta name="theme-color" content="#007bff">
    <meta name="background-color" content="#ffffff">
    <meta name="display" content="standalone">
    <meta name="orientation" content="portrait-primary">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Austam WMS">
    <meta name="msapplication-TileColor" content="#007bff">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- PWA Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo APP_URL; ?>/assets/images/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="<?php echo APP_URL; ?>/assets/images/icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="<?php echo APP_URL; ?>/assets/images/icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo APP_URL; ?>/assets/images/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo APP_URL; ?>/assets/images/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo APP_URL; ?>/assets/images/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="<?php echo APP_URL; ?>/assets/images/icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="<?php echo APP_URL; ?>/assets/images/icons/icon-512x512.png">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-square70x70logo" content="<?php echo APP_URL; ?>/assets/images/icons/icon-72x72.png">
    <meta name="msapplication-square150x150logo" content="<?php echo APP_URL; ?>/assets/images/icons/icon-152x152.png">
    <meta name="msapplication-square310x310logo" content="<?php echo APP_URL; ?>/assets/images/icons/icon-384x384.png">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:; img-src 'self' https: data: blob:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?>">
    <meta property="og:description" content="ระบบบริหารจัดการคลังสินค้า สำหรับ บริษัท ออสแต้ม กู๊ด จำกัด">
    <meta property="og:image" content="<?php echo APP_URL; ?>/assets/images/icons/icon-512x512.png">
    <meta property="og:url" content="<?php echo APP_URL; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Austam Good WMS">
</head>
<body>
    <div id="loading-overlay" class="d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>