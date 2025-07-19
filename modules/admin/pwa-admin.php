<?php
session_start();

// Include configuration and classes
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

// Check login and admin permission
checkLogin();
checkPermission('admin');

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize classes
$user = new User($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

$error_message = '';
$success_message = '';

// Handle notification sending
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'send_notification') {
        try {
            $title = $_POST['notification_title'] ?? 'Austam WMS';
            $message = $_POST['notification_message'] ?? '';
            $target_users = $_POST['target_users'] ?? 'all';
            $target_role = $_POST['target_role'] ?? '';
            $url = $_POST['notification_url'] ?? '/wms-uft/';
            
            if(empty($message)) {
                throw new Exception('กรุณากรอกข้อความแจ้งเตือน');
            }
            
            // Prepare notification data
            $notification_data = [
                'title' => $title,
                'message' => $message,
                'target_users' => $target_users,
                'target_role' => $target_role,
                'url' => $url
            ];
            
            // Send to notification API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => APP_URL . '/api/send-notification.php',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($notification_data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Cookie: ' . $_SERVER['HTTP_COOKIE']
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if($httpCode === 200) {
                $result = json_decode($response, true);
                if($result['success']) {
                    $success_message = "ส่งการแจ้งเตือนสำเร็จ - " . $result['stats']['successful_sends'] . " ผู้ใช้";
                } else {
                    throw new Exception($result['error']);
                }
            } else {
                throw new Exception('ไม่สามารถส่งการแจ้งเตือนได้');
            }
            
        } catch(Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get PWA statistics
function getPWAStats($db) {
    $stats = [];
    
    // Total subscriptions
    $query = "SELECT COUNT(*) as total_subscriptions FROM push_subscriptions WHERE is_active = TRUE";
    $stats['total_subscriptions'] = $db->query($query)->fetchColumn();
    
    // Subscriptions by role
    $query = "SELECT u.role, COUNT(*) as count 
              FROM push_subscriptions ps 
              JOIN users u ON ps.user_id = u.user_id 
              WHERE ps.is_active = TRUE 
              GROUP BY u.role";
    $stmt = $db->query($query);
    $role_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['by_role'] = [];
    foreach($role_stats as $row) {
        $stats['by_role'][$row['role']] = $row['count'];
    }
    
    // Recent subscriptions (last 7 days)
    $query = "SELECT COUNT(*) as recent_subscriptions 
              FROM push_subscriptions 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_active = TRUE";
    $stats['recent_subscriptions'] = $db->query($query)->fetchColumn();
    
    // User agents distribution
    $query = "SELECT 
                CASE 
                    WHEN user_agent LIKE '%Mobile%' THEN 'Mobile'
                    WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
              FROM push_subscriptions 
              WHERE is_active = TRUE 
              GROUP BY device_type";
    $stmt = $db->query($query);
    $device_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['by_device'] = [];
    foreach($device_stats as $row) {
        $stats['by_device'][$row['device_type']] = $row['count'];
    }
    
    return $stats;
}

$pwa_stats = getPWAStats($db);

// Get all users for targeting
$users_query = "SELECT user_id, ชื่อ_สกุล, role FROM users WHERE is_active = 1 ORDER BY ชื่อ_สกุล";
$all_users = $db->query($users_query)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'จัดการ PWA และการแจ้งเตือน';
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
        .stat-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .notification-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }
        .chart-container {
            position: relative;
            height: 300px;
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
                                <h1><i class="fas fa-mobile-alt"></i> จัดการ PWA และการแจ้งเตือน</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                        <li class="breadcrumb-item"><a href="./" class="text-white">ผู้ดูแลระบบ</a></li>
                                        <li class="breadcrumb-item active text-white">จัดการ PWA</li>
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

        <!-- PWA Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #007bff;">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="fas fa-bell fa-2x"></i>
                        </div>
                        <div class="stat-number text-primary"><?php echo number_format($pwa_stats['total_subscriptions']); ?></div>
                        <small class="text-muted">การสมัครรับการแจ้งเตือน</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo number_format($pwa_stats['recent_subscriptions']); ?></div>
                        <small class="text-muted">การสมัครใหม่ (7 วัน)</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-mobile-alt fa-2x"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo number_format($pwa_stats['by_device']['Mobile'] ?? 0); ?></div>
                        <small class="text-muted">อุปกรณ์มือถือ</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #17a2b8;">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="fas fa-desktop fa-2x"></i>
                        </div>
                        <div class="stat-number text-info"><?php echo number_format($pwa_stats['by_device']['Desktop'] ?? 0); ?></div>
                        <small class="text-muted">อุปกรณ์คอมพิวเตอร์</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Forms Row -->
        <div class="row">
            <!-- Send Notification Form -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-paper-plane"></i> ส่งการแจ้งเตือน</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="notification-form">
                            <input type="hidden" name="action" value="send_notification">
                            
                            <div class="mb-3">
                                <label class="form-label">หัวข้อการแจ้งเตือน</label>
                                <input type="text" name="notification_title" class="form-control" value="Austam WMS" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ข้อความ <span class="text-danger">*</span></label>
                                <textarea name="notification_message" class="form-control" rows="3" placeholder="กรอกข้อความที่ต้องการแจ้งเตือน" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ลิงก์ปลายทาง</label>
                                <input type="url" name="notification_url" class="form-control" value="/wms-uft/" placeholder="https://example.com">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ส่งถึง</label>
                                <select name="target_users" id="target-users" class="form-select" onchange="toggleRoleSelect()">
                                    <option value="all">ผู้ใช้ทั้งหมด</option>
                                    <option value="role">ตามบทบาท</option>
                                    <option value="specific">ผู้ใช้เฉพาะ</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="role-select" style="display: none;">
                                <label class="form-label">เลือกบทบาท</label>
                                <select name="target_role" class="form-select">
                                    <option value="">เลือกบทบาท</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                    <option value="office">พนักงานออฟฟิศ</option>
                                    <option value="worker">พนักงานคลัง</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="users-select" style="display: none;">
                                <label class="form-label">เลือกผู้ใช้</label>
                                <select name="target_user_ids[]" class="form-select" multiple>
                                    <?php foreach($all_users as $user_item): ?>
                                    <option value="<?php echo $user_item['user_id']; ?>">
                                        <?php echo htmlspecialchars($user_item['ชื่อ_สกุล']); ?> (<?php echo $user_item['role']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> ส่งการแจ้งเตือน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics Charts -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> สถิติการใช้งาน</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PWA Settings -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> การตั้งค่า PWA</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>การแจ้งเตือนแบบผลักดัน (Push Notifications)</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enablePushNotifications" checked>
                                    <label class="form-check-label" for="enablePushNotifications">
                                        เปิดใช้งานการแจ้งเตือนแบบผลักดัน
                                    </label>
                                </div>
                                <small class="text-muted">อนุญาตให้ระบบส่งการแจ้งเตือนไปยังอุปกรณ์ของผู้ใช้</small>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>การทำงานแบบออฟไลน์</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableOfflineMode" checked>
                                    <label class="form-check-label" for="enableOfflineMode">
                                        เปิดใช้งานโหมดออฟไลน์
                                    </label>
                                </div>
                                <small class="text-muted">อนุญาตให้ผู้ใช้สามารถใช้งานระบบแบบออฟไลน์ได้</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>การติดตั้งแอป</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableAppInstall" checked>
                                    <label class="form-check-label" for="enableAppInstall">
                                        แสดงปุ่มติดตั้งแอป
                                    </label>
                                </div>
                                <small class="text-muted">แสดงปุ่มให้ผู้ใช้สามารถติดตั้งแอปบนอุปกรณ์ได้</small>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>การซิงค์ข้อมูลแบบพื้นหลัง</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableBackgroundSync" checked>
                                    <label class="form-check-label" for="enableBackgroundSync">
                                        เปิดใช้งานการซิงค์แบบพื้นหลัง
                                    </label>
                                </div>
                                <small class="text-muted">ซิงค์ข้อมูลอัตโนมัติเมื่อมีการเชื่อมต่ออินเทอร์เน็ต</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-success" onclick="savePWASettings()">
                                <i class="fas fa-save"></i> บันทึกการตั้งค่า
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="resetPWACache()">
                                <i class="fas fa-trash"></i> ล้าง Cache PWA
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="testNotification()">
                                <i class="fas fa-bell"></i> ทดสอบการแจ้งเตือน
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> รายการการสมัครรับการแจ้งเตือน</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="subscriptions-table">
                                <thead>
                                    <tr>
                                        <th>ผู้ใช้</th>
                                        <th>บทบาท</th>
                                        <th>อุปกรณ์</th>
                                        <th>วันที่สมัคร</th>
                                        <th>สถานะ</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT ps.*, u.ชื่อ_สกุล, u.role 
                                              FROM push_subscriptions ps 
                                              JOIN users u ON ps.user_id = u.user_id 
                                              ORDER BY ps.created_at DESC 
                                              LIMIT 50";
                                    $subscriptions = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach($subscriptions as $sub): 
                                        $device_type = 'Desktop';
                                        if(strpos($sub['user_agent'], 'Mobile') !== false) {
                                            $device_type = 'Mobile';
                                        } elseif(strpos($sub['user_agent'], 'Tablet') !== false) {
                                            $device_type = 'Tablet';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['ชื่อ_สกุล']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $sub['role'] === 'admin' ? 'danger' : ($sub['role'] === 'office' ? 'primary' : 'secondary'); ?>">
                                                <?php echo $sub['role']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-<?php echo $device_type === 'Mobile' ? 'mobile-alt' : ($device_type === 'Tablet' ? 'tablet-alt' : 'desktop'); ?>"></i>
                                            <?php echo $device_type; ?>
                                        </td>
                                        <td><?php echo formatDate($sub['created_at']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $sub['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $sub['is_active'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($sub['is_active']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    onclick="toggleSubscription(<?php echo $sub['id']; ?>, false)">
                                                <i class="fas fa-pause"></i> หยุด
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="toggleSubscription(<?php echo $sub['id']; ?>, true)">
                                                <i class="fas fa-play"></i> เปิด
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
        // Toggle role/user select based on target
        function toggleRoleSelect() {
            const targetUsers = document.getElementById('target-users').value;
            const roleSelect = document.getElementById('role-select');
            const usersSelect = document.getElementById('users-select');
            
            if(targetUsers === 'role') {
                roleSelect.style.display = 'block';
                usersSelect.style.display = 'none';
            } else if(targetUsers === 'specific') {
                roleSelect.style.display = 'none';
                usersSelect.style.display = 'block';
            } else {
                roleSelect.style.display = 'none';
                usersSelect.style.display = 'none';
            }
        }
        
        // Create role distribution chart
        const roleData = <?php echo json_encode($pwa_stats['by_role']); ?>;
        const ctx = document.getElementById('roleChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(roleData),
                datasets: [{
                    data: Object.values(roleData),
                    backgroundColor: [
                        '#dc3545',
                        '#007bff', 
                        '#6c757d'
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
                    },
                    title: {
                        display: true,
                        text: 'การกระจายตามบทบาท'
                    }
                }
            }
        });
        
        // Save PWA settings
        function savePWASettings() {
            const settings = {
                pushNotifications: document.getElementById('enablePushNotifications').checked,
                offlineMode: document.getElementById('enableOfflineMode').checked,
                appInstall: document.getElementById('enableAppInstall').checked,
                backgroundSync: document.getElementById('enableBackgroundSync').checked
            };
            
            // In production, save to server
            localStorage.setItem('pwa_settings', JSON.stringify(settings));
            alert('บันทึกการตั้งค่าเรียบร้อย');
        }
        
        // Reset PWA cache
        function resetPWACache() {
            if(confirm('ต้องการล้าง Cache PWA หรือไม่?')) {
                if('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistration().then(registration => {
                        if(registration) {
                            const messageChannel = new MessageChannel();
                            messageChannel.port1.onmessage = function(event) {
                                if(event.data.success) {
                                    alert('ล้าง Cache เรียบร้อย');
                                    location.reload();
                                }
                            };
                            
                            registration.active.postMessage({
                                type: 'CLEAR_CACHE'
                            }, [messageChannel.port2]);
                        }
                    });
                }
            }
        }
        
        // Test notification
        function testNotification() {
            if('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if(permission === 'granted') {
                        new Notification('Austam WMS', {
                            body: 'ทดสอบการแจ้งเตือน PWA',
                            icon: '/wms-uft/assets/images/icons/icon-192x192.png',
                            badge: '/wms-uft/assets/images/icons/icon-72x72.png'
                        });
                    } else {
                        alert('กรุณาอนุญาตการแจ้งเตือนในเบราว์เซอร์');
                    }
                });
            } else {
                alert('เบราว์เซอร์ไม่รองรับการแจ้งเตือน');
            }
        }
        
        // Toggle subscription status
        function toggleSubscription(subscriptionId, isActive) {
            // In production, send AJAX request to toggle subscription
            const action = isActive ? 'เปิดใช้งาน' : 'หยุดใช้งาน';
            
            if(confirm(`ต้องการ${action}การสมัครรับการแจ้งเตือนนี้หรือไม่?`)) {
                // Implement AJAX call here
                alert(`${action}เรียบร้อย`);
                location.reload();
            }
        }
        
        // Load saved settings
        document.addEventListener('DOMContentLoaded', function() {
            const savedSettings = localStorage.getItem('pwa_settings');
            if(savedSettings) {
                const settings = JSON.parse(savedSettings);
                document.getElementById('enablePushNotifications').checked = settings.pushNotifications;
                document.getElementById('enableOfflineMode').checked = settings.offlineMode;
                document.getElementById('enableAppInstall').checked = settings.appInstall;
                document.getElementById('enableBackgroundSync').checked = settings.backgroundSync;
            }
        });
    </script>
</body>
</html>