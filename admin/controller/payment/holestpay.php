<?php
namespace Opencart\Admin\Controller\Payment;

class Holestpay extends \Opencart\System\Engine\Controller {
    private $error = array();
	
	public function __construct($registry) {
        parent::__construct($registry); // Call the parent constructor to initialize essential components
        // Your custom initialization code here, e.g., loading language files
        $this->document->addScript( "../extension/holestpay/admin/view/javascript/holestpay-admin.js");
    }
    
    private function getParameterValue($name, $default = null) {
        // Simple OpenCart config getter with fallback to default
        $value = $this->config->get('payment_holestpay_' . $name);
        return ($value !== null && $value !== '') ? $value : $default;
    }
    
    public function index() {
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


            if(isset($this->request->get['action'])){
                if($this->request->get['action'] == "orderStoreApiCall"){
                    return $this->orderStoreApiCall();
                }else if($this->request->get['action'] == "processManualCharge"){
                    return $this->processManualCharge();
                }
            }
            
            // CRITICAL: Auto-sync extension files when entering configuration
            $sync_result = $this->autoSyncExtensionFiles();
        
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
            $data['payment_holestpay_insert_footer_logotypes'] = $this->getParameterValue('insert_footer_logotypes', '0');

            // Webhook URL for user to configure in HolestPay panel
            // CRITICAL: Webhook must point to catalog (frontend), not admin
            $catalog_url = $this->config->get('config_ssl') ? 
                str_replace(basename(DIR_APPLICATION) . '/', '', HTTPS_SERVER) :
                str_replace(basename(DIR_APPLICATION) . '/', '', HTTP_SERVER);
            $data['webhook_url'] = $catalog_url . 'index.php?route=extension/holestpay/payment/holestpay';
            
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

            // Add sync result to template data
            $data['sync_result'] = $sync_result;
            
            // Fetch HolestPayAdmin data for JavaScript
            $data['holestpay_admin_data'] = $this->fetchHolestPayAdminData(true);

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
            $this->load->model('extension/holestpay/payment/holestpay');
            $this->model_payment_holestpay->install();
        } catch (Throwable $e) {
            $this->log->write('HolestPay Install Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Don't throw - let OpenCart handle gracefully
        }
    }
    
    public function uninstall() {
        try {
            $this->load->model('extension/holestpay/payment/holestpay');
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
    
    
    /**
     * Generate HolestPay request structure (same as frontend generateHPayRequest)
     */
    private function generateHPayRequest($order_info, $payment_method_id = '', $shipping_method_id = '', $vault_token_uid = '', $cof = 'none') {
        $this->load->model('extension/holestpay/payment/holestpay');
        
        // Generate order items (admin version - always include shipping)
        $order_items = $this->generateOrderItems($order_info);
        
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
            'vault_token_uid' => $vault_token_uid
        );
        
        return $request;
    }
    
    private function parseRequestData() {
        // Check if request is JSON
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($content_type, 'application/json') !== false) {
            $json_input = file_get_contents('php://input');
            $data = json_decode($json_input, true);
            return $data ?: [];
        }
        
        // Otherwise return POST data
        return $this->request->post;
    }
    
    private function generateOrderItems($order_info) {
        // Generate order items for HolestPay (admin version - always include shipping)
        $this->load->model('sale/order');
        $this->load->model('extension/holestpay/payment/holestpay');
        
        $order_items = [];
        
        // Get order products
        $order_products = $this->model_sale_order->getProducts($order_info['order_id']);
        foreach ($order_products as $product) {
            $order_items[] = [
                'name' => $product['name'],
                'quantity' => (int)$product['quantity'],
                'price' => (float)$product['price'],
                'total' => (float)$product['total']
            ];
        }
        
        // Get order totals (including shipping, tax, discount, etc.)
        $order_totals = $this->model_sale_order->getTotals($order_info['order_id']);
        foreach ($order_totals as $total) {
            // Skip subtotal and total as they're handled separately
            if (in_array($total['code'], ['subtotal', 'total'])) {
                continue;
            }
            
            // Include shipping, tax, discount, coupon, etc.
            $order_items[] = [
                'name' => $total['title'],
                'quantity' => 1,
                'price' => (float)$total['value'],
                'total' => (float)$total['value']
            ];
        }
        
        return $order_items;
    }
    
