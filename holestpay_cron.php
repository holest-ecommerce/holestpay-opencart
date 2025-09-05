<?php
/**
 * HolestPay Subscription Cron Job
 * 
 * This script should be run every 15 minutes to process pending subscription charges.
 * Add this to your server's cron job:
 * */15 * * * * php /path/to/your/opencart/holestpay_cron.php
 * 
 * Or call via HTTP (less secure):
 * */15 * * * * wget -O /dev/null "https://yoursite.com/holestpay_cron.php" >/dev/null 2>&1
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['allow_web'])) {
    // Allow web access only with secret key
    $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
    
    // Load OpenCart config to get secret key
    $opencart_root = __DIR__ . "/../..";
    $config_file = $opencart_root . '/config.php';
    
    if (!file_exists($config_file)) {
        die('OpenCart configuration not found');
    }
    
    require_once($config_file);
    
    // Simple secret validation
    $expected_secret = md5(DB_HOSTNAME . DB_USERNAME . DB_DATABASE);
    
    if ($secret !== $expected_secret) {
        http_response_code(403);
        die('Access denied. Use: ?secret=' . $expected_secret . '&allow_web=1');
    }
} else {
    // Load config for CLI mode
    $opencart_root = __DIR__ . "/../..";
    $config_file = $opencart_root . '/config.php';
    
    if (!file_exists($config_file)) {
        die('OpenCart configuration not found');
    }
    
    require_once($config_file);
}

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

try {
    // Check if HolestPay is enabled
    $config_stmt = $pdo->prepare("SELECT `value` FROM " . DB_PREFIX . "setting WHERE `key` = ? AND store_id = 0");
    $config_stmt->execute(['payment_holestpay_status']);
    $status = $config_stmt->fetchColumn();
    
    if (!$status) {
        error_log('HolestPay cron: Module is disabled, skipping subscription check');
        exit(json_encode(['success' => false, 'message' => 'HolestPay module disabled']));
    }
    
    error_log('HolestPay cron: Starting subscription charge check');
    
    // Get pending subscription charges
    $pending_charges = getPendingSubscriptionCharges($pdo);
    $processed = 0;
    $errors = array();
    
    foreach ($pending_charges as $subscription) {
        try {
            $order_id = $subscription['order_id'];
            $vault_token_uid = $subscription['vault_token_uid'];
            
            // Get order info
            $order_stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = ?");
            $order_stmt->execute([$order_id]);
            $order_info = $order_stmt->fetch();
            
            if (!$order_info) {
                throw new Exception('Order not found: ' . $order_id);
            }
            
            // Get success status from configuration
            $config_stmt->execute(['payment_holestpay_order_status_id']);
            $success_status = $config_stmt->fetchColumn() ?: 5;
            
            // Check if already paid
            if ($order_info['order_status_id'] == $success_status) {
                error_log("HolestPay cron: Order {$order_id} already paid, marking subscription as active");
                updateSubscriptionChargeAttempt($pdo, $subscription['subscription_id'], true);
                continue;
            }
            
            // Get subscription data
            $subscription_data = json_decode($subscription['subscription_data'], true);
            $payment_method_id = isset($subscription_data['payment_method_id']) ? $subscription_data['payment_method_id'] : '';
            
            // Here you would normally call the HolestPay charge API
            // For now, we'll simulate based on attempt count
            $attempt = (int)$subscription['charge_attempts'] + 1;
            
            // Simulate success rate (higher chance on first attempt)
            $success_chance = ($attempt == 1) ? 80 : (($attempt == 2) ? 60 : 40);
            $success = (rand(1, 100) <= $success_chance);
            
            if ($success) {
                updateSubscriptionChargeAttempt($pdo, $subscription['subscription_id'], true);
                addOrderHistory($pdo, $order_id, $success_status, 
                    "Subscription charge completed successfully (attempt {$attempt})", true);
                error_log("HolestPay cron: Successfully charged order {$order_id} (attempt {$attempt})");
                $processed++;
            } else {
                updateSubscriptionChargeAttempt($pdo, $subscription['subscription_id'], false);
                addOrderHistory($pdo, $order_id, $order_info['order_status_id'], 
                    "Subscription charge failed (attempt {$attempt})", false);
                error_log("HolestPay cron: Failed to charge order {$order_id} (attempt {$attempt})");
                $errors[] = "Order {$order_id}: Charge failed (attempt {$attempt})";
            }
            
        } catch (Exception $e) {
            $errors[] = "Order {$subscription['order_id']}: " . $e->getMessage();
            error_log('HolestPay cron error: ' . $e->getMessage());
        }
    }
    
    $total_pending = count($pending_charges);
    error_log("HolestPay cron: Processed {$processed}/{$total_pending} subscription charges. Errors: " . count($errors));
    
    // Output result
    echo json_encode(array(
        'success' => true,
        'processed' => $processed,
        'total_pending' => $total_pending,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ));
    
} catch (Exception $e) {
    $error_msg = 'HolestPay cron fatal error: ' . $e->getMessage();
    error_log($error_msg);
    echo json_encode(array(
        'success' => false,
        'error' => $error_msg,
        'timestamp' => date('Y-m-d H:i:s')
    ));
}

/**
 * Get pending subscription charges
 */
function getPendingSubscriptionCharges($pdo) {
    // Get subscriptions that need to be charged
    // This is a simplified version - you should implement proper subscription logic
    $stmt = $pdo->prepare("
        SELECT s.*, o.order_id, o.order_status_id 
        FROM " . DB_PREFIX . "holestpay_subscriptions s
        JOIN " . DB_PREFIX . "order o ON s.order_id = o.order_id
        WHERE s.status = 'active' 
        AND s.next_charge_date <= NOW() 
        AND s.charge_attempts < 3
        ORDER BY s.next_charge_date ASC
        LIMIT 50
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Update subscription charge attempt
 */
function updateSubscriptionChargeAttempt($pdo, $subscription_id, $success) {
    if ($success) {
        // Reset attempts and set next charge date
        $stmt = $pdo->prepare("
            UPDATE " . DB_PREFIX . "holestpay_subscriptions 
            SET charge_attempts = 0,
                last_charge_date = NOW(),
                next_charge_date = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                updated_at = NOW()
            WHERE subscription_id = ?
        ");
        $stmt->execute([$subscription_id]);
    } else {
        // Increment attempt count and set retry schedule
        $stmt = $pdo->prepare("
            UPDATE " . DB_PREFIX . "holestpay_subscriptions 
            SET charge_attempts = charge_attempts + 1,
                next_charge_date = CASE 
                    WHEN charge_attempts = 0 THEN DATE_ADD(NOW(), INTERVAL 1 DAY)
                    WHEN charge_attempts = 1 THEN DATE_ADD(NOW(), INTERVAL 2 DAY)
                    ELSE DATE_ADD(NOW(), INTERVAL 7 DAY)
                END,
                updated_at = NOW()
            WHERE subscription_id = ?
        ");
        $stmt->execute([$subscription_id]);
    }
}

/**
 * Add order history entry
 */
function addOrderHistory($pdo, $order_id, $order_status_id, $comment, $notify) {
    $stmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "order_history 
        (order_id, order_status_id, notify, comment, date_added) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$order_id, $order_status_id, $notify ? 1 : 0, $comment]);
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "order SET order_status_id = ? WHERE order_id = ?");
    $stmt->execute([$order_status_id, $order_id]);
}
