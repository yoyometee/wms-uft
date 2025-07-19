<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>">
            <i class="fas fa-warehouse"></i> <?php echo APP_NAME; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>">
                        <i class="fas fa-home"></i> หน้าหลัก
                    </a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="operationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs"></i> การดำเนินการ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/receive/">
                            <i class="fas fa-truck-loading"></i> รับสินค้า
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/picking/">
                            <i class="fas fa-clipboard-list"></i> จัดเตรียมสินค้า
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/movement/">
                            <i class="fas fa-exchange-alt"></i> ย้ายสินค้า
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/inventory/">
                            <i class="fas fa-adjust"></i> ปรับสต็อก
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/conversion/">
                            <i class="fas fa-recycle"></i> การแปลง
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/premium/">
                            <i class="fas fa-star"></i> Premium
                        </a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> รายงาน
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/stock.php">
                            <i class="fas fa-boxes"></i> รายงานสต็อก
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/transactions.php">
                            <i class="fas fa-list-alt"></i> รายงานการเคลื่อนไหว
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/locations.php">
                            <i class="fas fa-map-marker-alt"></i> รายงานตำแหน่ง
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/expiry.php">
                            <i class="fas fa-calendar-times"></i> รายงานวันหมดอายุ
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/">
                            <i class="fas fa-chart-line"></i> รายงานขั้นสูง
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/dashboard/executive.php">
                            <i class="fas fa-tachometer-alt"></i> Executive Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/analytics/">
                            <i class="fas fa-brain"></i> Advanced Analytics
                        </a></li>
                    </ul>
                </li>
                
                <?php if(isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'office'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="intelligentDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-brain"></i> ระบบอัจฉริยะ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/ai/pick-path-optimizer.php">
                            <i class="fas fa-robot"></i> AI Pick Path
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reorder/">
                            <i class="fas fa-shopping-cart"></i> การสั่งซื้ออัตโนมัติ
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/cycle-count/">
                            <i class="fas fa-calculator"></i> Cycle Count
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i> จัดการระบบ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/admin/users.php">
                            <i class="fas fa-users"></i> จัดการผู้ใช้
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/admin/products.php">
                            <i class="fas fa-box"></i> จัดการสินค้า
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/admin/locations.php">
                            <i class="fas fa-map"></i> จัดการตำแหน่ง
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/admin/settings.php">
                            <i class="fas fa-wrench"></i> การตั้งค่า
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/admin/backup.php">
                            <i class="fas fa-database"></i> สำรองข้อมูล
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger" id="notification-count">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" id="notifications-list">
                        <li><h6 class="dropdown-header">การแจ้งเตือน</h6></li>
                        <li><a class="dropdown-item" href="#">ไม่มีการแจ้งเตือน</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['user_name'] ?? 'ผู้ใช้'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile.php">
                            <i class="fas fa-user-edit"></i> แก้ไขข้อมูล
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/change-password.php">
                            <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Quick Search Modal -->
<div class="modal fade" id="quickSearchModal" tabindex="-1" aria-labelledby="quickSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickSearchModalLabel">
                    <i class="fas fa-search"></i> ค้นหาข้อมูล
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="quickSearch" class="form-control" placeholder="ค้นหา SKU, Pallet ID, Location...">
                    <button class="btn btn-primary" type="button" id="performQuickSearch">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>
                <div id="quickSearchResults"></div>
            </div>
        </div>
    </div>
</div>

<!-- Barcode Scanner Modal -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="barcodeScannerModalLabel">
                    <i class="fas fa-barcode"></i> สแกนบาร์โค้ด
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="barcodeInput" class="form-label">บาร์โค้ด/QR Code:</label>
                    <input type="text" id="barcodeInput" class="form-control" placeholder="สแกนหรือพิมพ์บาร์โค้ด">
                </div>
                <button type="button" class="btn btn-primary" id="processBarcodeBtn">
                    <i class="fas fa-check"></i> ดำเนินการ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<div class="fab-container">
    <button class="fab fab-main" data-bs-toggle="modal" data-bs-target="#quickSearchModal">
        <i class="fas fa-search"></i>
    </button>
    
    <?php if(BARCODE_ENABLED): ?>
    <button class="fab fab-secondary" data-bs-toggle="modal" data-bs-target="#barcodeScannerModal">
        <i class="fas fa-barcode"></i>
    </button>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick search functionality
    document.getElementById('performQuickSearch').addEventListener('click', function() {
        const searchTerm = document.getElementById('quickSearch').value.trim();
        if(searchTerm) {
            performQuickSearch(searchTerm);
        }
    });
    
    document.getElementById('quickSearch').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            document.getElementById('performQuickSearch').click();
        }
    });
    
    // Barcode scanner functionality
    document.getElementById('processBarcodeBtn').addEventListener('click', function() {
        const barcode = document.getElementById('barcodeInput').value.trim();
        if(barcode) {
            processBarcode(barcode);
        }
    });
    
    document.getElementById('barcodeInput').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            document.getElementById('processBarcodeBtn').click();
        }
    });
    
    // Auto-focus on barcode input when modal opens
    document.getElementById('barcodeScannerModal').addEventListener('shown.bs.modal', function() {
        document.getElementById('barcodeInput').focus();
    });
    
    // Load notifications
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});

