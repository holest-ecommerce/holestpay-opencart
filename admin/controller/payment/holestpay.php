<?php
namespace Opencart\Admin\Controller\Payment;

class Holestpay extends \Opencart\System\Engine\Controller {
    private $error = array();
    
    private function getParameterValue($name, $default = null) {
        // Simple OpenCart config getter with fallback to default
        $value = $this->config->get('payment_holestpay_' . $name);
        return ($value !== null && $value !== '') ? $value : $default;
    }
    
    public function index(): void {
        try {
            // Load language for both route structures
            $this->load->language('payment/holestpay');
            
            // Also try to load extension language if accessed via extension route
            $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
            if (strpos($route, 'extension/holestpay/payment/holestpay') !== false) {
                // Try to load extension language file as well
                try {
                    $this->load->language('extension/holestpay/payment/holestpay');
                } catch (Exception $e) {
                    // Fallback to main language file if extension language fails
                    error_log('HolestPay: Could not load extension language file: ' . $e->getMessage());
                }
            }
            
            $this->document->setTitle($this->language->get('heading_title'));
            
            // CRITICAL: Set CSP headers for HolestPay admin integration
            $this->setHolestPayCSPHeaders();
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_holestpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }
        
        // Error handling
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        if (isset($this->error['merchant_site_uid'])) {
            $data['error_merchant_site_uid'] = $this->error['merchant_site_uid'];
        } else {
            $data['error_merchant_site_uid'] = '';
        }
        
        if (isset($this->error['secret_key'])) {
            $data['error_secret_key'] = $this->error['secret_key'];
        } else {
            $data['error_secret_key'] = '';
        }
        
        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/holestpay/payment/holestpay', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        // Form data
        $data['action'] = $this->url->link('extension/holestpay/payment/holestpay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        
        // Configuration fields
        $data['payment_holestpay_status'] = $this->getParameterValue('status', '0');
        $data['payment_holestpay_environment'] = $this->getParameterValue('environment', 'sandbox');
        $data['payment_holestpay_merchant_site_uid'] = $this->getParameterValue('merchant_site_uid', '');
        $data['payment_holestpay_secret_key'] = $this->getParameterValue('secret_key', '');
        $data['payment_holestpay_title'] = $this->getParameterValue('title', 'HolestPay');
        $data['payment_holestpay_description'] = $this->getParameterValue('description', 'Pay securely with HolestPay payment methods');
        
        // Get HolestPay configuration data based on saved settings
        $data['holestpay_pos_config'] = '{}';
        $data['has_pos_config'] = false;

        if (!empty($data['payment_holestpay_merchant_site_uid']) && !empty($data['payment_holestpay_environment'])) {
            // Use saved OpenCart settings to look up config in holestpay_config table
            try {
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` 
                    WHERE `merchant_site_uid` = '" . $this->db->escape($data['payment_holestpay_merchant_site_uid']) . "' 
                    AND `environment` = '" . $this->db->escape($data['payment_holestpay_environment']) . "'
                    ORDER BY `date_modified` DESC LIMIT 1");
                
                if ($query->num_rows > 0) {
                    $config_data = json_decode($query->row['config_data'], true);
                    if ($config_data && isset($config_data['POS'])) {
                        $data['holestpay_pos_config'] = $query->row['config_data'];
                        $data['has_pos_config'] = true;
                        $this->log->write('HolestPay: Found POS config for ' . $data['payment_holestpay_environment'] . ': ' . $data['payment_holestpay_merchant_site_uid']);
                    }
                }
            } catch (Exception $e) {
                $this->log->write('HolestPay: Could not load POS config: ' . $e->getMessage());
            }
        } else {
            // If OpenCart settings are empty, check if we have any webhook config to suggest
            try {
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` ORDER BY `date_modified` DESC LIMIT 1");
                if ($query->num_rows > 0) {
                    $data['suggested_environment'] = $query->row['environment'];
                    $data['suggested_merchant_site_uid'] = $query->row['merchant_site_uid'];
                    $data['has_webhook_suggestion'] = true;
                    $this->log->write('HolestPay: Found webhook suggestion - Environment: ' . $query->row['environment'] . ', Merchant UID: ' . $query->row['merchant_site_uid']);
                }
            } catch (Exception $e) {
                // Table might not exist yet
                $this->log->write('HolestPay: Could not check for webhook suggestions: ' . $e->getMessage());
            }
        }
        $data['payment_holestpay_sort_order'] = $this->getParameterValue('sort_order', '1');
        $data['payment_holestpay_geo_zone_id'] = $this->getParameterValue('geo_zone_id', '0');
        $data['payment_holestpay_order_status_id'] = $this->getParameterValue('order_status_id', '5');
        $data['payment_holestpay_order_status_failed_id'] = $this->getParameterValue('order_status_failed_id', '7');

        // Webhook URL for user to configure in HolestPay panel
        // CRITICAL: Webhook must point to catalog (frontend), not admin
        $catalog_url = $this->config->get('config_ssl') ? 
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTPS_SERVER) :
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTP_SERVER);
        $data['webhook_url'] = $catalog_url . 'extension/holestpay/holestpay_webhook.php';
        
