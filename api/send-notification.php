<?php
session_start();

// Include configuration and classes
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

// Check admin permission
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Get notification data from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    $title = $input['title'] ?? 'Austam WMS';
    $message = $input['message'] ?? '';
    $target_users = $input['target_users'] ?? 'all'; // 'all', 'role', 'specific'
    $target_role = $input['target_role'] ?? '';
    $target_user_ids = $input['target_user_ids'] ?? [];
    $url = $input['url'] ?? '/wms-uft/';
    $icon = $input['icon'] ?? '/wms-uft/assets/images/icons/icon-192x192.png';
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Build user query based on target
    $user_condition = "WHERE ps.is_active = TRUE";
    $params = [];
    
    if ($target_users === 'role' && !empty($target_role)) {
        $user_condition .= " AND u.role = :role";
        $params[':role'] = $target_role;
    } elseif ($target_users === 'specific' && !empty($target_user_ids)) {
        $placeholders = str_repeat('?,', count($target_user_ids) - 1) . '?';
        $user_condition .= " AND u.user_id IN ($placeholders)";
        $params = array_values($target_user_ids);
    }
    
    // Get active subscriptions
    $query = "SELECT ps.endpoint, ps.p256dh_key, ps.auth_key, u.user_id, u.ชื่อ_สกุล
              FROM push_subscriptions ps
              JOIN users u ON ps.user_id = u.user_id
              $user_condition";
    
    if (!empty($params)) {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    } else {
        $stmt = $db->query($query);
    }
    
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subscriptions)) {
        throw new Exception('No active subscriptions found');
    }
    
    // Prepare notification payload
    $payload = json_encode([
        'title' => $title,
        'body' => $message,
        'icon' => $icon,
        'badge' => '/wms-uft/assets/images/icons/icon-72x72.png',
        'url' => $url,
        'timestamp' => time(),
        'tag' => 'austam-wms-' . time(),
        'requireInteraction' => true,
        'actions' => [
            [
                'action' => 'open',
                'title' => 'เปิดระบบ',
                'icon' => '/wms-uft/assets/images/icons/icon-72x72.png'
            ],
            [
                'action' => 'close',
                'title' => 'ปิด'
            ]
        ]
    ]);
    
    $successful_sends = 0;
    $failed_sends = 0;
    $errors = [];
    
    // VAPID keys (In production, store these securely)
    $vapid_public_key = 'BEl62iUYgUivxIkv69yViEuiBIa40HI9stpjgM2JYODNd_4LWGNXOl8hKg8B8LUyU1QgXK6L4YZz4j9H9w3Cp8';
    $vapid_private_key = 'your-vapid-private-key-here'; // Replace with actual private key
    $vapid_subject = 'mailto:admin@austamgood.com';
    
    // Send notifications to each subscription
    foreach ($subscriptions as $subscription) {
        try {
            $result = sendWebPushNotification(
                $subscription['endpoint'],
                $payload,
                $subscription['p256dh_key'],
                $subscription['auth_key'],
                $vapid_public_key,
                $vapid_private_key,
                $vapid_subject
            );
            
            if ($result['success']) {
                $successful_sends++;
            } else {
                $failed_sends++;
                $errors[] = "User {$subscription['user_id']}: {$result['error']}";
                
                // Disable subscription if permanently failed
                if ($result['disable_subscription']) {
                    $disable_query = "UPDATE push_subscriptions SET is_active = FALSE WHERE endpoint = :endpoint";
                    $disable_stmt = $db->prepare($disable_query);
                    $disable_stmt->bindParam(':endpoint', $subscription['endpoint']);
                    $disable_stmt->execute();
                }
            }
            
        } catch (Exception $e) {
            $failed_sends++;
            $errors[] = "User {$subscription['user_id']}: {$e->getMessage()}";
        }
    }
    
    // Log notification activity
    logActivity('PUSH_NOTIFICATION', "Sent notification to $successful_sends users: $message", $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => "Notification sent successfully",
        'stats' => [
            'total_subscriptions' => count($subscriptions),
            'successful_sends' => $successful_sends,
            'failed_sends' => $failed_sends,
            'errors' => $errors
        ]
    ]);

} catch(Exception $e) {
    error_log("Send notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Function to send web push notification
function sendWebPushNotification($endpoint, $payload, $userPublicKey, $userAuthToken, $vapidPublicKey, $vapidPrivateKey, $vapidSubject) {
    // This is a simplified implementation
    // In production, use a proper Web Push library like web-push-php
    
    try {
        // Parse endpoint URL
        $urlParts = parse_url($endpoint);
        
        if (!$urlParts || !isset($urlParts['host'])) {
            throw new Exception('Invalid endpoint URL');
        }
        
        // Simulate sending (replace with actual Web Push implementation)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'TTL: 86400' // 24 hours
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => "CURL Error: $error",
                'disable_subscription' => false
            ];
        }
        
        // Check response codes
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        } elseif ($httpCode === 410 || $httpCode === 404) {
            // Subscription is no longer valid
            return [
                'success' => false,
                'error' => "Subscription expired (HTTP $httpCode)",
                'disable_subscription' => true
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP Error: $httpCode - $response",
                'disable_subscription' => false
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'disable_subscription' => false
        ];
    }
}
?>