<?php
// Common functions for WMS system

function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function checkPermission($role) {
    if(!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        if($_SESSION['user_role'] !== 'admin') {
            header('Location: ' . APP_URL . '/index.php?error=permission_denied');
            exit;
        }
    }
}

function formatDate($timestamp, $format = 'd/m/Y H:i:s') {
    if(empty($timestamp)) return '-';
    
    if(is_numeric($timestamp)) {
        return date($format, $timestamp);
    }
    
    return date($format, strtotime($timestamp));
}

function formatWeight($weight) {
    return number_format($weight, 2) . ' กก.';
}

function formatNumber($number) {
    return number_format($number);
}

function formatCurrency($amount) {
    return '฿' . number_format($amount, 2);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function getTransactionTypeColor($transaction_type) {
    $colors = [
        // Thai transaction types
        'เบิก' => 'danger',
        'รับ' => 'success',
        'ย้าย' => 'warning',
        'ปรับ' => 'info',
        'แปลง' => 'primary',
        'premium' => 'secondary',
        'return' => 'dark',
        // English transaction types
        'รับสินค้า' => 'success',
        'จัดเตรียมสินค้า' => 'primary',
        'ย้ายสินค้า' => 'info',
        'ปรับสต็อก' => 'warning',
        'การแปลง' => 'primary',
        'ออนไลน์' => 'info',
        'รีแพ็ค' => 'warning',
        // Additional types
        'picking' => 'primary',
        'receiving' => 'success',
        'moving' => 'warning',
        'adjustment' => 'info',
        'conversion' => 'primary'
    ];
    
    return $colors[strtolower($transaction_type)] ?? 'secondary';
}

function getTransactionIcon($transaction_type) {
    $icons = [
        'เบิก' => 'fas fa-arrow-down',
        'รับ' => 'fas fa-arrow-up',
        'ย้าย' => 'fas fa-exchange-alt',
        'ปรับ' => 'fas fa-edit',
        'แปลง' => 'fas fa-recycle',
        'premium' => 'fas fa-star',
        'return' => 'fas fa-undo'
    ];
    
    return $icons[strtolower($transaction_type)] ?? 'fas fa-box';
}

function calculatePercentageChange($current, $previous) {
    if($previous == 0) return 0;
    return (($current - $previous) / $previous) * 100;
}

function formatPercentage($percentage, $decimals = 1) {
    return number_format($percentage, $decimals) . '%';
}

function getStatusBadgeClass($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'completed' => 'success',
        'cancelled' => 'danger',
        'in_progress' => 'info'
    ];
    
    return $classes[strtolower($status)] ?? 'secondary';
}