        // Load models for dropdowns
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        // Environment options
        $data['environments'] = array(
            'sandbox' => $this->language->get('text_sandbox'),
            'production' => $this->language->get('text_production')
        );
        
       
        
        // Base URLs for JavaScript
        $data['base'] = HTTP_SERVER;
        $data['catalog'] = $this->config->get('config_ssl') ? 
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTPS_SERVER) :
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTP_SERVER);
        
        // JavaScript file URLs - use the current admin server path
       // $data['holestpay_admin_js'] = rtrim($data['catalog'],'/') .'/extension/holestpay/admin/view/javascript/holestpay-admin.js';


            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            // Auto-correct permissions for HolestPay module
            $this->correctHolestPayPermissions();
            
            $this->response->setOutput($this->load->view($this->getTemplatePath(), $data));
            
        } catch (Throwable $e) {
            $this->log->write('HolestPay Admin Index Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Display generic error page to user
            $data['error_warning'] = 'An error occurred while loading the HolestPay configuration. Please check the error logs.';
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view($this->getTemplatePath(), $data));
        }
    }
    
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'payment/holestpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (!$this->request->post['payment_holestpay_merchant_site_uid']) {
            $this->error['merchant_site_uid'] = $this->language->get('error_merchant_site_uid');
        }
        
        if (!$this->request->post['payment_holestpay_secret_key']) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }
        
        return !$this->error;
    }
    
    public function install() {
        try {
            $this->load->model('payment/holestpay');
            $this->model_payment_holestpay->install();
        } catch (Throwable $e) {
            $this->log->write('HolestPay Install Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Don't throw - let OpenCart handle gracefully
        }
    }
    
    public function uninstall() {
        try {
            $this->load->model('payment/holestpay');
            $this->model_payment_holestpay->uninstall();
        } catch (Throwable $e) {
            $this->log->write('HolestPay Uninstall Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Don't throw - module must be uninstallable even with errors
        }
    }
    

    
    public function getConfig() {
        try {
            $this->response->addHeader('Content-Type: application/json');
            
            $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['environment']) || !isset($input['merchant_site_uid'])) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Missing parameters']));
            return;
        }
        
        $environment = $input['environment'];
        $merchant_site_uid = $input['merchant_site_uid'];
        
        if (empty($environment) || empty($merchant_site_uid)) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Empty parameters']));
            return;
        }
        
        // Query the holestpay_config table for existing configuration
        $query = $this->db->query("SELECT `config_data` FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if ($query->num_rows > 0) {
            $config_data = json_decode($query->row['config_data'], true);
            $this->response->setOutput(json_encode([
                'success' => true, 
                'config' => $config_data
            ]));
        } else {
            $this->response->setOutput(json_encode([
                'success' => false, 
                'error' => 'No configuration found'
            ]));
        }
        
        } catch (Throwable $e) {
            $this->log->write('HolestPay getConfig Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->response->setOutput(json_encode([
                'success' => false, 
                'error' => 'Configuration retrieval failed'
            ]));
        }
    }
    
    private function setHolestPayCSPHeaders() {
        // CRITICAL: CSP headers for HolestPay admin integration
        $environment = $this->getParameterValue('environment', 'sandbox');
        
        // Determine HolestPay domains based on environment
        $hpay_domain = ($environment === 'production') ? 'pay.holest.com' : 'sandbox.pay.holest.com';
        
        // CSP policy allowing:
        // - eval for HolestPay admin scripts
        // - iframe loading from HolestPay domains
        // - script loading from HolestPay domains
        $csp_policy = "default-src 'self'; " .
                     "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://{$hpay_domain}; " .
                     "frame-src 'self' https://{$hpay_domain}; " .
                     "connect-src 'self' https://{$hpay_domain}; " .
                     "img-src 'self' data: https://{$hpay_domain}; " .
                     "style-src * 'unsafe-inline'; " .
                     "font-src 'self' https://fonts.gstatic.com;";
        
        // Set CSP headers
        $this->response->addHeader('Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-WebKit-CSP: ' . $csp_policy);
        
        // Additional security headers for iframe embedding
        $this->response->addHeader('X-Frame-Options: SAMEORIGIN');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        
        // Log CSP policy for debugging
        error_log("HolestPay Admin CSP Policy set for domain: {$hpay_domain}");
    }
    
    public function getOrderData() {
        $this->response->addHeader('Content-Type: application/json');
        
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        
        if (!$order_id) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order ID required']));
            return;
        }
        
        // Get HolestPay order data
        $query = $this->db->query("SELECT `hpay_uid`, `hpay_status`, `hpay_data` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        if ($query->num_rows && ($query->row['hpay_uid'] || $query->row['hpay_status'])) {
            $hpay_data = json_decode($query->row['hpay_data'], true) ?: array();
            
            $order_data = array(
                'hpay_uid' => $query->row['hpay_uid'],
                'hpay_status' => $query->row['hpay_status'],
                'last_updated' => isset($hpay_data['last_updated']) ? $hpay_data['last_updated'] : '',
                'payment_status' => isset($hpay_data['payment_status']) ? $hpay_data['payment_status'] : '',
                'transaction_user_info' => isset($hpay_data['transaction_user_info']) ? $hpay_data['transaction_user_info'] : array(),
                'fiscal_html' => isset($hpay_data['fiscal_html']) ? $hpay_data['fiscal_html'] : '',
                'shipping_html' => isset($hpay_data['shipping_html']) ? $hpay_data['shipping_html'] : ''
            );
            
            $this->response->setOutput(json_encode([
                'success' => true, 
                'order_data' => $order_data
            ]));
        } else {
            $this->response->setOutput(json_encode([
                'success' => false, 
                'error' => 'No HolestPay data found for this order'
            ]));
        }
    }
    
    public function orderStoreApiCall() {
        // CRITICAL: Send order_store API call to HolestPay when admin changes order details
        $this->response->addHeader('Content-Type: application/json');
        
        $order_id = isset($this->request->post['order_id']) ? (int)$this->request->post['order_id'] : 0;
        $with_status = isset($this->request->post['with_status']) ? $this->request->post['with_status'] : null;
        $force = isset($this->request->post['force']) ? (bool)$this->request->post['force'] : false; // Manual button
        
        if (!$order_id) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order ID required']));
            return;
        }
        
        // CRITICAL: Check if we're in webhook processing mode to prevent infinite loops
        if ($this->isWebhookProcessing() && !$force) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Cannot call order_store during webhook processing']));
            return;
        }
        
        // Load order data
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);
        
        if (!$order_info) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order not found']));
            return;
        }
        
        // Check if order_store should be called (like WooCommerce sample)
        if (!$force && !$this->shouldStoreOrder($order_id, $order_info)) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order does not meet criteria for store operation']));
            return;
        }
        
        // Send order_store API call to HolestPay
        $result = $this->sendOrderStoreToHolestPay($order_id, $order_info, $with_status);
        
        if ($result['success']) {
            $this->response->setOutput(json_encode(['success' => true, 'message' => 'Order data sent to HolestPay successfully']));
        } else {
            $this->response->setOutput(json_encode(['success' => false, 'error' => $result['error']]));
        }
    }
    
    // Check if order should be stored to HolestPay (like WooCommerce sample)
    private function shouldStoreOrder($order_id, $order_info) {
        // Get HolestPay order data
        $hpay_query = $this->db->query("SELECT `hpay_uid`, `hpay_status` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        $hpay_status = $hpay_query->num_rows ? $hpay_query->row['hpay_status'] : '';
        $is_holestpay_order = (strpos($order_info['payment_code'], 'holestpay_') !== false);
        
        // If HPay Status field is not set and it's not a HolestPay payment method
        if (!$hpay_status && !$is_holestpay_order) {
            // Check if there are enabled fiscal methods
            return $this->hasEnabledFiscalMethods();
        }
        
        // If HPay Status field is set, always allow store
        if ($hpay_status) {
            return true;
        }
        
        // If it's a HolestPay order, always allow store
        if ($is_holestpay_order) {
            return true;
        }
        
        return false;
    }
    
    // Check if there are enabled fiscal methods (like WooCommerce sample)
    private function hasEnabledFiscalMethods() {
        $environment = $this->getParameterValue('environment', 'sandbox');
        $merchant_site_uid = $this->getParameterValue('merchant_site_uid', '');
        
        if (!$merchant_site_uid) {
            return false;
        }
        
        // Get HolestPay configuration
        $config_query = $this->db->query("SELECT `config_data` FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if (!$config_query->num_rows) {
            return false;
        }
        
        $config_data = json_decode($config_query->row['config_data'], true);
        
        // Check fiscal methods (like WooCommerce sample)
        if (isset($config_data['fiscal']) && is_array($config_data['fiscal'])) {
            foreach ($config_data['fiscal'] as $fiscal_method) {
                if (isset($fiscal_method['Enabled']) && $fiscal_method['Enabled']) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function isWebhookProcessing() {
        // Check if we're currently processing a webhook or hpay_forwarded_payment_response
        return isset($_SESSION['holestpay_webhook_processing']) && $_SESSION['holestpay_webhook_processing'] === true;
    }
    
    private function sendOrderStoreToHolestPay($order_id, $order_info, $with_status = null) {
        try {
            $environment = $this->getParameterValue('environment', 'sandbox');
            $merchant_site_uid = $this->getParameterValue('merchant_site_uid', '');
            $secret_key = $this->getParameterValue('secret_key', '');
            
            if (!$merchant_site_uid || !$secret_key) {
                return ['success' => false, 'error' => 'HolestPay configuration incomplete'];
            }
            
            // Get or generate HPay UID
            $hpay_query = $this->db->query("SELECT `hpay_uid` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
            $hpay_uid = ($hpay_query->num_rows && $hpay_query->row['hpay_uid']) ? $hpay_query->row['hpay_uid'] : $order_id;
            
            // Prepare order data for HolestPay (like WooCommerce sample)
            $order_data = $this->prepareOrderDataForHolestPay($order_info);
            
            // Add status if specified (like WooCommerce $with_status parameter)
            if ($with_status) {
                $order_data['status'] = $with_status;
            } else {
                // Auto-determine status based on order state (like WooCommerce sample)
                $status = $this->determineOrderStatus($order_info);
                if ($status) {
                    $order_data['status'] = $status;
                }
            }
            
            // HolestPay API URL (store endpoint)
            $api_url = ($environment === 'production') 
                ? 'https://pay.holest.com/clientpay/store' 
                : 'https://sandbox.pay.holest.com/clientpay/store';
            
            // Prepare API request (like WooCommerce sample)
            $request_data = array(
                'merchant_site_uid' => $merchant_site_uid,
                'order_uid' => $hpay_uid,
                'order_amount' => number_format($order_info['total'], 2, '.', ''),
                'order_currency' => $order_info['currency_code'],
                'order_data' => $order_data,
                'timestamp' => time()
            );
            
            // Sign the request
            $signature = $this->signOrderStoreRequest($request_data, $secret_key);
            $request_data['verificationhash'] = $signature;
            
            // Send API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request_data' => $request_data)));
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
                error_log("HolestPay: order_store API call successful for order {$order_id}");
                return ['success' => true, 'response' => $response_data];
            } else {
                $error = isset($response_data['error']) ? $response_data['error'] : 'Unknown API error';
                error_log("HolestPay: order_store API call failed for order {$order_id}: {$error}");
                return ['success' => false, 'error' => $error];
            }
            
        } catch (Exception $e) {
            error_log("HolestPay: Exception in order_store API call for order {$order_id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Determine order status for HolestPay (like WooCommerce sample)
    private function determineOrderStatus($order_info) {
        $order_status_id = $order_info['order_status_id'];
        $payment_code = $order_info['payment_code'];
        
        // Only set status for non-HolestPay orders or specific cases
        if (strpos($payment_code, 'holestpay_') === false) {
            // Map OpenCart statuses to HolestPay statuses (like WooCommerce sample)
            $status_mapping = array(
                '1' => 'PAYMENT:PENDING',        // Pending
                '2' => 'PAYMENT:PROCESSING',     // Processing  
                '3' => 'PAYMENT:SHIPPED',        // Shipped
                '5' => 'PAYMENT:PAID',           // Complete
                '7' => 'PAYMENT:CANCELED',       // Canceled
                '8' => 'PAYMENT:DENIED',         // Denied
                '9' => 'PAYMENT:CANCELED',       // Canceled Reversal
                '10' => 'PAYMENT:FAILED',        // Failed
                '11' => 'PAYMENT:REFUNDED',      // Refunded
                '12' => 'PAYMENT:REVERSED',      // Reversed
                '13' => 'PAYMENT:CHARGEBACK',    // Chargeback
                '14' => 'PAYMENT:EXPIRED',       // Expired
                '15' => 'PAYMENT:PROCESSED',     // Processed
                '16' => 'PAYMENT:VOIDED'         // Voided
            );
            
            return isset($status_mapping[$order_status_id]) ? $status_mapping[$order_status_id] : null;
        }
        
        return null; // Don't set status for HolestPay orders unless explicitly specified
    }
    
    private function prepareOrderDataForHolestPay($order_info) {
        // Load additional order data
        $this->load->model('sale/order');
        
        // Get order products
        $order_products = $this->model_sale_order->getOrderProducts($order_info['order_id']);
        
        // Prepare order items
        $items = array();
        foreach ($order_products as $product) {
            $items[] = array(
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'total' => $product['total'],
                'tax' => $product['tax']
            );
        }
        
        return array(
            'order_id' => $order_info['order_id'],
            'order_status_id' => $order_info['order_status_id'],
            'total' => $order_info['total'],
            'currency_code' => $order_info['currency_code'],
            'currency_value' => $order_info['currency_value'],
            'items' => $items,
            'billing_address' => array(
                'firstname' => $order_info['payment_firstname'],
                'lastname' => $order_info['payment_lastname'],
                'company' => $order_info['payment_company'],
                'address_1' => $order_info['payment_address_1'],
                'address_2' => $order_info['payment_address_2'],
                'city' => $order_info['payment_city'],
                'postcode' => $order_info['payment_postcode'],
                'country' => $order_info['payment_country'],
                'zone' => $order_info['payment_zone']
            ),
            'shipping_address' => array(
                'firstname' => $order_info['shipping_firstname'],
                'lastname' => $order_info['shipping_lastname'],
                'company' => $order_info['shipping_company'],
                'address_1' => $order_info['shipping_address_1'],
                'address_2' => $order_info['shipping_address_2'],
                'city' => $order_info['shipping_city'],
                'postcode' => $order_info['shipping_postcode'],
                'country' => $order_info['shipping_country'],
                'zone' => $order_info['shipping_zone']
            ),
            'shipping_method' => $order_info['shipping_method'],
            'payment_method' => $order_info['payment_method'],
            'date_added' => $order_info['date_added'],
            'date_modified' => $order_info['date_modified']
        );
    }
    
    private function signOrderStoreRequest($request_data, $secret_key) {
        // Create signature for order_store API request
        $string_to_sign = $request_data['merchant_site_uid'] . 
                         $request_data['order_uid'] . 
                         $request_data['timestamp'] . 
                         json_encode($request_data['order_data']) . 
                         $secret_key;
        
        return hash('sha256', $string_to_sign);
    }
    
    private function getTemplatePath() {
        // Check if we're being accessed via extension route
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        
        if (strpos($route, 'extension/holestpay/payment/holestpay') !== false) {
            // Extension route - ensure extension language is loaded
            $this->ensureExtensionLanguageLoaded();
            return 'extension/holestpay/payment/holestpay';
        } else {
            // Standard route - use payment template path
            return 'payment/holestpay';
        }
    }
    
    private function ensureExtensionLanguageLoaded() {
        // Manually load extension language variables if not already loaded
        if (!isset($this->language->data['text_store_to_hpay'])) {
            $language_file = DIR_LANGUAGE . $this->config->get('config_language') . '/extension/holestpay/payment/holestpay.php';
            if (file_exists($language_file)) {
                // Load the language file manually
                $_ = array();
                include $language_file;
                // Merge with existing language data
                $this->language->data = array_merge($this->language->data, $_);
            }
        }
    }
    
    private function correctHolestPayPermissions() {
        try {
            // Get current user's group ID
            $user_group_id = $this->user->getGroupId();
            
            if (!$user_group_id) {
                return; // No user group found
            }
            
            // Check if user group already has payment/holestpay permission
            $query = $this->db->query("SELECT permission FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");
            
            if ($query->num_rows) {
                $permissions = json_decode($query->row['permission'], true);
                
                // Check if both access and modify permissions exist for payment/holestpay
                $has_access = isset($permissions['access']) && in_array('payment/holestpay', $permissions['access']);
                $has_modify = isset($permissions['modify']) && in_array('payment/holestpay', $permissions['modify']);
                
                if (!$has_access || !$has_modify) {
                    // Add missing permissions
                    if (!$has_access) {
                        if (!isset($permissions['access'])) {
                            $permissions['access'] = array();
                        }
                        $permissions['access'][] = 'payment/holestpay';
                    }
                    
                    if (!$has_modify) {
                        if (!isset($permissions['modify'])) {
                            $permissions['modify'] = array();
                        }
                        $permissions['modify'][] = 'payment/holestpay';
                    }
                    
                    // Update the database
                    $this->db->query("UPDATE " . DB_PREFIX . "user_group SET permission = '" . $this->db->escape(json_encode($permissions)) . "' WHERE user_group_id = '" . (int)$user_group_id . "'");
                    
                    $this->log->write('HolestPay: Auto-corrected permissions for user group ID: ' . $user_group_id);
                }
            }
        } catch (Throwable $e) {
            $this->log->write('HolestPay: Failed to auto-correct permissions: ' . $e->getMessage());
        }
    }
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Admin\Controller\Extension\Holestpay\Payment\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/payment/holestpay.php";
}

if(!class_exists('\Opencart\Admin\Model\Payment\Holestpay')) {
    require_once __DIR__ . "/../../model/payment/holestpay.php";
}

