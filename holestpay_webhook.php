<?php
/**
 * HolestPay Direct Webhook Handler
 * Bypasses OpenCart routing system for reliable webhook processing
 * 
 * URL: https://yourdomain.com/holestpay_webhook.php
 */

// CRITICAL: Set headers immediately for webhook accessibility
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Debug mode
if (isset($_GET['debug'])) {
    echo json_encode(['status' => 'success', 'message' => 'HolestPay Direct Webhook is working!', 'method' => $_SERVER['REQUEST_METHOD']]);
    exit;
}

try {
    // Load OpenCart configuration
    $opencart_root = __DIR__ . "/../..";
    $config_file = $opencart_root . '/config.php';
    
    if (!file_exists($config_file)) {
        throw new Exception('OpenCart config.php not found');
    }
    
    // Include config to get database constants
    require_once($config_file);
    
    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Get topic from URL parameter
    $topic = isset($_GET['topic']) ? $_GET['topic'] : '';
    
    // Get webhook data
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
    
    // Log webhook access
    error_log('HolestPay Direct Webhook: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' Topic: ' . $topic);
    
    if (!$webhook_data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
    
    // Get HolestPay configuration from database
    $config_stmt = $pdo->prepare("SELECT `value` FROM " . DB_PREFIX . "setting WHERE `key` = ? AND store_id = 0");
    
    $config_stmt->execute(['payment_holestpay_merchant_site_uid']);
    $merchant_site_uid = $config_stmt->fetchColumn();
    
    $config_stmt->execute(['payment_holestpay_secret_key']);
    $secret_key = $config_stmt->fetchColumn();
    
    if (!$merchant_site_uid || !$secret_key) {
        error_log('HolestPay Direct Webhook: Missing configuration');
        http_response_code(400);
        echo json_encode(['error' => 'HolestPay not configured']);
        exit;
    }
    
    // Process webhook based on topic
    switch ($topic) {
        case 'posconfig-updated':
            processPosConfigUpdated($pdo, $webhook_data);
            break;
            
        case 'orderupdate':
            processOrderUpdate($pdo, $webhook_data);
            break;
            
        case 'payresult':
            processPaymentResult($pdo, $webhook_data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown webhook topic: ' . $topic]);
            exit;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'topic' => $topic]);
    
} catch (Throwable $e) {
    error_log('HolestPay Direct Webhook Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Process posconfig-updated webhook
 */
function processPosConfigUpdated($pdo, $webhook_data) {
    try {
        // Store the configuration data
        $config_json = json_encode($webhook_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $merchant_site_uid = $webhook_data['merchant_site_uid'] ?? '';
        $environment = $webhook_data['environment'] ?? 'sandbox';
        
        // Check if config already exists
        $stmt = $pdo->prepare("SELECT id FROM " . DB_PREFIX . "holestpay_config WHERE merchant_site_uid = ? AND environment = ?");
        $stmt->execute([$merchant_site_uid, $environment]);
        $existing_config = $stmt->fetch();
        
        if ($existing_config) {
            // Update existing config
            $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "holestpay_config SET config_data = ?, date_modified = NOW() WHERE id = ?");
            $stmt->execute([$config_json, $existing_config['id']]);
        } else {
            // Insert new config
            $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "holestpay_config (merchant_site_uid, environment, config_data, date_added, date_modified) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$merchant_site_uid, $environment, $config_json]);
        }
        
        // Payment and shipping methods are already stored in the config_data JSON above
        // No need for separate tables since all data is available in holestpay_config
        
        error_log('HolestPay: POS config updated successfully');
        
    } catch (Exception $e) {
        error_log('HolestPay: Error processing posconfig-updated: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Process orderupdate webhook
 */
function processOrderUpdate($pdo, $webhook_data) {
    try {
        $order_uid = $webhook_data['order_uid'] ?? '';
        if (!$order_uid) {
            throw new Exception('Missing order_uid in webhook data');
        }
        
        // Find order by HPay UID
        $stmt = $pdo->prepare("SELECT order_id FROM " . DB_PREFIX . "order WHERE hpay_uid = ?");
        $stmt->execute([$order_uid]);
        $order = $stmt->fetch();
        
        if (!$order) {
            error_log('HolestPay: Order not found for UID: ' . $order_uid);
            return;
        }
        
        $order_id = $order['order_id'];
        
        // Get current order data
        $stmt = $pdo->prepare("SELECT hpay_data FROM " . DB_PREFIX . "order WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $current_order = $stmt->fetch();
        $current_data = $current_order ? json_decode($current_order['hpay_data'], true) : [];
        
        // Merge new data with existing data
        $merged_data = array_merge($current_data ?: [], $webhook_data);
        $merged_json = json_encode($merged_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Update order
        $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "order SET hpay_status = ?, hpay_data = ? WHERE order_id = ?");
        $stmt->execute([$webhook_data['status'] ?? '', $merged_json, $order_id]);
        
        // Update order status based on payment status
        updateOrderStatus($pdo, $order_id, $webhook_data['status'] ?? '');
        
        error_log('HolestPay: Order updated successfully: ' . $order_id);
        
    } catch (Exception $e) {
        error_log('HolestPay: Error processing orderupdate: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Process payresult webhook
 */
function processPaymentResult($pdo, $webhook_data) {
    // Same as orderupdate for now
    processOrderUpdate($pdo, $webhook_data);
}

// Payment and shipping methods are stored in holestpay_config.config_data JSON
// No separate tables needed since all data is available in the main config

/**
 * Update order status based on payment status
 */
function updateOrderStatus($pdo, $order_id, $payment_status) {
    // Get configuration from database
    $config_stmt = $pdo->prepare("SELECT `value` FROM " . DB_PREFIX . "setting WHERE `key` = ? AND store_id = 0");
    
    $config_stmt->execute(['payment_holestpay_order_status_id']);
    $success_status_id = $config_stmt->fetchColumn() ?: 5;
    
    $config_stmt->execute(['payment_holestpay_order_status_failed_id']);
    $failed_status_id = $config_stmt->fetchColumn() ?: 7;
    
    $status_map = [
        'PAID' => $success_status_id,
        'SUCCESS' => $success_status_id,
        'PAYING' => 1, // Pending
        'RESERVED' => 1, // Pending
        'AWAITING' => 1, // Pending
        'OBLIGATED' => 1, // Pending
        'FAILED' => $failed_status_id,
        'CANCELLED' => 7, // Cancelled
        'REFUNDED' => 11, // Refunded
    ];
    
    $new_status_id = $status_map[$payment_status] ?? null;
    
    if ($new_status_id) {
        $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "order SET order_status_id = ? WHERE order_id = ?");
        $stmt->execute([$new_status_id, $order_id]);
        
        // Add order history
        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "order_history (order_id, order_status_id, notify, comment, date_added) VALUES (?, ?, 0, ?, NOW())");
        $stmt->execute([$order_id, $new_status_id, 'HolestPay status update: ' . $payment_status]);
    }
}
?>
