<?php
session_start();

// Include configuration and classes
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

// Check login
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Create push subscriptions table if not exists
    $create_table_query = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh_key TEXT,
        auth_key TEXT,
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        UNIQUE KEY unique_user_endpoint (user_id, endpoint(255)),
        INDEX idx_user_active (user_id, is_active)
    )";
    
    $db->exec($create_table_query);
    
    // Get subscription data from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['endpoint'])) {
        throw new Exception('Invalid subscription data');
    }
    
    $endpoint = $input['endpoint'];
    $p256dh_key = $input['keys']['p256dh'] ?? '';
    $auth_key = $input['keys']['auth'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Insert or update subscription
    $query = "INSERT INTO push_subscriptions 
              (user_id, endpoint, p256dh_key, auth_key, user_agent, updated_at) 
              VALUES (:user_id, :endpoint, :p256dh_key, :auth_key, :user_agent, NOW())
              ON DUPLICATE KEY UPDATE 
              p256dh_key = VALUES(p256dh_key),
              auth_key = VALUES(auth_key),
              user_agent = VALUES(user_agent),
              updated_at = NOW(),
              is_active = TRUE";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':endpoint', $endpoint);
    $stmt->bindParam(':p256dh_key', $p256dh_key);
    $stmt->bindParam(':auth_key', $auth_key);
    $stmt->bindParam(':user_agent', $user_agent);
    $stmt->execute();
    
    // Log activity
    logActivity('PUSH_SUBSCRIBE', 'Push notification subscription registered', $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Push subscription saved successfully'
    ]);

} catch(Exception $e) {
    error_log("Push subscribe error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>