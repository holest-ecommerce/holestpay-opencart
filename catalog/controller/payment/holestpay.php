<?php
namespace Opencart\Catalog\Controller\Payment;

class Holestpay extends \Opencart\System\Engine\Controller {
    
    public function index() {
        // Debug logging - always log when controller is called
        error_log('HolestPay Controller index() called - Route: ' . ($_GET['route'] ?? 'unknown') . ' - URL: ' . $_SERVER['REQUEST_URI'] ?? '');
        
        // Debug routing test
        if (isset($_GET['debug'])) {
            echo "HolestPay Index Debug: Method called successfully - Route: " . ($_GET['route'] ?? 'unknown');
            exit;
        }
        
        // CRITICAL: Handle webhook calls within index method since OpenCart 4 extension routing 
        // doesn't support method calls like /webhook at the end of extension routes
        if (isset($_GET['webhook']) || isset($_GET['topic']) || 
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/webhook') !== false)) {
            return $this->webhook();
        }
        
        // Handle orderUserUrl calls
        if (isset($_GET['orderUserUrl']) || isset($_GET['order_id'])) {
            return $this->orderUserUrl();
        }
        
        $this->load->language('payment/holestpay');
        $this->load->model('extension/holestpay/payment/holestpay');
        
        // CRITICAL: Set CSP headers for HolestPay integration
        $this->setHolestPayCSPHeaders();
        
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_please_wait'] = $this->language->get('text_please_wait');
        
        // Check if module is configured
        if (!$this->config->get('payment_holestpay_merchant_site_uid') || 
            !$this->config->get('payment_holestpay_secret_key')) {
            $data['error'] = $this->language->get('error_configuration');
            return $this->load->view('payment/holestpay_error', $data);
        }
        
        // Get HolestPay configuration and payment methods
        $hpay_config = $this->model_extension_holestpay_payment_holestpay->getHolestPayConfig();
        $payment_methods = $this->model_extension_holestpay_payment_holestpay->getHolestPayMethods();
        
        if (empty($payment_methods)) {
            $data['error'] = $this->language->get('error_no_payment_methods');
            return $this->load->view('payment/holestpay_error', $data);
        }
        
        // Get cart data for HolestPay
        $cart_data = $this->model_extension_holestpay_payment_holestpay->getCartData();
        
        // Get customer vault tokens if logged in
        $vault_tokens = array();
        if ($this->customer->isLogged()) {
            $vault_tokens = $this->model_extension_holestpay_payment_holestpay->getCustomerVaultTokens($this->customer->getId());
        }
        
        $data['payment_methods'] = $payment_methods;
        $data['vault_tokens'] = $vault_tokens;
        $data['cart_data'] = $cart_data;
        $data['environment'] = $this->config->get('payment_holestpay_environment');
        $data['merchant_site_uid'] = $this->config->get('payment_holestpay_merchant_site_uid');
        $data['description'] = $this->config->get('payment_holestpay_description');
        
        // Generate checkout data for HolestPayCheckout JavaScript object
        $data['holestpay_checkout_data'] = json_encode(array(
            'environment' => $this->config->get('payment_holestpay_environment'),
            'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
            'cart' => $cart_data,
            'payment_methods' => $payment_methods,
            'vault_tokens' => $vault_tokens,
            'customer_id' => $this->customer->isLogged() ? $this->customer->getId() : null,
            'urls' => array(
                            'confirm' => $this->url->link('payment/holestpay/confirm', '', true),
            'webhook' => HTTP_SERVER . 'extension/holestpay/holestpay_webhook.php'
            )
        ));
        
        return $this->load->view('payment/holestpay', $data);
    }
    
    public function webhookDirect() {
        // Alternative webhook endpoint using direct route (payment/holestpay/webhookDirect)
        // CRITICAL: Webhooks must be publicly accessible without authentication
        
        // Set headers to ensure webhook accessibility
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Force immediate output to verify webhook is being called
        if (isset($_GET['debug'])) {
            echo "HolestPay WebhookDirect Debug: Method called successfully";
            exit;
        }
        
        // Call the main webhook method
        return $this->webhook();
    }
    
