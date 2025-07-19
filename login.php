<?php
session_start();

// Include configuration and classes
require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = sanitizeInput($_POST['user_id']);
    $password = sanitizeInput($_POST['password']);
    
    if(empty($user_id) || empty($password)) {
        $error_message = 'กรุณากรอกรหัสผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $database = new Database();
            $db = $database->connect();
            $user = new User($db);
            
            $login_result = $user->login($user_id, $password);
            
            if($login_result) {
                logActivity('LOGIN', 'User logged in successfully', $user_id);
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'รหัสผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                logActivity('LOGIN_FAILED', 'Invalid login attempt', $user_id);
            }
        } catch(Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$page_title = 'เข้าสู่ระบบ';
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
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .demo-accounts {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .demo-accounts h6 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .demo-accounts small {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-warehouse"></i>
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>ระบบจัดการคลังสินค้า</p>
        </div>
        
        <?php if($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-floating">
                <input type="text" class="form-control" id="user_id" name="user_id" placeholder="รหัสผู้ใช้" required>
                <label for="user_id">
                    <i class="fas fa-user"></i> รหัสผู้ใช้
                </label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" placeholder="รหัสผ่าน" required>
                <label for="password">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
            </div>
            
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">
                    จดจำการเข้าสู่ระบบ
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>
        
        <div class="demo-accounts">
            <h6><i class="fas fa-info-circle"></i> บัญชีทดสอบ:</h6>
            <small>
                <strong>Admin:</strong> ADMIN001 / password<br>
                <strong>พนักงาน:</strong> WH001 / password<br>
                <strong>สำนักงาน:</strong> OFFICE001 / password
            </small>
        </div>
    </div>
    
    <div class="footer-text">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Version <?php echo APP_VERSION; ?></p>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on first input
            document.getElementById('user_id').focus();
            
            // Form validation
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                const userId = document.getElementById('user_id').value.trim();
                const password = document.getElementById('password').value.trim();
                
                if(!userId || !password) {
                    e.preventDefault();
                    showAlert('error', 'กรุณากรอกรหัสผู้ใช้และรหัสผ่าน');
                    return;
                }
                
                // Show loading
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังเข้าสู่ระบบ...';
                submitBtn.disabled = true;
            });
            
            // Enter key on password field
            document.getElementById('password').addEventListener('keypress', function(e) {
                if(e.key === 'Enter') {
                    document.getElementById('loginForm').submit();
                }
            });
        });
        
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.login-container');
            const form = document.getElementById('loginForm');
            container.insertBefore(alertDiv, form);
            
            // Auto remove after 5 seconds
            setTimeout(function() {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>