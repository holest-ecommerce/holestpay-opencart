<?php
/**
 * HolestPay Direct Order User URL Handler
 * Handles user return from payment and hpay_forwarded_payment_response
 * 
 * URL: https://yourdomain.com/holestpay_return.php?order_id=123
 */

// CRITICAL: Set headers for accessibility
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    if (!$order_id) {
        // Redirect to home if no order ID
        header('Location: ' . HTTP_SERVER);
        exit;
    }
    
    // Check for hpay_forwarded_payment_response POST parameter
    if (isset($_POST['hpay_forwarded_payment_response'])) {
        $forwarded_response = $_POST['hpay_forwarded_payment_response'];
        
        try {
            // Decode JSON if it's a string
            if (is_string($forwarded_response)) {
                $forwarded_response = json_decode($forwarded_response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("HolestPay: Invalid JSON in hpay_forwarded_payment_response: " . json_last_error_msg());
                    $forwarded_response = null;
                }
            }
            
            if ($forwarded_response && is_array($forwarded_response)) {
                // Process the forwarded response like an orderupdate webhook
                $order_uid = $forwarded_response['order_uid'] ?? '';
                if ($order_uid) {
                    // Find order by HPay UID
                    $stmt = $pdo->prepare("SELECT order_id FROM " . DB_PREFIX . "order WHERE hpay_uid = ?");
                    $stmt->execute([$order_uid]);
                    $order = $stmt->fetch();
                    
                    if ($order) {
                        $found_order_id = $order['order_id'];
                        
                        // Get current order data
                        $stmt = $pdo->prepare("SELECT hpay_data FROM " . DB_PREFIX . "order WHERE order_id = ?");
                        $stmt->execute([$found_order_id]);
                        $current_order = $stmt->fetch();
                        $current_data = $current_order ? json_decode($current_order['hpay_data'], true) : [];
                        
                        // Merge new data with existing data
                        $merged_data = array_merge($current_data ?: [], $forwarded_response);
                        $merged_json = json_encode($merged_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        
                        // Update order
                        $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "order SET hpay_status = ?, hpay_data = ? WHERE order_id = ?");
                        $stmt->execute([$forwarded_response['status'] ?? '', $merged_json, $found_order_id]);
                        
                        error_log("HolestPay: Successfully processed forwarded payment response for order {$found_order_id}");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("HolestPay: Exception processing forwarded payment response: " . $e->getMessage());
        }
    }
    
    // Get order information for display
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order_info = $stmt->fetch();
    
    if (!$order_info) {
        // Order not found, redirect to home
        header('Location: ' . HTTP_SERVER);
        exit;
    }
    
    $hpay_data = json_decode($order_info['hpay_data'], true) ?: [];
    
    // Determine success or failure based on payment status
    $payment_status = $order_info['hpay_status'] ?? '';
    $is_success = in_array($payment_status, ['PAID', 'SUCCESS', 'PAYING', 'RESERVED', 'AWAITING', 'OBLIGATED']);
    
    if ($is_success) {
        // Redirect to success page
        header('Location: ' . HTTP_SERVER . 'index.php?route=checkout/success');
    } else {
        // Redirect to failure page
        header('Location: ' . HTTP_SERVER . 'index.php?route=checkout/failure');
    }
    
} catch (Exception $e) {
    error_log('HolestPay Return Handler Error: ' . $e->getMessage());
    // Redirect to home on error
    header('Location: ' . HTTP_SERVER);
}
?>