    public function confirm() {
        $this->load->language('payment/holestpay');
        $this->load->model('payment/holestpay');
        $this->load->model('checkout/order');
        
        $json = array();
        
        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        if (!$order_info) {
            $json['error'] = $this->language->get('error_order');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        // Get POST data
        $payment_method_id = isset($this->request->post['payment_method_id']) ? $this->request->post['payment_method_id'] : '';
        $vault_token_uid = isset($this->request->post['vault_token_uid']) ? $this->request->post['vault_token_uid'] : '';
        $cof = isset($this->request->post['cof']) ? $this->request->post['cof'] : 'none';
        
        // Generate HolestPay request
        $hpay_request = $this->generateHPayRequest($order_info, $payment_method_id, $vault_token_uid, $cof);
        
        if (!$hpay_request) {
            $json['error'] = $this->language->get('error_request_generation');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        // Add signature
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        $hpay_request['signature'] = $this->model_extension_holestpay_payment_holestpay->generateSignature($hpay_request, $secret_key);
        
        // Update order with HolestPay data
        $this->model_extension_holestpay_payment_holestpay->createOrder(array('order_id' => $order_info['order_id']));
        
        $json['success'] = true;
        $json['hpay_request'] = $hpay_request;
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function generateHPayRequest($order_info, $payment_method_id, $vault_token_uid = '', $cof = 'none') {
        $this->load->model('payment/holestpay');
        
        // Generate order items
        $order_items = $this->model_extension_holestpay_payment_holestpay->generateOrderItems($order_info);
        
        $request = array(
            'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
            'order_uid' => $order_info['order_id'],
            'order_name' => '#' . $order_info['order_id'],
            'order_amount' => $order_info['total'],
            'order_currency' => $order_info['currency_code'],
            'order_items' => $order_items,
            'order_billing' => array(
                'email' => $order_info['email'],
                'first_name' => $order_info['payment_firstname'],
                'last_name' => $order_info['payment_lastname'],
                'phone' => $order_info['telephone'],
                'company' => $order_info['payment_company'],
                'address' => $order_info['payment_address_1'],
                'address2' => $order_info['payment_address_2'],
                'city' => $order_info['payment_city'],
                'country' => $order_info['payment_country'],
                'postcode' => $order_info['payment_postcode'],
                'lang' => $this->config->get('config_language')
            ),
            'order_shipping' => array(
                'first_name' => $order_info['shipping_firstname'],
                'last_name' => $order_info['shipping_lastname'],
                'company' => $order_info['shipping_company'],
                'address' => $order_info['shipping_address_1'],
                'address2' => $order_info['shipping_address_2'],
                'city' => $order_info['shipping_city'],
                'country' => $order_info['shipping_country'],
                'postcode' => $order_info['shipping_postcode']
            ),
            'payment_method' => $payment_method_id,
            'vault_token_uid' => $vault_token_uid,
            'cof' => $cof,
            'notify_url' => HTTP_SERVER . 'extension/holestpay/holestpay_webhook.php',
            'return_url' => $this->url->link('checkout/success', '', true),
            'cancel_url' => $this->url->link('checkout/failure', '', true)
        );
        
        return $request;
    }
    
    public function webhook() {
        // CRITICAL: Webhooks must be publicly accessible without authentication
        // This method is called by HolestPay external service
        
        // Set headers to ensure webhook accessibility
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Log webhook access for debugging
        error_log('HolestPay Webhook accessed: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
        
        // Force immediate output to verify webhook is being called
        if (isset($_GET['debug'])) {
            echo "HolestPay Webhook Debug: Method called successfully";
            exit;
        }
        
        try {
            $this->load->model('payment/holestpay');
            $this->load->model('checkout/order');
        
        // Get topic from URL parameter
        $topic = isset($this->request->get['topic']) ? $this->request->get['topic'] : '';
        
        // Get webhook data
        $input = file_get_contents('php://input');
        $webhook_data = json_decode($input, true);
        
        if (!$webhook_data) {
            http_response_code(400);
            echo 'Invalid JSON data';
            exit;
        }
        
        // Process webhook based on topic
        switch ($topic) {
            case 'posconfig-updated':
                $this->processPosConfigUpdatedWebhook($webhook_data);
                break;
                
            case 'orderupdate':
                $this->processOrderUpdateWebhook($webhook_data);
                break;
                
            case 'payresult':
                $this->processPaymentResultWebhook($webhook_data);
                break;
                
            default:
                http_response_code(400);
                echo 'Unknown webhook topic: ' . $topic;
                exit;
        }
        
        http_response_code(200);
        echo 'OK';
        
        } catch (Throwable $e) {
            error_log('HolestPay Webhook Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            http_response_code(500);
            echo 'ERROR';
        }
    }
    
    private function verifyWebhookSignature($webhook_data) {
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        $received_signature = isset($webhook_data['signature']) ? $webhook_data['signature'] : '';
        
        if (!$received_signature || !$secret_key) {
            return false;
        }
        
        // Remove signature from data for verification
        unset($webhook_data['signature']);
        
        // Generate expected signature
        $data_string = json_encode($webhook_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected_signature = hash_hmac('sha256', $data_string, $secret_key);
        
        return hash_equals($expected_signature, $received_signature);
    }
    
    private function processPosConfigUpdatedWebhook($webhook_data) {
        // Verify required fields for posconfig-updated webhook
        if (!isset($webhook_data['environment']) || !isset($webhook_data['merchant_site_uid']) || 
            !isset($webhook_data['POS']) || !isset($webhook_data['checkstr'])) {
            http_response_code(400);
            echo 'Missing required fields in posconfig-updated webhook';
            exit;
        }
        
        $environment = $webhook_data['environment'];
        $merchant_site_uid = $webhook_data['merchant_site_uid'];
        $checkstr = $webhook_data['checkstr'];
        $pos_config = $webhook_data['POS'];
        
        // Verify environment and merchant_site_uid match our configuration
        if ($environment !== $this->config->get('payment_holestpay_environment') ||
            $merchant_site_uid !== $this->config->get('payment_holestpay_merchant_site_uid')) {
            http_response_code(400);
            echo 'Environment or merchant site UID mismatch';
            exit;
        }
        
        // Verify checkstr (signature)
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        $expected_checkstr = md5($merchant_site_uid . $secret_key);
        
        if ($checkstr !== $expected_checkstr) {
            http_response_code(401);
            echo 'Invalid checkstr signature';
            exit;
        }
        
        // Save the complete POS configuration
        $config_data = json_encode($pos_config);
        
        // Load admin model for saving configuration
        $this->load->model('payment/holestpay');
        
        // Check if config exists
        $existing_config = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if ($existing_config->num_rows) {
            // Update existing configuration
            $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_config` SET 
                `config_data` = '" . $this->db->escape($config_data) . "',
                `date_modified` = NOW()
                WHERE `environment` = '" . $this->db->escape($environment) . "' 
                AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        } else {
            // Insert new configuration
            $this->db->query("INSERT INTO `" . DB_PREFIX . "holestpay_config` SET 
                `environment` = '" . $this->db->escape($environment) . "',
                `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "',
                `config_data` = '" . $this->db->escape($config_data) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()");
        }
        
        // Update payment methods from POS.payment array
        if (isset($pos_config['payment']) && is_array($pos_config['payment'])) {
            $this->updatePaymentMethods($pos_config['payment'], $environment, $merchant_site_uid);
        }
        
        // Update shipping methods from POS.shipping array
        if (isset($pos_config['shipping']) && is_array($pos_config['shipping'])) {
            $this->updateShippingMethods($pos_config['shipping'], $environment, $merchant_site_uid);
        }
        
        return true;
    }
    
    private function processOrderUpdateWebhook($webhook_data) {
        if (!isset($webhook_data['order_uid']) || !isset($webhook_data['hpay_status'])) {
            return false;
        }
        
        // CRITICAL: Set flag to prevent order_store API calls during webhook processing
        $_SESSION['holestpay_webhook_processing'] = true;
        
        try {
            $order_id = $webhook_data['order_uid'];
            $hpay_status = $webhook_data['hpay_status'];
            $new_hpay_data = isset($webhook_data['hpay_data']) ? $webhook_data['hpay_data'] : array();
            
            // CRITICAL: Use merge method like Magento - preserve existing data, merge new data
            $this->mergeHPayOrderData($order_id, $hpay_status, $new_hpay_data);
            
            // Update OpenCart order status based on HolestPay status
            $this->updateOrderStatus($order_id, $hpay_status);
            
            return true;
        } finally {
            // Always clear the flag
            unset($_SESSION['holestpay_webhook_processing']);
        }
    }
    
    private function mergeHPayOrderData($order_id, $hpay_status, $new_hpay_data) {
        // Get existing HolestPay data
        $query = $this->db->query("SELECT `hpay_data` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        $existing_data = array();
        if ($query->num_rows && $query->row['hpay_data']) {
            $existing_data = json_decode($query->row['hpay_data'], true) ?: array();
        }
        
        // CRITICAL: Merge method like Magento - deep merge preserving existing data
        $merged_data = $this->deepMergeArrays($existing_data, $new_hpay_data);
        
        // Add timestamp for this update
        $merged_data['last_updated'] = date('Y-m-d H:i:s');
        $merged_data['payment_status'] = $this->extractPaymentStatus($hpay_status);
        
        // Update order with merged data
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `hpay_status` = '" . $this->db->escape($hpay_status) . "',
            `hpay_data` = '" . $this->db->escape(json_encode($merged_data)) . "'
            WHERE `order_id` = '" . (int)$order_id . "'");
    }
    
    private function deepMergeArrays($existing, $new) {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                // Recursive merge for nested arrays
                $existing[$key] = $this->deepMergeArrays($existing[$key], $value);
            } else {
                // Overwrite or add new values
                $existing[$key] = $value;
            }
        }
        return $existing;
    }
    
    private function extractPaymentStatus($hpay_status) {
        // Extract payment status from HPay Status format: "payment|shipping|fiscal|integration"
        $status_parts = explode('|', $hpay_status);
        return isset($status_parts[0]) ? $status_parts[0] : '';
    }
    
    private function processPaymentResultWebhook($webhook_data) {
        if (!isset($webhook_data['order_uid']) || !isset($webhook_data['payment_status'])) {
            return false;
        }
        
        $order_id = $webhook_data['order_uid'];
        $payment_status = $webhook_data['payment_status'];
        
        $order_info = $this->model_checkout_order->getOrder($order_id);
        
        if (!$order_info) {
            return false;
        }
        
        // Determine order status based on payment result
        if (in_array($payment_status, array('SUCCESS', 'PAID'))) {
            $order_status_id = $this->config->get('payment_holestpay_order_status_id');
            $comment = 'Payment successful via HolestPay';
        } else {
            $order_status_id = $this->config->get('payment_holestpay_order_status_failed_id');
            $comment = 'Payment failed via HolestPay: ' . $payment_status;
        }
        
        // Add order history
        $this->model_checkout_order->addHistory($order_id, $order_status_id, $comment, true);
        
        // Save vault token if provided and customer is logged in
        if (isset($webhook_data['vault_token_uid']) && isset($webhook_data['vault_card_mask']) && $order_info['customer_id']) {
            $this->model_extension_holestpay_payment_holestpay->saveVaultToken(
                $order_info['customer_id'],
                $webhook_data['vault_token_uid'],
                $webhook_data['vault_card_mask'],
                $webhook_data['payment_method_id']
            );
        }
        
        return true;
    }
    
    private function updateOrderStatus($order_id, $hpay_status) {
        // Parse HolestPay status and determine OpenCart order status
        $status_parts = explode('|', $hpay_status);
        $payment_status = isset($status_parts[0]) ? $status_parts[0] : '';
        
        if (in_array($payment_status, array('SUCCESS', 'PAID'))) {
            $order_status_id = $this->config->get('payment_holestpay_order_status_id');
        } elseif (in_array($payment_status, array('FAILED', 'REFUSED', 'CANCELED'))) {
            $order_status_id = $this->config->get('payment_holestpay_order_status_failed_id');
        } else {
            return; // Don't update for pending/processing statuses
        }
        
        $this->model_checkout_order->addHistory($order_id, $order_status_id, 'Order status updated via HolestPay webhook', true);
    }
    
    private function updatePaymentMethods($payment_methods, $environment, $merchant_site_uid) {
        // DEPRECATED: Payment methods are now stored in POS configuration in holestpay_config table
        // This method is kept for backward compatibility but does nothing
        // Payment methods are now managed through the POS configuration
        
        error_log("HolestPay: updatePaymentMethods() called but payment methods are now managed via POS configuration");
        return true;
    }
    
    private function updateShippingMethods($shipping_methods, $environment, $merchant_site_uid) {
        // DEPRECATED: Shipping methods are now stored in POS configuration in holestpay_config table
        // This method is kept for backward compatibility but does nothing
        // Shipping methods are now managed through the POS configuration
        
        error_log("HolestPay: updateShippingMethods() called but shipping methods are now managed via POS configuration");
        return true;
    }
    
    public function charge() {
        // Handle subscription charges (MIT/COF) - like WooCommerce sample
        $this->load->model('payment/holestpay');
        
        $json = array();
        
        // Get charge parameters
        $order_id = isset($this->request->post['order_id']) ? (int)$this->request->post['order_id'] : 0;
        $vault_token_uid = isset($this->request->post['vault_token_uid']) ? $this->request->post['vault_token_uid'] : '';
        $payment_method_id = isset($this->request->post['payment_method_id']) ? $this->request->post['payment_method_id'] : '';
        
        if (!$order_id || !$vault_token_uid) {
            $json['error'] = 'Missing required parameters: order_id and vault_token_uid';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        try {
            // Load order
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);
            
            if (!$order_info) {
                throw new Exception('Order not found');
            }
            
            // Check if order is already paid
            if ($order_info['order_status_id'] == $this->config->get('payment_holestpay_order_status_id')) {
                throw new Exception('Order is already paid');
            }
            
            // Generate charge request (like WooCommerce sample)
            $charge_request = $this->generateChargeRequest($order_info, $vault_token_uid, $payment_method_id);
            
            if (!$charge_request) {
                throw new Exception('Failed to generate charge request');
            }
            
            // Send charge request to HolestPay
            $result = $this->sendChargeRequest($charge_request);
            
            if ($result['success']) {
                $json['success'] = true;
                $json['message'] = 'Charge processed successfully';
                $json['transaction_id'] = isset($result['transaction_id']) ? $result['transaction_id'] : '';
                
                // Update order status if successful
                if (isset($result['status']) && $result['status'] == 'SUCCESS') {
                    $this->model_checkout_order->addOrderHistory($order_id, 
                        $this->config->get('payment_holestpay_order_status_id'), 
                        'Payment completed via HolestPay charge (vault token)', true);
                }
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            $json['error'] = $e->getMessage();
            error_log("HolestPay charge error for order {$order_id}: " . $e->getMessage());
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function generateChargeRequest($order_info, $vault_token_uid, $payment_method_id) {
        $environment = $this->config->get('payment_holestpay_environment');
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        
        if (!$merchant_site_uid || !$secret_key) {
            return false;
        }
        
        // Prepare charge request (similar to WooCommerce sample)
        $charge_request = array(
            'merchant_site_uid' => $merchant_site_uid,
            'order_uid' => $order_info['order_id'],
            'order_amount' => number_format($order_info['total'], 2, '.', ''),
            'order_currency' => $order_info['currency_code'],
            'payment_method' => $payment_method_id,
            'vault_token_uid' => $vault_token_uid,
            'subscription_uid' => '', // Can be extended for subscriptions
            'notify_url' => HTTP_SERVER . 'extension/holestpay/holestpay_webhook.php?topic=payresult',
            'order_user_url' => HTTP_SERVER . 'extension/holestpay/holestpay_return.php?order_id=' . $order_info['order_id']
        );
        
        // Sign the request
        $signature_string = $charge_request['order_uid'] . '|' . 
                           $charge_request['order_amount'] . '|' . 
                           $charge_request['order_currency'] . '|' . 
                           $vault_token_uid . '|' . 
                           $charge_request['subscription_uid'] . '|' . 
                           $secret_key;
        
        $charge_request['verificationhash'] = hash('sha256', $signature_string);
        
        return $charge_request;
    }
    
    private function sendChargeRequest($charge_request) {
        $environment = $this->config->get('payment_holestpay_environment');
        
        // HolestPay charge API URL
        $api_url = ($environment === 'production') 
            ? 'https://pay.holest.com/clientpay/charge' 
            : 'https://sandbox.pay.holest.com/clientpay/charge';
        
        // Send charge request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request_data' => $charge_request)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'User-Agent: OpenCart-HolestPay/1.0'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to HolestPay API'];
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code === 200 && isset($response_data['status'])) {
            return ['success' => true, 'status' => $response_data['status'], 'transaction_id' => isset($response_data['transaction_uid']) ? $response_data['transaction_uid'] : ''];
        } else {
            $error = isset($response_data['error']) ? $response_data['error'] : 'Unknown API error';
            return ['success' => false, 'error' => $error];
        }
    }
    
    public function orderUserUrl() {
        // CRITICAL: This is the order_user_url where user is redirected after payment
        // Must be publicly accessible without authentication - users may not be logged in
        // Handles hpay_forwarded_payment_response POST parameter same as orderupdate webhook
        
        // Set headers to ensure accessibility (similar to webhook)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $this->load->language('payment/holestpay');
        
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        
        if (!$order_id) {
            $this->response->redirect($this->url->link('common/home'));
            return;
        }
        
        // CRITICAL: Check for hpay_forwarded_payment_response POST parameter
        // This should be treated the same way as orderupdate webhook data
        if (isset($this->request->post['hpay_forwarded_payment_response'])) {
            $forwarded_response = $this->request->post['hpay_forwarded_payment_response'];
            
            // CRITICAL: Set flag to prevent order_store API calls during forwarded response processing
            $_SESSION['holestpay_webhook_processing'] = true;
            
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
                    // Ensure order_uid is set for the webhook processing
                    $webhook_data = array_merge($forwarded_response, array('order_uid' => $order_id));
                    
                    // Process this exactly like an orderupdate webhook
                    $result = $this->processOrderUpdateWebhook($webhook_data);
                    
                    if ($result) {
                        error_log("HolestPay: Successfully processed forwarded payment response for order {$order_id}");
                    } else {
                        error_log("HolestPay: Failed to process forwarded payment response for order {$order_id}");
                    }
                } else {
                    error_log("HolestPay: Invalid hpay_forwarded_payment_response format for order {$order_id}");
                }
            } catch (Exception $e) {
                error_log("HolestPay: Exception processing forwarded payment response for order {$order_id}: " . $e->getMessage());
            } finally {
                // Always clear the flag
                unset($_SESSION['holestpay_webhook_processing']);
            }
        }
        
        // Load order model
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        
        if (!$order_info) {
            $this->response->redirect($this->url->link('common/home'));
            return;
        }
        
        // Get HolestPay order data (may have been updated by forwarded response)
        $hpay_data = $this->getHolestPayOrderData($order_id);
        
        $data = array();
        $data['order_id'] = $order_id;
        $data['order_info'] = $order_info;
        
        // Payment outcome title
        $data['payment_outcome_title'] = $this->getPaymentOutcomeTitle($hpay_data);
        
        // Transaction user info with translated keys but non-translated values
        $data['transaction_user_info'] = $this->getTransactionUserInfo($hpay_data);
        
        // Fiscal HTML from HolestPay
        $data['fiscal_html'] = isset($hpay_data['fiscal_html']) ? $hpay_data['fiscal_html'] : '';
        
        // Shipping HTML from HolestPay
        $data['shipping_html'] = isset($hpay_data['shipping_html']) ? $hpay_data['shipping_html'] : '';
        
        // Load template
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('payment/holestpay_order_result', $data));
    }
    
    private function getHolestPayOrderData($order_id) {
        $query = $this->db->query("SELECT `hpay_data` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        if ($query->num_rows) {
            return json_decode($query->row['hpay_data'], true) ?: array();
        }
        
        return array();
    }
    
    private function getPaymentOutcomeTitle($hpay_data) {
        $status = isset($hpay_data['payment_status']) ? $hpay_data['payment_status'] : 'unknown';
        
        switch (strtolower($status)) {
            case 'completed':
            case 'success':
                return $this->language->get('text_payment_success');
            case 'failed':
                return $this->language->get('text_payment_failed');
            case 'cancelled':
                return $this->language->get('text_payment_cancelled');
            case 'pending':
                return $this->language->get('text_payment_pending');
            default:
                return $this->language->get('text_payment_unknown');
        }
    }
    
    private function getTransactionUserInfo($hpay_data) {
        $transaction_info = isset($hpay_data['transaction_user_info']) ? $hpay_data['transaction_user_info'] : array();
        $translated_info = array();
        
        // Translate keys but keep original values
        foreach ($transaction_info as $key => $value) {
            $translated_key = $this->language->get('text_' . strtolower(str_replace(' ', '_', $key)));
            if ($translated_key === 'text_' . strtolower(str_replace(' ', '_', $key))) {
                // Translation not found, use original key
                $translated_key = $key;
            }
            $translated_info[$translated_key] = $value; // Keep original value
        }
        
        return $translated_info;
    }
    
    private function setHolestPayCSPHeaders() {
        // CRITICAL: CSP headers for HolestPay integration
        $environment = $this->config->get('payment_holestpay_environment');
        
        // Determine HolestPay domains based on environment
        $hpay_domain = ($environment === 'production') ? 'pay.holest.com' : 'sandbox.pay.holest.com';
        
        // CSP policy allowing:
        // - eval for HolestPay scripts
        // - iframe loading from HolestPay domains
        // - script loading from HolestPay domains
        $csp_policy = "default-src 'self'; " .
                     "script-src 'self' 'unsafe-eval' https://{$hpay_domain}; " .
                     "frame-src 'self' https://{$hpay_domain}; " .
                     "connect-src 'self' https://{$hpay_domain}; " .
                     "img-src 'self' data: https://{$hpay_domain}; " .
                     "style-src 'self' 'unsafe-inline' https://{$hpay_domain};";
        
        // Set CSP headers
        $this->response->addHeader('Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-WebKit-CSP: ' . $csp_policy);
        
        // Additional security headers for iframe embedding
        $this->response->addHeader('X-Frame-Options: SAMEORIGIN');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        
        // Log CSP policy for debugging
        error_log("HolestPay CSP Policy set for domain: {$hpay_domain}");
    }
    
    // SUBSCRIPTION SCHEDULING SYSTEM (like WooCommerce hpay_15min_run)
    public function checkSubscriptionCharges() {
        // This method should be called by a cron job every 15 minutes
        $this->load->model('payment/holestpay');
        
        $json = array('processed' => 0, 'errors' => array());
        
        try {
            // Get pending subscription charges (like WooCommerce sample)
            $pending_charges = $this->model_extension_holestpay_payment_holestpay->getPendingSubscriptionCharges();
            
            foreach ($pending_charges as $subscription) {
                try {
                    // Process charge for this subscription
                    $result = $this->processSubscriptionCharge($subscription);
                    
                    if ($result['success']) {
                        $this->model_extension_holestpay_payment_holestpay->updateSubscriptionChargeAttempt(
                            $subscription['subscription_id'], true);
                        $json['processed']++;
                    } else {
                        $this->model_extension_holestpay_payment_holestpay->updateSubscriptionChargeAttempt(
                            $subscription['subscription_id'], false);
                        $json['errors'][] = 'Order ' . $subscription['order_id'] . ': ' . $result['error'];
                    }
                    
                } catch (Exception $e) {
                    $json['errors'][] = 'Order ' . $subscription['order_id'] . ': ' . $e->getMessage();
                    error_log("HolestPay subscription charge error: " . $e->getMessage());
                }
            }
            
            $json['success'] = true;
            $json['message'] = "Processed {$json['processed']} subscription charges";
            
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
            error_log("HolestPay subscription check error: " . $e->getMessage());
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function processSubscriptionCharge($subscription) {
        // Load order
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($subscription['order_id']);
        
        if (!$order_info) {
            return ['success' => false, 'error' => 'Order not found'];
        }
        
        // Check if order is already paid
        if ($order_info['order_status_id'] == $this->config->get('payment_holestpay_order_status_id')) {
            return ['success' => false, 'error' => 'Order is already paid'];
        }
        
        // Get subscription data
        $subscription_data = json_decode($subscription['subscription_data'], true);
        $payment_method_id = isset($subscription_data['payment_method_id']) ? $subscription_data['payment_method_id'] : '';
        
        // Generate charge request
        $charge_request = $this->generateChargeRequest($order_info, $subscription['vault_token_uid'], $payment_method_id);
        
        if (!$charge_request) {
            return ['success' => false, 'error' => 'Failed to generate charge request'];
        }
        
        // Send charge request
        $result = $this->sendChargeRequest($charge_request);
        
        if ($result['success']) {
            // Add order note
            $this->model_checkout_order->addOrderHistory($subscription['order_id'], 
                $this->config->get('payment_holestpay_order_status_id'), 
                'Subscription charge completed successfully (attempt ' . ($subscription['charge_attempts'] + 1) . ')', true);
        } else {
            // Add order note for failed attempt
            $this->model_checkout_order->addOrderHistory($subscription['order_id'], 
                $order_info['order_status_id'], 
                'Subscription charge failed (attempt ' . ($subscription['charge_attempts'] + 1) . '): ' . $result['error'], false);
        }
        
        return $result;
    }
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Catalog\Controller\Extension\Holestpay\Payment\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/payment/holestpay.php";
}
