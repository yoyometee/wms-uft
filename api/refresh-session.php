<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

try {
    // Update login time to refresh session
    $_SESSION['login_time'] = time();
    
    // Log session refresh
    $log_entry = date('Y-m-d H:i:s') . " - User: {$_SESSION['user_id']}, Action: SESSION_REFRESH\n";
    file_put_contents('../logs/activity.log', $log_entry, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session refreshed successfully',
        'timestamp' => time(),
        'expires_at' => time() + SESSION_TIMEOUT
    ]);

} catch(Exception $e) {
    error_log("Session refresh error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการต่อเวลา session'
    ]);
}
?>