function generateRandomColor() {
    return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if($time < 60) return 'เมื่อสักครู่';
    if($time < 3600) return floor($time/60) . ' นาทีที่แล้ว';
    if($time < 86400) return floor($time/3600) . ' ชั่วโมงที่แล้ว';
    if($time < 2592000) return floor($time/86400) . ' วันที่แล้ว';
    if($time < 31536000) return floor($time/2592000) . ' เดือนที่แล้ว';
    
    return floor($time/31536000) . ' ปีที่แล้ว';
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function createAlert($type, $message) {
    $alertClass = '';
    switch($type) {
        case 'success':
            $alertClass = 'alert-success';
            break;
        case 'error':
            $alertClass = 'alert-danger';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            break;
        case 'info':
            $alertClass = 'alert-info';
            break;
        default:
            $alertClass = 'alert-info';
    }
    
    return '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

function logActivity($action, $description, $user_id = null) {
    if(!$user_id && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $log_entry = date('Y-m-d H:i:s') . " - User: $user_id, Action: $action, Description: $description\n";
    file_put_contents('logs/activity.log', $log_entry, FILE_APPEND);
}

function getStockStatus($current_stock, $min_stock, $max_stock) {
    if($current_stock <= $min_stock) {
        return ['status' => 'low', 'class' => 'text-danger', 'text' => 'ต่ำกว่าขั้นต่ำ'];
    } elseif($current_stock >= $max_stock) {
        return ['status' => 'high', 'class' => 'text-warning', 'text' => 'สูงกว่าขั้นสูง'];
    } else {
        return ['status' => 'normal', 'class' => 'text-success', 'text' => 'ปกติ'];
    }
}

function getZoneColor($zone) {
    $colors = [
        'Selective Rack' => 'primary',
        'PF-Zone' => 'success',
        'PF-Premium' => 'warning',
        'Packaging' => 'info',
        'Damaged' => 'danger'
    ];
    
    foreach($colors as $zoneType => $color) {
        if(strpos($zone, $zoneType) !== false) {
            return $color;
        }
    }
    
    return 'secondary';
}


function calculateExpiryStatus($expiry_date) {
    if(empty($expiry_date) || $expiry_date <= 0) {
        return ['status' => 'unknown', 'class' => 'text-muted', 'text' => 'ไม่ระบุ'];
    }
    
    $days_diff = ceil(($expiry_date - time()) / (60 * 60 * 24));
    
    if($days_diff < 0) {
        return ['status' => 'expired', 'class' => 'text-danger', 'text' => 'หมดอายุแล้ว'];
    } elseif($days_diff <= 7) {
        return ['status' => 'expiring_soon', 'class' => 'text-danger', 'text' => 'หมดอายุใน ' . $days_diff . ' วัน'];
    } elseif($days_diff <= 30) {
        return ['status' => 'expiring_warning', 'class' => 'text-warning', 'text' => 'หมดอายุใน ' . $days_diff . ' วัน'];
    } else {
        return ['status' => 'good', 'class' => 'text-success', 'text' => 'ยังไม่หมดอายุ'];
    }
}

function generateBarcode($text, $type = 'qr') {
    if($type === 'qr') {
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($text);
    } else {
        return "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($text) . "&code=Code128&dpi=96";
    }
}

function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    if(!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'ไม่พบไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด'];
    }
    
    $file_size = $file['size'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if(!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'error' => 'ประเภทไฟล์ไม่รองรับ'];
    }
    
    if($file_size > $max_size) {
        return ['success' => false, 'error' => 'ขนาดไฟล์ใหญ่เกินไป'];
    }
    
    $new_file_name = generateRandomString(10) . '_' . time() . '.' . $file_ext;
    $upload_path = UPLOAD_PATH . $new_file_name;
    
    if(!file_exists(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    if(move_uploaded_file($file_tmp, $upload_path)) {
        return ['success' => true, 'file_name' => $new_file_name, 'file_path' => $upload_path];
    } else {
        return ['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์ได้'];
    }
}

function deleteFile($file_path) {
    if(file_exists($file_path)) {
        return unlink($file_path);
    }
    return true;
}

function exportToCSV($data, $filename, $headers = []) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if(!empty($headers)) {
        fputcsv($output, $headers);
    }
    
    foreach($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportToExcel($data, $filename, $headers = []) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<table border="1">';
    
    if(!empty($headers)) {
        echo '<tr>';
        foreach($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
    }
    
    foreach($data as $row) {
        echo '<tr>';
        foreach($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
}

function createPagination($current_page, $total_pages, $base_url) {
    $pagination = '<nav aria-label="Page navigation">';
    $pagination .= '<ul class="pagination justify-content-center">';
    
    // Previous button
    if($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . ($current_page - 1) . '">ก่อนหน้า</a></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    for($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $pagination .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next button
    if($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . ($current_page + 1) . '">ถัดไป</a></li>';
    }
    
    $pagination .= '</ul>';
    $pagination .= '</nav>';
    
    return $pagination;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function convertThaiDateToMysql($thai_date) {
    if(empty($thai_date)) return null;
    
    $parts = explode('/', $thai_date);
    if(count($parts) === 3) {
        $day = $parts[0];
        $month = $parts[1];
        $year = $parts[2];
        
        // Convert Thai year to Christian year
        if($year > 2400) {
            $year = $year - 543;
        }
        
        return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    }
    
    return null;
}

function convertMysqlDateToThai($mysql_date) {
    if(empty($mysql_date)) return '-';
    
    $timestamp = strtotime($mysql_date);
    $day = date('d', $timestamp);
    $month = date('m', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    return $day . '/' . $month . '/' . $year;
}

function getThaiMonthName($month) {
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    return $months[$month] ?? '';
}

function debugLog($message, $data = null) {
    $log_entry = date('Y-m-d H:i:s') . " - DEBUG: $message";
    
    if($data !== null) {
        $log_entry .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    $log_entry .= "\n";
    file_put_contents('logs/debug.log', $log_entry, FILE_APPEND);
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect($url, $permanent = false) {
    if($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header('Location: ' . $url);
    exit;
}

function getClientIP() {
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unit_index = 0;
    
    while($size >= 1024 && $unit_index < count($units) - 1) {
        $size /= 1024;
        $unit_index++;
    }
    
    return round($size, 2) . ' ' . $units[$unit_index];
}

?>