    public function orderStoreApiCall() {
        // CRITICAL: Send order_store API call to HolestPay when admin changes order details
        $this->response->addHeader('Content-Type: application/json');
        
        // Parse request data (POST or JSON)
        $request_data = $this->parseRequestData();
        
        $order_id = isset($request_data['order_id']) ? (int)$request_data['order_id'] : 0;
        $with_status = isset($request_data['with_status']) ? $request_data['with_status'] : null;
        $force = isset($request_data['force']) ? (bool)$request_data['force'] : false; // Manual button
        
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
        
        // Generate HolestPay request structure
        $hpay_request = $this->generateHPayRequest($order_info);
        
        // Add signature
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        $request_data["merchant_site_uid"] = $this->config->get('payment_holestpay_merchant_site_uid');
       
        
        // Send order_store API call to HolestPay
        $result = $this->sendOrderStoreToHolestPay($order_id, $order_info, $with_status, $hpay_request);
        
        if ($result['success']) {
            $this->response->setOutput(json_encode(['success' => true, 'message' => 'Order data sent to HolestPay successfully']));
        } else {
            $this->response->setOutput(json_encode(['success' => false, 'error' => $result['error']]));
        }
    }
    
    public function processManualCharge() {
        // Process manual charge using saved payment method
        $this->response->addHeader('Content-Type: application/json');
        
        // Parse request data (POST or JSON)
        $request_data = $this->parseRequestData();
        
        $order_id = isset($request_data['order_id']) ? (int)$request_data['order_id'] : 0;
        $vault_token_uid = isset($request_data['vault_token_uid']) ? $request_data['vault_token_uid'] : '';
        $payment_method_id = isset($request_data['payment_method_id']) ? $request_data['payment_method_id'] : '';
        $amount = isset($request_data['amount']) ? (float)$request_data['amount'] : 0;
        
        if (!$order_id) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order ID required']));
            return;
        }
        
