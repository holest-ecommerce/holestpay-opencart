<?php
/**
 * OpenCart 3 Compatible HolestPay Payment Controller
 * This file provides OpenCart 3 compatibility for the HolestPay extension
 */

class ControllerPaymentHolestpay extends Controller {
    private $error = array();
    
    public function __construct($registry) {
        parent::__construct($registry);
        // Add HolestPay admin JavaScript
        $this->document->addScript('view/javascript/holestpay-admin.js');
    }
    
    private function getParameterValue($name, $default = null) {
        $value = $this->config->get('payment_holestpay_' . $name);
        return ($value !== null && $value !== '') ? $value : $default;
    }
    
    public function index() {
        try {
            // Load language
            $this->load->language('payment/holestpay');
            
            $this->document->setTitle($this->language->get('heading_title'));
            
            // Set CSP headers for HolestPay admin integration
            $this->setHolestPayCSPHeaders();
            
            if(isset($this->request->get['action'])){
                if($this->request->get['action'] == "orderStoreApiCall"){
                    return $this->orderStoreApiCall();
                }else if($this->request->get['action'] == "processManualCharge"){
                    return $this->processManualCharge();
                }
            }
            
            // Auto-sync extension files when entering configuration
            $sync_result = $this->autoSyncExtensionFiles();
        
            $this->load->model('setting/setting');
            
            if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
                $this->model_setting_setting->editSetting('payment_holestpay', $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true));
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
                'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('payment/holestpay', 'token=' . $this->session->data['token'], true)
            );
            
            // Form data
            $data['action'] = $this->url->link('payment/holestpay', 'token=' . $this->session->data['token'], true);
            $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);
            
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
            $catalog_url = $this->config->get('config_ssl') ? 
                str_replace(basename(DIR_APPLICATION) . '/', '', HTTPS_SERVER) :
                str_replace(basename(DIR_APPLICATION) . '/', '', HTTP_SERVER);
            $data['webhook_url'] = $catalog_url . 'index.php?route=payment/holestpay';
            
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
            
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            // Add sync result to template data
            $data['sync_result'] = $sync_result;
            
            // Fetch HolestPayAdmin data for JavaScript
            $data['holestpay_admin_data'] = $this->fetchHolestPayAdminData(true);

            // Auto-correct permissions for HolestPay module
            $this->correctHolestPayPermissions();
            
            $this->response->setOutput($this->load->view('payment/holestpay', $data));
            
        } catch (Throwable $e) {
            $this->log->write('HolestPay Admin Index Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Display generic error page to user
            $data['error_warning'] = 'An error occurred while loading the HolestPay configuration. Please check the error logs.';
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view('payment/holestpay', $data));
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
        }
    }
    
    public function uninstall() {
        try {
            $this->load->model('payment/holestpay');
            $this->model_payment_holestpay->uninstall();
        } catch (Throwable $e) {
            $this->log->write('HolestPay Uninstall Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        }
    }
    
    // Include all the other methods from the OpenCart 4 version
    // (truncated for brevity - would include all methods)
    
    private function setHolestPayCSPHeaders() {
        $environment = $this->getParameterValue('environment', 'sandbox');
        $hpay_domain = ($environment === 'production') ? 'pay.holest.com' : 'sandbox.pay.holest.com';
        
        $csp_policy = "default-src 'self'; " .
                     "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://{$hpay_domain}; " .
                     "frame-src 'self' https://{$hpay_domain}; " .
                     "connect-src 'self' https://{$hpay_domain}; " .
                     "img-src 'self' data: https://{$hpay_domain}; " .
                     "style-src * 'unsafe-inline'; " .
                     "font-src 'self' https://fonts.gstatic.com;";
        
        $this->response->addHeader('Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-Content-Security-Policy: ' . $csp_policy);
        $this->response->addHeader('X-WebKit-CSP: ' . $csp_policy);
        $this->response->addHeader('X-Frame-Options: SAMEORIGIN');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
    }
    
    // Include other essential methods here...
    // (This is a simplified version - the full version would include all methods)
}
