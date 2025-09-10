<?php
namespace Opencart\Catalog\Controller\Payment;

ini_set('display_errors', 'On');
ini_set('error_reporting', E_ALL);

if(!class_exists('\Opencart\Catalog\Model\Payment\Holestpay')){
	require_once(__DIR__ . "/../../model/payment/holestpay.php");
}

if(!class_exists('\Opencart\System\Engine\Controller\Holestpay')){
	class Holestpay extends \Opencart\System\Engine\Controller {
		public static $_possible_hpay_pay_statuses = array("SUCCESS","PAID","PAYING","OVERDUE","AWAITING", "REFUNDED", "PARTIALLY-REFUNDED","VOID", "RESERVED", "EXPIRED","CANCELED", "OBLIGATED", "REFUSED");
        public static $_possible_hpay_shipping_packet_statuses = array("PREPARING", "READY", "SUBMITTED", "DELIVERY", "DELIVERED", "ERROR", "RESOLVING", "FAILED", "REVOKED");

		public function index() {
			
			$this->checkFontendScript();
			
			// CRITICAL: Handle webhook calls within index method since OpenCart 4 extension routing 
			// doesn't support method calls like /webhook at the end of extension routes
			if (isset($_GET['topic']) || 
				(isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'webhook') !== false)) {
				return $this->webhook();
			}
			
			// Handle orderUserUrl calls
			if (isset($_GET['order_id'])) {
				return $this->orderUserUrl();
			}
			
			// Handle orderUserUrl calls
			if (isset($_GET['action'])) {
				return $this->userActions();
			}
		
			$this->load->language($this->getLanguagePath());
			$this->load->model('extension/holestpay/payment/holestpay');
			
			// CRITICAL: Set CSP headers for HolestPay integration
			$this->setHolestPayCSPHeaders();
			
			$data['text_loading'] = $this->language->get('text_loading');
			$data['text_please_wait'] = $this->language->get('text_please_wait');
			
			// Check if module is configured
			if (!$this->config->get('payment_holestpay_merchant_site_uid') || 
				!$this->config->get('payment_holestpay_secret_key')) {
				$data['error'] = $this->language->get('error_configuration');
				return $this->load->view($this->getErrorTemplatePath(), $data);
			}
			
			// Get HolestPay configuration and POS data
			$hpay_config = $this->model_extension_holestpay_payment_holestpay->getHolestPayConfig();
			
			// Check if POS configuration exists
			if (empty($hpay_config) || !isset($hpay_config['POS'])) {
				$data['error'] = $this->language->get('error_no_payment_methods');
				return $this->load->view($this->getErrorTemplatePath(), $data);
			}
			
			// Get cart data for HolestPay
			$cart_data = $this->model_extension_holestpay_payment_holestpay->getCartData();
			
			// Get customer vault tokens if logged in
			$vault_tokens = array();
			if ($this->customer->isLogged()) {
				$vault_tokens = $this->model_extension_holestpay_payment_holestpay->getCustomerVaultTokens($this->customer->getId());
			}
			
			// Add vault tokens to cart data for POS
			$cart_data['vault_tokens'] = $vault_tokens;
			$data['cart_data'] = $cart_data;
			$data['environment'] = $this->config->get('payment_holestpay_environment');
			$data['merchant_site_uid'] = $this->config->get('payment_holestpay_merchant_site_uid');
			$data['description'] = $this->config->get('payment_holestpay_description');
			
			// Generate checkout data for HolestPayCheckout JavaScript object
			$data['holestpay_checkout_render'] = $this->holestpay_frontend_js(true);
            return $this->load->view($this->getTemplatePath(), $data);
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

        public function userActions(){
			header('Content-Type: application/json');
            if(isset($_GET['action'])){
                $action = $_GET['action'];
                if($action == "refresh_checkout"){
					echo json_encode($this->getHolestPayCheckout());
                    die;
                }
            }
            return array('error' => 'Invalid action');
        }

        private function getHolestPayCheckout() {
            try {
                // Load required models and language
                $this->load->language($this->getLanguagePath());
                $this->load->model('payment/holestpay');
                
                // Check if module is configured
                if (!$this->config->get('payment_holestpay_merchant_site_uid') || 
                    !$this->config->get('payment_holestpay_secret_key')) {
                    return array('error' => 'HolestPay not configured');
                }
                
                // Get HolestPay configuration and POS data
                $hpay_config = $this->model_payment_holestpay->getHolestPayConfig();
                
                // Check if POS configuration exists
                if (empty($hpay_config) || !isset($hpay_config['POS'])) {
                    return array('error' => 'HolestPay POS configuration not available');
                }
                
                // Get cart data for HolestPay
                $cart_data = $this->model_payment_holestpay->getCartData();

				// Get customer vault tokens if logged in
                $vault_tokens = array();
                if ($this->customer->isLogged()) {
                    $vault_tokens = $this->model_payment_holestpay->getCustomerVaultTokens($this->customer->getId());
                }
                
                // Add vault tokens to cart data for POS
                $cart_data['vault_tokens'] = $vault_tokens;
                
                // Build HolestPayCheckout object
                $environment = $this->config->get('payment_holestpay_environment');
                $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
                
                $hpaylang = $this->config->get('config_language');
                
                if(stripos($hpaylang, 'yu') !== false){    
                    $hpaylang = 'rs-cyr';
                }else if(stripos($hpaylang, 'sr') !== false){
                    $hpaylang = 'rs';
                }else if(stripos($hpaylang, 'mk') !== false){
                    $hpaylang = 'mk';
                }else{
                    $hpaylang = substr($hpaylang, 0, 2);
                }

                if(!$hpaylang){
                    $hpaylang = 'rs';
                }

				$logotypes_setup = null;

				if($this->config->get('payment_holestpay_insert_footer_logotypes') && isset($hpay_config['POS']) && isset($hpay_config['POS']['pos_parameters'])){
					$logotypes_setup = array();
					if(isset($hpay_config['POS']['pos_parameters']['Logotypes Card Images'])){
						$logotypes_setup['Logotypes Card Images'] = $hpay_config['POS']['pos_parameters']['Logotypes Card Images'];
					}
					if(isset($hpay_config['POS']['pos_parameters']['Logotypes Banks'])){
						$logotypes_setup['Logotypes Banks'] = $hpay_config['POS']['pos_parameters']['Logotypes Banks'];
					}
					if(isset($hpay_config['POS']['pos_parameters']['Logotypes 3DS'])){
						$logotypes_setup['Logotypes 3DS'] = $hpay_config['POS']['pos_parameters']['Logotypes 3DS'];
					}
				}

				return array(
                    'merchant_site_uid' => $merchant_site_uid,
                    'hpay_url' => ($environment === 'production') ? 'https://pay.holest.com' : 'https://sandbox.pay.holest.com',
                    'site_url' => HTTP_SERVER, 
                    'ajax_url' => $this->url->link('extension/holestpay/payment/holestpay', '', true),
                    'get_request_url' => $this->url->link('extension/holestpay/payment/holestpay|confirm', '', true),
                    'language' => $this->config->get('config_language'),
                    'hpaylang' => $hpaylang,
                    'plugin_version' => '1.0.0',
                    'environment' => $environment,
                    'cart' => $cart_data,
					'customer_id' => $this->customer->isLogged() ? $this->customer->getId() : null,
					'logotypes_setup' => $logotypes_setup,
                    'labels' => array(
                        'error_contact_us' => $this->language->get('text_error_contact_us') ?: 'Error, please contact us for assistance',
                        'remove_token_confirm' => $this->language->get('text_remove_token_confirm') ?: 'Please confirm you want to remove payment token',
                        'error' => $this->language->get('text_error') ?: 'Error',
                        'Payment refused' => $this->language->get('text_payment_refused') ?: 'Payment refused',
                        'Payment failed' => $this->language->get('text_payment_failed') ?: 'Payment failed',
                        'Payment canceled' => $this->language->get('text_payment_canceled') ?: 'Payment canceled',
                        'Payment error' => $this->language->get('text_payment_error') ?: 'Payment error',
                        'Try to pay again' => $this->language->get('text_try_again') ?: 'Try to pay again',
                        'Ordering as a company?' => $this->language->get('text_ordering_as_company') ?: 'Ordering as a company?',
                        'Company Tax ID' => $this->language->get('text_company_tax_id') ?: 'Company Tax ID',
                        'Company Register ID' => $this->language->get('text_company_register_id') ?: 'Company Register ID',
                        'Company Name' => $this->language->get('text_company_name') ?: 'Company Name',
						'Default' => $this->language->get('text_default') ?: 'Default',
						'Remove' => $this->language->get('text_remove') ?: 'Remove',
						'SetDefault' => $this->language->get('text_set_default') ?: 'Set as Default',
						'UseOther' => $this->language->get('text_use_other') ?: 'Use other...',
                        'Order UID' => $this->language->get('text_order_uid') ?: 'Order UID',
                        'Authorization Code' => $this->language->get('text_authorization_code') ?: 'Authorization Code',
                        'Payment Status' => $this->language->get('text_payment_status') ?: 'Payment Status',
                        'Transaction Status Code' => $this->language->get('text_transaction_status_code') ?: 'Transaction Status Code',
                        'Transaction ID' => $this->language->get('text_transaction_id') ?: 'Transaction ID',
                        'Transaction Time' => $this->language->get('text_transaction_time') ?: 'Transaction Time',
                        'Status code for the 3D transaction' => $this->language->get('text_3d_status_code') ?: 'Status code for the 3D transaction',
                        'Amount in order currency' => $this->language->get('text_amount_order_currency') ?: 'Amount in order currency',
                        'Amount in payment currency' => $this->language->get('text_amount_payment_currency') ?: 'Amount in payment currency'
                    )
                );
            } catch (Throwable $ex) {
                return array('error' => 'HolestPay configuration generation failed: ' . $ex->getMessage());
            }
        }

        public function holestpay_frontend_js($render_only = false){
            if(!$render_only){
                header('Content-Type: application/javascript');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }

            $out = '// HolestPay Configuration - Generated dynamically' . "\n";
            $out .= 'window.HolestPayCheckout = ' . json_encode($this->getHolestPayCheckout()) . ';' . "\n\n";
            
            $out .= "if(typeof HPayFrontOC === 'undefined' && !window.__HPayFrontOC_load) { 
    window.__HPayFrontOC_load = true;
    let script = document.createElement('script');
    script.src = window.HolestPayCheckout.site_url.replace(/\/$/,'') + '/extension/holestpay/catalog/view/javascript/holestpay-checkout.js?ver=' + window.HolestPayCheckout.plugin_version;
    script.onload = function() {
        if(typeof HPayFrontOC !== 'undefined') {
            HPayFrontOC.init(HolestPayCheckout);
        }
    };
    script.onerror = function() {
        console.error('Failed to load HolestPay hpay.js script from:', script.src);
    };
    document.head.appendChild(script);
}else if(window.HPayFrontOC){
	HPayFrontOC.init(HolestPayCheckout);
}";   
            if($render_only){
                return $out;
            }else{
                die($out);
            }
        }
		
		public function checkFontendScript(){
			try{
				$ext = "twig";
				$footer_files = array(DIR_TEMPLATE . "common". DIRECTORY_SEPARATOR . "footer.{$ext}");
				foreach(glob(DIR_EXTENSION . '*', GLOB_ONLYDIR) as $dir) {
					if(file_exists($dir . "/catalog/view/template/common/footer.{$ext}")){
						$footer_files[] = $dir . "/catalog/view/template/common/footer.{$ext}";
					}
				}
				
				$planted     = false;
				$cannotwrite = false;
				ob_start();
				foreach($footer_files as $footer_file){
					if(file_exists($footer_file)){
						$cnt = file_get_contents($footer_file);
						if(stripos($cnt,"</body>") !== false){
							if(stripos($cnt,"payment_holestpay_script") === false){
								$cnt = str_ireplace(
										  "</body>",
										  "\r\n<script id='payment_holestpay_script' src='".HTTP_SERVER."index.php?route=extension/holestpay/payment/holestpay|holestpay_frontend_js'></script>\r\n</body>",

										  $cnt);
								if(@file_put_contents($footer_file,$cnt)){
									$planted     = true;
								}else{
									$cannotwrite = true;
								}		  
							}else
								$planted = true;
						}
					}
				}
				$dump = ob_get_clean();
				if($planted)
					return false;	
				else if($cannotwrite){
					return " -- CAN NOT WRITE HOLESTPAY SCRIPT -- "; 
				}else{
					return " -- HOLESTPAY SCRIPT NOT PLACED -- "; 
				}
			}catch(Throwable $ex){
				return null;
			}
		}
		
		public function confirm() {
			$this->load->language($this->getLanguagePath());
			$this->load->model('extension/holestpay/payment/holestpay');
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
			
			// Get JSON data from request body
			$json_input = file_get_contents('php://input');
			$request_data = json_decode($json_input, true);
			
			$payment_method_id = isset($request_data['payment_method_id']) ? $request_data['payment_method_id'] : 'select';
			$shipping_method_id = isset($request_data['shipping_method_id']) ? $request_data['shipping_method_id'] : '';
			$vault_token_uid = isset($request_data['vault_token_uid']) ? $request_data['vault_token_uid'] : '';
			$cof = isset($request_data['cof']) ? $request_data['cof'] : 'none';
			
			// Generate HolestPay request
			$hpay_request = $this->generateHPayRequest($order_info, $payment_method_id, $shipping_method_id, $vault_token_uid, $cof);
			
			if (!$hpay_request) {
				$json['error'] = $this->language->get('error_request_generation');
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
			
			// Add signature
			$secret_key = $this->config->get('payment_holestpay_secret_key');
			$hpay_request['verificationhash'] = $this->model_extension_holestpay_payment_holestpay->generateSignature($hpay_request, $secret_key);
			
			// Update order with HolestPay data
			$this->model_extension_holestpay_payment_holestpay->createOrder(array('order_id' => $order_info['order_id']));
			
			$json['success'] = true;
			$json['hpay_request'] = $hpay_request;
			
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
		}
		
		private function generateHPayRequest($order_info, $payment_method_id, $shipping_method_id = '', $vault_token_uid = '', $cof = 'none') {
			$this->load->model('extension/holestpay/payment/holestpay');
			
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
				'shipping_method' => $shipping_method_id,
				'vault_token_uid' => $vault_token_uid,
				'cof' => $cof,
				'order_user_url' => $this->url->link('extension/holestpay/payment/holestpay', 'order_id=' . $order_info['order_id'], true)
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

			header('Content-Type: application/json');
			
			// Handle OPTIONS preflight request
			if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
				http_response_code(200);
				exit;
			}
			
			try {
				$this->load->model('extension/holestpay/payment/holestpay');
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
						echo json_encode(array("error" => "Unknown webhook topic: " . $topic));
						die;
				}
				
				http_response_code(200);
				echo json_encode(array("accepted" => $topic));
				die;
			
			} catch (Throwable $e) {
				error_log('HolestPay Webhook Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
				http_response_code(500);
				echo json_encode(array("error" => $e->getMessage()));
				die;
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
			$pos_config = $webhook_data;
			
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
			$this->load->model('extension/holestpay/payment/holestpay');
			
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
			
			return true;
		}
		
		private function processOrderUpdateWebhook($webhook_data) {
			if (!isset($webhook_data['order_uid']) || !isset($webhook_data['status'])) {
				return false;
			}
			
			// CRITICAL: Set flag to prevent order_store API calls during webhook processing
			$_SESSION['holestpay_webhook_processing'] = true;
			
			try {
				$order_id = $webhook_data['order_uid'];
				$hpay_status = $webhook_data['status'];
				$new_hpay_data = $webhook_data;
				
				if(isset($new_hpay_data['order'])){
					unset($new_hpay_data['order']);
				}

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
			//$merged_data['payment_status'] = $this->extractPaymentStatus($hpay_status);
			
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
            if(stripos($hpay_status, 'PAYMENT:') !== false){
                $hpay_status = explode('PAYMENT:', $hpay_status);
                $hpay_status = $hpay_status[1];
                $hpay_status = explode(' ', $hpay_status);
                $hpay_status = $hpay_status[0];
                return $hpay_status;
            }else if(stripos($hpay_status, ':') === false){
                if(in_array($hpay_status, self::$_possible_hpay_pay_statuses)){
                    return $hpay_status;
                }
            }
			return "";
		}
		
		private function processPaymentResultWebhook($webhook_data) {
			if (!isset($webhook_data['order_uid']) || !isset($webhook_data['status'])) {
				return false;
			}
			
			$order_id = $webhook_data['order_uid'];
			$payment_status = $this->extractPaymentStatus($webhook_data['status']);
			
			$order_info = $this->model_checkout_order->getOrder($order_id);
			
			if (!$order_info) {
				return false;
			}
			
			// Determine order status based on payment result
			if (in_array($payment_status, array('SUCCESS', 'PAID','RESERVED','AWAITING','OBLIGATED'))) {
				$order_status_id = $this->config->get('payment_holestpay_order_status_id');
				$comment = 'Payment successful via HolestPay';
			} else {
				$order_status_id = $this->config->get('payment_holestpay_order_status_failed_id');
				$comment = 'Payment failed via HolestPay: ' . $payment_status;
			}
			
            $this->mergeHPayOrderData($order_id, $payment_status, $webhook_data);

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
			
			if (in_array($payment_status, array('SUCCESS', 'PAID','RESERVED','AWAITING','OBLIGATED'))) {
				$order_status_id = $this->config->get('payment_holestpay_order_status_id');
			} elseif (in_array($payment_status, array('FAILED', 'REFUSED', 'CANCELED'))) {
				$order_status_id = $this->config->get('payment_holestpay_order_status_failed_id');
			} else {
				return; // Don't update for pending/processing statuses
			}
			
			$this->model_checkout_order->addHistory($order_id, $order_status_id, 'Order status updated via HolestPay webhook', true);
		}
		
		public function charge() {
			// Handle subscription charges (MIT/COF) - like WooCommerce sample
			$this->load->model('extension/holestpay/payment/holestpay');
			
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
				'subscription_uid' => ''
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
			//We need to not burn much time on support
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			
			curl_setopt($ch,CURLOPT_RESOLVE, array(
				"pay.holest.com:443:95.217.201.105",
				"sandbox.pay.holest.com:443:95.217.201.105",
				"holest.com:443:176.9.124.17",
				"www.holest.com:443:176.9.124.17",
				"cdn.payments.holest.com:443:176.9.124.17",
				"payments.holest.com:443:176.9.124.17"
			));
			
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
			
			$this->load->language($this->getLanguagePath());
			
			$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
			
			if (!$order_id) {
				$this->response->redirect($this->url->link('common/home'));
				return;
			}

			$hpay_clear_cart = isset($this->request->post['hpay_clear_cart']) ? (int)$this->request->post['hpay_clear_cart'] : (isset($this->request->get['hpay_clear_cart']) ? (int)$this->request->get['hpay_clear_cart'] : 0);
			if($hpay_clear_cart){
				$this->cart->clear();
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
			
			// Get order products
			$data['order_products'] = $this->model_checkout_order->getProducts($order_id);
			
			// Get order totals (including shipping cost and method)
			$data['order_totals'] = $this->model_checkout_order->getTotals($order_id);
			
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
			$status = $hpay_data['status'];

			if(stripos($status, 'SUCCESS') !== false || stripos($status, 'PAID') !== false || stripos($status, 'RESERVED') !== false){
				return $this->language->get('text_payment_success');
			}else if(stripos($status, 'FAILED') !== false){
				return $this->language->get('text_payment_failed');
			}else if(stripos($status, 'CANCELLED') !== false){
				return $this->language->get('text_payment_cancelled');
			}else if(stripos($status, 'PENDING') !== false){
				return $this->language->get('text_payment_pending');
			}else{
				return $this->language->get('text_payment_pending');
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
			// - script loading from ANY domain (for HolestPay dynamic script loading)
			$csp_policy = "default-src 'self'; " .
						 "script-src 'self' 'unsafe-eval' 'unsafe-inline' *; " .
						 "frame-src 'self' https://{$hpay_domain}; " .
						 "connect-src 'self' https://{$hpay_domain}; " .
						 "img-src 'self' * data: https://{$hpay_domain}; " .
						 "style-src 'self' * 'unsafe-inline' https://{$hpay_domain};";
			
			// Set CSP headers
			$this->response->addHeader('Content-Security-Policy: ' . $csp_policy);
			$this->response->addHeader('X-Content-Security-Policy: ' . $csp_policy);
			$this->response->addHeader('X-WebKit-CSP: ' . $csp_policy);
			
			// Additional security headers for iframe embedding
			$this->response->addHeader('X-Frame-Options: SAMEORIGIN');
			$this->response->addHeader('X-Content-Type-Options: nosniff');
			
			// Log CSP policy for debugging
			//error_log("HolestPay CSP Policy set - script-src allows all domains (*), frame/connect restricted to: {$hpay_domain}");
		}
		
		// SUBSCRIPTION SCHEDULING SYSTEM (like WooCommerce hpay_15min_run)
		public function checkSubscriptionCharges() {
			// This method should be called by a cron job every 15 minutes
			$this->load->model('extension/holestpay/payment/holestpay');
			
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
		
		private function getTemplatePath() {
			// For OpenCart 4, always use the standard payment template path
			// The extension template files should be copied to the standard location during installation
			return 'payment/holestpay';
		}
		
		private function getErrorTemplatePath() {
			// Always use payment error template path for errors
			return 'payment/holestpay_error';
		}
		
		private function getLanguagePath() {
			// For OpenCart 4, always use the standard payment language path
			// The extension language files should be copied to the standard location during installation
			return 'payment/holestpay';
		}
	}
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Catalog\Controller\Extension\Holestpay\Payment\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/payment/holestpay.php";
}