        if (!$vault_token_uid) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Vault token UID required']));
            return;
        }
        
        if (!$payment_method_id) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Payment method ID required']));
            return;
        }
        
        // Load order data
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);
        
        if (!$order_info) {
            $this->response->setOutput(json_encode(['success' => false, 'error' => 'Order not found']));
            return;
        }
        
        // Use order total if amount not specified
        if ($amount <= 0) {
            $amount = $order_info['total'];
        }
        
        // Generate HolestPay request structure with vault token
        $hpay_request = $this->generateHPayRequest($order_info, $payment_method_id, '', $vault_token_uid);
        
        // Override amount if specified
        if ($amount > 0) {
            $hpay_request['order_amount'] = $amount;
        }
        
        // Add signature
        $secret_key = $this->config->get('payment_holestpay_secret_key');
        $request_data["merchant_site_uid"] = $this->config->get('payment_holestpay_merchant_site_uid');
       
        
        // Send charge API call to HolestPay
        $result = $this->sendChargeToHolestPay($order_id, $order_info, $hpay_request);
        
        if ($result['success']) {
            $this->response->setOutput(json_encode(['success' => true, 'message' => 'Manual charge processed successfully']));
        } else {
            $this->response->setOutput(json_encode(['success' => false, 'error' => $result['error']]));
        }
    }
    
    private function sendChargeToHolestPay($order_id, $order_info, $hpay_request) {
        try {
            $environment = $this->getParameterValue('environment', 'sandbox');
            $merchant_site_uid = $this->getParameterValue('merchant_site_uid', '');
            $secret_key = $this->getParameterValue('secret_key', '');
            
            if (!$merchant_site_uid || !$secret_key) {
                return ['success' => false, 'error' => 'HolestPay configuration incomplete'];
            }
            
            // HolestPay API URL (charge endpoint)
            $api_url = ($environment === 'production') 
                ? 'https://pay.holest.com/clientpay/charge' 
                : 'https://sandbox.pay.holest.com/clientpay/charge';
            
            $hpay_request['verificationhash'] = $this->generateSignature($hpay_request, $secret_key);    
            // Send API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("request_data" => $hpay_request)));
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
            
            if ($http_code >= 200 && $http_code < 300) {
                // Success - update order with HolestPay data
                if (isset($response_data['transaction_uid'])) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
                        `hpay_uid` = '" . $this->db->escape($response_data['transaction_uid']) . "',
                        `hpay_status` = '" . $this->db->escape($response_data['status'] ?? '') . "',
                        `date_modified` = NOW() 
                        WHERE `order_id` = '" . (int)$order_id . "'");
                }
                
                return ['success' => true, 'response' => $response_data];
            } else {
                return ['success' => false, 'error' => 'HolestPay API error: ' . ($response_data['error'] ?? 'Unknown error')];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
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
    
    private function sendOrderStoreToHolestPay($order_id, $order_info, $with_status = null, $hpay_request = null) {
        try {
            $environment = $this->getParameterValue('environment', 'sandbox');
            $merchant_site_uid = $this->getParameterValue('merchant_site_uid', '');
            $secret_key = $this->getParameterValue('secret_key', '');
            
            if (!$merchant_site_uid || !$secret_key) {
                return ['success' => false, 'error' => 'HolestPay configuration incomplete'];
            }
            
            // Use provided hpay_request or generate one
            if (!$hpay_request) {
                $hpay_request = $this->generateHPayRequest($order_info);
                // Add signature
                $request_data["merchant_site_uid"] = $this->config->get('payment_holestpay_merchant_site_uid');
            }
            
            // Add status if specified
            if ($with_status) {
                $hpay_request['status'] = $with_status;
            } else {
                // Auto-determine status based on order state
                $status = $this->determineOrderStatus($order_info);
                if ($status) {
                    $hpay_request['status'] = $status;
                }
            }
            
            // HolestPay API URL (store endpoint)
            $api_url = ($environment === 'production') 
                ? 'https://pay.holest.com/clientpay/store' 
                : 'https://sandbox.pay.holest.com/clientpay/store';
            
            // Send API request
            $hpay_request['verificationhash'] = $this->generateSignature($hpay_request, $secret_key);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("request_data" => $hpay_request)));
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
        $order_products = $this->model_sale_order->getProducts($order_info['order_id']);
        
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
    
    private function generateSignature($data, $secret_key) {
        // Generate request signature for HolestPay - matches Node.js implementation
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        // Format amount to 8 decimal places like in Node.js
        $amt_for_signature = number_format((float)(isset($data['order_amount']) ? $data['order_amount'] : 0), 8, '.', '');
        
        // Build concatenated string in the exact order from Node.js sample
        // Handle missing properties by checking if key exists and has value
        $cstr = trim(isset($data['transaction_uid']) ? (string)$data['transaction_uid'] : '') . '|';
        $cstr .= trim(isset($data['status']) ? (string)$data['status'] : '') . '|';
        $cstr .= trim(isset($data['order_uid']) ? (string)$data['order_uid'] : '') . '|';
        $cstr .= trim($amt_for_signature) . '|';
        $cstr .= trim(isset($data['order_currency']) ? (string)$data['order_currency'] : '') . '|';
        $cstr .= trim(isset($data['vault_token_uid']) ? (string)$data['vault_token_uid'] : '') . '|';
        $cstr .= trim(isset($data['subscription_uid']) ? (string)$data['subscription_uid'] : '');
        $cstr .= trim(isset($data['rand']) ? (string)$data['rand'] : '');
        
        // First MD5 hash of concatenated string + merchant_site_uid
        $cstrmd5 = md5($cstr . $merchant_site_uid);
        
        // Then SHA512 hash of MD5 result + secret_key
        $sha512calc = hash('sha512', $cstrmd5 . $secret_key);
        
        // Return lowercase hex as in Node.js
        return strtolower($sha512calc);
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
    
    /**
     * Auto-sync extension files when entering configuration
     * Checks and copies template, language, and JavaScript files if needed
     * Also checks for newer versions based on file modification time
     */
    private function autoSyncExtensionFiles() {
        $result = array(
            'success' => true,
            'files_synced' => 0,
            'files_updated' => 0,
            'files_skipped' => 0,
            'errors' => array(),
            'details' => array()
        );
        
        try {
            // Define file mappings: source => destination
            $file_mappings = $this->getExtensionFileMapping();
            
            foreach ($file_mappings as $source => $destination) {
                $sync_result = $this->syncSingleFile($source, $destination);
                
                if ($sync_result['success']) {
                    if ($sync_result['action'] === 'copied') {
                        $result['files_synced']++;
                        $result['details'][] = "✅ Copied: " . basename($destination);
                    } elseif ($sync_result['action'] === 'updated') {
                        $result['files_updated']++;
                        $result['details'][] = "🔄 Updated: " . basename($destination);
                    } else {
                        $result['files_skipped']++;
                    }
                } else {
                    $result['errors'][] = $sync_result['error'];
                    $result['details'][] = "❌ Error: " . basename($destination) . " - " . $sync_result['error'];
                }
            }
            
            // Log the sync operation
            $this->log->write('HolestPay Auto-Sync: ' . $result['files_synced'] . ' copied, ' . 
                             $result['files_updated'] . ' updated, ' . $result['files_skipped'] . ' skipped, ' . 
                             count($result['errors']) . ' errors');
            
            if (count($result['errors']) > 0) {
                $result['success'] = false;
            }
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Auto-sync failed: ' . $e->getMessage();
            $this->log->write('HolestPay Auto-Sync Error: ' . $e->getMessage());
        }
        
        // Add comprehensive error summary if there are permission issues
        if (count($result['errors']) > 0) {
            $permission_errors = 0;
            foreach ($result['errors'] as $error) {
                if (strpos($error, 'Permission denied') !== false) {
                    $permission_errors++;
                }
            }
            
            if ($permission_errors > 0) {
                $result['permission_help'] = $this->generatePermissionHelp();
            }
        }
        
        return $result;
    }
    
    /**
     * Get file mapping for extension files that need to be synced
     */
    private function getExtensionFileMapping() {
        return array(
            // Template files
            DIR_EXTENSION . 'holestpay/catalog/view/template/payment/holestpay.twig' => 
                DIR_CATALOG . 'view/template/payment/holestpay.twig',
            DIR_EXTENSION . 'holestpay/catalog/view/template/payment/holestpay_error.twig' => 
                DIR_CATALOG . 'view/template/payment/holestpay_error.twig',
            DIR_EXTENSION . 'holestpay/catalog/view/template/payment/holestpay_order_result.twig' => 
                DIR_CATALOG . 'view/template/payment/holestpay_order_result.twig',
            DIR_EXTENSION . 'holestpay/admin/view/template/payment/holestpay.twig' => 
                DIR_APPLICATION . 'view/template/payment/holestpay.twig',
                
            // JavaScript files
            DIR_EXTENSION . 'holestpay/catalog/view/javascript/holestpay-checkout.js' => 
                DIR_CATALOG . 'view/javascript/holestpay-checkout.js',
            DIR_EXTENSION . 'holestpay/admin/view/javascript/holestpay-admin.js' => 
                DIR_APPLICATION . 'view/javascript/holestpay-admin.js',
                
            // Language files - English
            DIR_EXTENSION . 'holestpay/catalog/language/en-gb/payment/holestpay.php' => 
                DIR_CATALOG . 'language/en-gb/payment/holestpay.php',
            DIR_EXTENSION . 'holestpay/catalog/language/en-gb/shipping/holestpay.php' => 
                DIR_CATALOG . 'language/en-gb/shipping/holestpay.php',
            DIR_EXTENSION . 'holestpay/admin/language/en-gb/payment/holestpay.php' => 
                DIR_APPLICATION . 'language/en-gb/payment/holestpay.php',
                
            // Language files - Serbian Cyrillic
            DIR_EXTENSION . 'holestpay/catalog/language/sr-rs/payment/holestpay.php' => 
                DIR_CATALOG . 'language/sr-rs/payment/holestpay.php',
            DIR_EXTENSION . 'holestpay/catalog/language/sr-rs/shipping/holestpay.php' => 
                DIR_CATALOG . 'language/sr-rs/shipping/holestpay.php',
                
            // Language files - Serbian Latin
            DIR_EXTENSION . 'holestpay/catalog/language/sr-yu/payment/holestpay.php' => 
                DIR_CATALOG . 'language/sr-yu/payment/holestpay.php',
            DIR_EXTENSION . 'holestpay/catalog/language/sr-yu/shipping/holestpay.php' => 
                DIR_CATALOG . 'language/sr-yu/shipping/holestpay.php',
                
            // Language files - Macedonian
            DIR_EXTENSION . 'holestpay/catalog/language/mk-mk/payment/holestpay.php' => 
                DIR_CATALOG . 'language/mk-mk/payment/holestpay.php',
            DIR_EXTENSION . 'holestpay/catalog/language/mk-mk/shipping/holestpay.php' => 
                DIR_CATALOG . 'language/mk-mk/shipping/holestpay.php'
        );
    }
    
    /**
     * Sync a single file from source to destination
     * Checks modification time to determine if update is needed
     */
     private function syncSingleFile($source, $destination) {
        $result = array('success' => false, 'action' => 'none', 'error' => '');
        
        try {
            // Check if source file exists
            if (!file_exists($source)) {
                $result['action'] = 'skipped';
                $result['success'] = true; // Not an error, just no source file
                return $result;
            }
            
            // Create destination directory if it doesn't exist
            $dest_dir = dirname($destination);
            if (!is_dir($dest_dir)) {
                if (!mkdir($dest_dir, 0755, true)) {
                    $result['error'] = $this->getPermissionErrorMessage($dest_dir, 'create directory');
                    return $result;
                }
            }
            
            // Check if destination directory is writable
            if (!is_writable($dest_dir)) {
                $result['error'] = $this->getPermissionErrorMessage($dest_dir, 'write to directory');
                return $result;
            }
            
            $should_copy = false;
            $action = 'copied';
            
            // Check if destination exists
            if (file_exists($destination)) {
                // Check if destination file is writable
                if (!is_writable($destination)) {
                    $result['error'] = $this->getPermissionErrorMessage($destination, 'overwrite file');
                    return $result;
                }
                
                // Compare modification times
                $source_time = filemtime($source);
                $dest_time = filemtime($destination);
                
                if ($source_time > $dest_time) {
                    $should_copy = true;
                    $action = 'updated';
                } else {
                    // Destination is up to date
                    $result['action'] = 'skipped';
                    $result['success'] = true;
                    return $result;
                }
            } else {
                // Destination doesn't exist, copy it
                $should_copy = true;
                $action = 'copied';
            }
            
            if ($should_copy) {
                if (copy($source, $destination)) {
                    $result['success'] = true;
                    $result['action'] = $action;
                    
                    // Preserve source file modification time
                    touch($destination, filemtime($source));
                } else {
                    $result['error'] = $this->getPermissionErrorMessage($destination, 'copy file');
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = 'File operation failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Generate detailed permission error message with actionable instructions
     */
    private function getPermissionErrorMessage($path, $operation) {
        $relative_path = str_replace(DIR_OPENCART, '', $path);
        $parent_dir = dirname($path);
        
        // Check if it's a permission issue
        if (file_exists($parent_dir) && !is_writable($parent_dir)) {
            return "Permission denied: Cannot {$operation} '{$relative_path}'. " .
                   "Please set write permissions (755 or 775) on directory: " . dirname($relative_path);
        }
        
        if (file_exists($path) && !is_writable($path)) {
            return "Permission denied: Cannot {$operation} '{$relative_path}'. " .
                   "Please set write permissions (644 or 664) on file: {$relative_path}";
        }
        
        return "Cannot {$operation} '{$relative_path}'. Check file system permissions.";
    }
    
    /**
     * Generate comprehensive permission help message
     */
    private function generatePermissionHelp() {
        $opencart_root = str_replace(DIR_OPENCART, '', DIR_OPENCART);
        
        return array(
            'title' => 'File Sync Failed - Permission Issues Detected',
            'message' => 'HolestPay extension cannot automatically sync template files due to file system permission restrictions.',
            'instructions' => array(
                'The following directories need write permissions (755 or 775):',
                '• ' . $opencart_root . 'catalog/view/template/payment/',
                '• ' . $opencart_root . 'catalog/view/javascript/',
                '• ' . $opencart_root . 'catalog/language/*/payment/',
                '• ' . $opencart_root . 'admin/view/template/payment/',
                '• ' . $opencart_root . 'admin/view/javascript/',
                '',
                'SSH/Terminal commands:',
                'chmod 755 ' . $opencart_root . 'catalog/view/template/payment/',
                'chmod 755 ' . $opencart_root . 'catalog/view/javascript/',
                'chmod 755 ' . $opencart_root . 'admin/view/template/payment/',
                'chmod 755 ' . $opencart_root . 'admin/view/javascript/',
                '',
                'Alternative: Use the manual fix script provided with the extension.',
                'After fixing permissions, refresh this page to retry auto-sync.'
            )
        );
    }
    
    /**
     * Fetch HolestPayAdmin data for JavaScript
     */
    public function fetchHolestPayAdminData($return_data = false) {
        // Get configuration values
        $environment = $this->getParameterValue('environment', 'sandbox');
        $merchant_site_uid = $this->getParameterValue('merchant_site_uid', '');
        $secret_key = $this->getParameterValue('secret_key', '');
        
        // Get base URLs
        $base_url = HTTP_SERVER;
        $catalog_url = $this->config->get('config_ssl') ? 
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTPS_SERVER) :
            str_replace(basename(DIR_APPLICATION) . '/', '', HTTP_SERVER);
        
        // Get webhook URL
        $webhook_url = $catalog_url . 'index.php?route=extension/holestpay/payment/holestpay';
        
        // Get POS configuration
        $holestpay_pos_config = '{}';
        $has_pos_config = false;
        
        if (!empty($merchant_site_uid) && !empty($environment)) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` WHERE `environment` = '" . $this->db->escape($environment) . "' AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "' ORDER BY `date_modified` DESC LIMIT 1");
            if ($query->num_rows > 0) {
                $config_data = json_decode($query->row['config_data'], true);
                if ($config_data && isset($config_data['POS'])) {
                    $holestpay_pos_config = $query->row['config_data'];
                    $has_pos_config = true;
                }
            }
        }
        
        $pos_config = json_decode($holestpay_pos_config, true);
        
        $admin_data = array(
            'admin_base_url' => $base_url,
            'frontend_base_url' => $catalog_url,
            'site_url' => rtrim($catalog_url, '/'),
            'notify_url' => $webhook_url,
            'plugin_url' => $base_url,
            'ajax_url' => $base_url . 'index.php?route=extension/holestpay/payment/holestpay',
            'language' => $this->config->get('config_language') ?: 'en',
            'hpaylang' => $this->config->get('config_language') ?: 'en',
            'plugin_version' => '1.0.0',
            'settings' => array(
                'environment' => $environment,
                'merchant_site_uid' => $merchant_site_uid
            ),
            'labels' => array(
                'error_saving_settings' => $this->language->get('text_error_saving_settings') ?: 'Error saving settings',
                'disconnect_question' => $this->language->get('text_disconnect_question') ?: 'Disconnect HolestPay?',
                'manage_on_hpay' => $this->language->get('text_manage_on_hpay') ?: 'Manage on HolestPay',
                'yes' => $this->language->get('text_yes') ?: 'Yes',
                'no' => $this->language->get('text_no') ?: 'No',
                'connecting' => $this->language->get('text_connecting') ?: 'Connecting...',
                'connected' => $this->language->get('text_connected') ?: 'Connected',
                'disconnected' => $this->language->get('text_disconnected') ?: 'Disconnected',
                'error_connection' => $this->language->get('text_error_connection') ?: 'Connection Error',
                'save_settings' => $this->language->get('text_save_settings') ?: 'Save Settings',
                'test_connection' => $this->language->get('text_test_connection') ?: 'Test Connection',
                'webhook_url' => $this->language->get('text_webhook_url') ?: 'Webhook URL',
                'copy_webhook_url' => $this->language->get('text_copy_webhook_url') ?: 'Copy Webhook URL',
                'webhook_url_copied' => $this->language->get('text_webhook_url_copied') ?: 'Webhook URL copied to clipboard',
            )
        );
        
        // Add POS configuration if available
        if ($pos_config && isset($pos_config['POS'])) {
            $admin_data['settings'][$environment] = array(
                'environment' => $environment,
                'merchant_site_uid' => $merchant_site_uid,
                'secret_token' => $secret_key
            );
            $admin_data['settings'][$environment . 'POS'] = $pos_config['POS'];
            
            $admin_data['settings']['site_id'] = $pos_config['POS']['HPaySiteId'];
            $admin_data['settings'][$environment]['site_id'] = $pos_config['POS']['HPaySiteId'];
            $admin_data['settings'][$environment . "POS"]['site_id'] = $pos_config['POS']['HPaySiteId'];

            $admin_data['settings']['company_id'] =$pos_config['POS']["company"]['HPayCompanyId'];
            $admin_data['settings'][$environment]['company_id'] = $pos_config['POS']["company"]['HPayCompanyId'];
            $admin_data['settings'][$environment . "POS"]['company_id'] = $pos_config['POS']["company"]['HPayCompanyId'];
            
            
            
        }
        
        if ($return_data) {
            return $admin_data;
        } else {
            // Set JSON headers and output data directly
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Cache-Control: no-cache, no-store, must-revalidate');
            $this->response->addHeader('Pragma: no-cache');
            $this->response->addHeader('Expires: 0');
            $this->response->setOutput(json_encode($admin_data));
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