function performQuickSearch(searchTerm) {
    const resultsDiv = document.getElementById('quickSearchResults');
    resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    
    fetch('<?php echo APP_URL; ?>/api/search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({search: searchTerm})
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            displaySearchResults(data.results);
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-warning">ไม่พบข้อมูล</div>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการค้นหา</div>';
    });
}

function displaySearchResults(results) {
    const resultsDiv = document.getElementById('quickSearchResults');
    
    if(results.length === 0) {
        resultsDiv.innerHTML = '<div class="alert alert-info">ไม่พบข้อมูล</div>';
        return;
    }
    
    let html = '<div class="list-group">';
    
    results.forEach(result => {
        html += `<div class="list-group-item">
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">${result.title}</h6>
                <small class="text-muted">${result.type}</small>
            </div>
            <p class="mb-1">${result.description}</p>
            <small class="text-muted">${result.extra}</small>
        </div>`;
    });
    
    html += '</div>';
    resultsDiv.innerHTML = html;
}

function processBarcode(barcode) {
    // You can implement barcode processing logic here
    // For now, just perform a search
    document.getElementById('quickSearch').value = barcode;
    performQuickSearch(barcode);
    
    // Switch to search modal
    bootstrap.Modal.getInstance(document.getElementById('barcodeScannerModal')).hide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('quickSearchModal')).show();
}

function loadNotifications() {
    fetch('<?php echo APP_URL; ?>/api/notifications.php')
    .then(response => response.json())
    .then(data => {
        updateNotificationCount(data.count);
        updateNotificationList(data.notifications);
    })
    .catch(error => {
        console.error('Failed to load notifications:', error);
    });
}

function updateNotificationCount(count) {
    const badge = document.getElementById('notification-count');
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline' : 'none';
}

function updateNotificationList(notifications) {
    const list = document.getElementById('notifications-list');
    
    if(notifications.length === 0) {
        list.innerHTML = '<li><h6 class="dropdown-header">การแจ้งเตือน</h6></li><li><a class="dropdown-item" href="#">ไม่มีการแจ้งเตือน</a></li>';
        return;
    }
    
    let html = '<li><h6 class="dropdown-header">การแจ้งเตือน</h6></li>';
    
    notifications.forEach(notification => {
        html += `<li><a class="dropdown-item" href="${notification.url}">
            <i class="${notification.icon}"></i> ${notification.message}
            <br><small class="text-muted">${notification.time}</small>
        </a></li>`;
    });
    
    list.innerHTML = html;
}
</script>