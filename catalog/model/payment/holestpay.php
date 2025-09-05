<?php
namespace Opencart\Catalog\Model\Payment;

class Holestpay extends \Opencart\System\Engine\Model {
    
    public function getMethod($address, $total = null) {
        // Debug logging to check if method is being called
        error_log('HolestPay getMethod() called with address: ' . print_r($address, true));
        
        $this->load->language('payment/holestpay');
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_holestpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        
        $status = false;
        
        if (!$this->config->get('payment_holestpay_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        }
        
        $method_data = array();
        
        // Debug configuration values
        error_log('HolestPay Debug - Status: ' . ($status ? 'true' : 'false'));
        error_log('HolestPay Debug - payment_holestpay_status: ' . $this->config->get('payment_holestpay_status'));
        error_log('HolestPay Debug - merchant_site_uid: ' . $this->config->get('payment_holestpay_merchant_site_uid'));
        
        if ($status && $this->config->get('payment_holestpay_status')) {
            error_log('HolestPay Debug - Conditions met, getting payment methods...');
            // Get HolestPay payment methods from configuration
            $payment_methods = $this->getHolestPayMethods();
            error_log('HolestPay Debug - Found ' . count($payment_methods) . ' payment methods');
            
            if (!empty($payment_methods)) {
                // CRITICAL: Return single payment method object with sub-methods
                // Similar to how shipping methods work in OpenCart
                
                $hpay_methods = array();
                foreach ($payment_methods as $hpay_method) {
                    $hpay_methods[] = array(
                        'code'       => 'holestpay_' . $hpay_method['hpay_id'],
                        'title'      => $hpay_method['method_name'],
                        'terms'      => '',
                        'sort_order' => $hpay_method['sort_order'],
                        'hpay_id'    => $hpay_method['hpay_id'],
                        'supports_mit' => $hpay_method['supports_mit'],
                        'supports_cof' => $hpay_method['supports_cof']
                    );
                }
                
                // Return single method object with sub-methods array
                $method_data = array(
                    'code'       => 'holestpay',
                    'title'      => $this->language->get('text_title') ?: 'HolestPay',
                    'terms'      => '',
                    'sort_order' => $this->config->get('payment_holestpay_sort_order') ?: 1,
                    'hpay_methods' => $hpay_methods // Sub-methods go here
                );
            }
        }
        
        error_log('HolestPay Debug - Returning method_data: ' . print_r($method_data, true));
        return $method_data;
    }
    
    public function getHolestPayMethods() {
        $config_data = $this->getHolestPayConfig();
        
        if (!$config_data || !isset($config_data['POS']['payment'])) {
            return array();
        }
        
        $payment_methods = array();
        
        // Convert POS payment configuration to the expected format
        if (isset($config_data['POS']['payment']) && is_array($config_data['POS']['payment'])) {
            foreach ($config_data['POS']['payment'] as $method_data) {
                // Only include enabled payment methods (using correct field names from webhook sample)
                if (isset($method_data['Enabled']) && $method_data['Enabled'] === true) {
                    $payment_methods[] = array(
                        'hpay_id' => $method_data['Uid'],
                        'method_name' => $method_data['SystemTitle'] ?? $method_data['Uid'],
                        'sort_order' => $method_data['Order'] ?? 0,
                        'enabled' => $method_data['Enabled'],
                        'supports_mit' => isset($method_data['MIT']) ? $method_data['MIT'] : false,
                        'supports_cof' => isset($method_data['COF']) ? $method_data['COF'] : false,
                        'environment' => $this->config->get('payment_holestpay_environment'),
                        'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
                        'payment_type' => $method_data['PaymentType'] ?? '',
                        'instant' => $method_data['Instant'] ?? false,
                        'hpay_site_method_id' => $method_data['HPaySiteMethodId']
                    );
                }
            }
            
            // Sort by sort_order
            usort($payment_methods, function($a, $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });
        }
        
        return $payment_methods;
    }
    
    public function getHolestPayShippingMethods() {
        $config_data = $this->getHolestPayConfig();
        
        if (!$config_data || !isset($config_data['POS']['shipping'])) {
            return array();
        }
        
        $shipping_methods = array();
        
        // Convert POS shipping configuration to the expected format
        if (isset($config_data['POS']['shipping']) && is_array($config_data['POS']['shipping'])) {
            foreach ($config_data['POS']['shipping'] as $method_data) {
                // Only include enabled shipping methods (using correct field names from webhook sample)
                if (isset($method_data['Enabled']) && $method_data['Enabled'] === true) {
                    $shipping_methods[] = array(
                        'hpay_id' => $method_data['Uid'],
                        'method_name' => $method_data['SystemTitle'] ?? $method_data['Uid'],
                        'method_code' => $method_data['ShippingMethod'],
                        'sort_order' => $method_data['Order'] ?? 0,
                        'enabled' => $method_data['Enabled'],
                        'cost' => 0, // Cost calculated elsewhere
                        'tax_class_id' => 0,
                        'environment' => $this->config->get('payment_holestpay_environment'),
                        'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
                        'hpay_site_method_id' => $method_data['HPaySiteMethodId'],
                        'instant' => $method_data['Instant'] ?? false
                    );
                }
            }
            
            // Sort by sort_order
            usort($shipping_methods, function($a, $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });
        }
        
        return $shipping_methods;
    }
    
    public function getHolestPayConfig() {
        $environment = $this->config->get('payment_holestpay_environment');
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        if (!$environment || !$merchant_site_uid) {
            return false;
        }
        
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if ($query->num_rows) {
            return json_decode($query->row['config_data'], true);
        }
        
        return false;
    }
    
    public function createOrder($order_data) {
        // Generate HolestPay order data
        $hpay_order_data = $this->generateHPayOrderData($order_data);
        
        // Save order with HolestPay data
        $order_id = $order_data['order_id'];
        $hpay_uid = $order_id; // Default to OpenCart order ID, can be changed by HolestPay
        
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `hpay_uid` = '" . $this->db->escape($hpay_uid) . "',
            `hpay_status` = 'PENDING',
            `hpay_data` = '" . $this->db->escape(json_encode($hpay_order_data)) . "'
            WHERE `order_id` = '" . (int)$order_id . "'");
        
        return $hpay_order_data;
    }
    
    public function generateHPayOrderData($order_data) {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_data['order_id']);
        
        if (!$order_info) {
            return false;
        }
        
        // Get cart data
        $cart_data = $this->getCartData();
        
        // Generate order items
        $order_items = $this->generateOrderItems($order_info);
        
        $hpay_data = array(
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
            'notify_url' => HTTP_SERVER . 'extension/holestpay/holestpay_webhook.php',
            'return_url' => HTTP_SERVER . 'index.php?route=checkout/success'
        );
        
        return $hpay_data;
    }
    
    public function getCartData() {
        $cart_data = array(
            'cart_amount' => $this->cart->getTotal(),
            'order_currency' => $this->session->data['currency'],
            'order_items' => array()
        );
        
        // Get cart products
        foreach ($this->cart->getProducts() as $product) {
            $cart_data['order_items'][] = array(
                'posuid' => $product['product_id'],
                'type' => 'product',
                'name' => $product['name'],
                'sku' => $product['model'],
                'qty' => $product['quantity'],
                'price' => $product['price'],
                'subtotal' => $product['total'],
                'virtual' => false
            );
        }
        
        return $cart_data;
    }
    
    public function generateOrderItems($order_info) {
        $this->load->model('checkout/order');
        $order_products = $this->model_checkout_order->getProducts($order_info['order_id']);
        
        $items = array();
        
        foreach ($order_products as $product) {
            $items[] = array(
                'posuid' => $product['product_id'],
                'type' => 'product',
                'name' => $product['name'],
                'sku' => $product['model'],
                'qty' => $product['quantity'],
                'price' => $product['price'],
                'subtotal' => $product['total'],
                'virtual' => false
            );
        }
        
        return $items;
    }
    
    public function generateSignature($data, $secret_key) {
        // Generate request signature for HolestPay
        $string_to_sign = '';
        
        // Create signature string based on HolestPay requirements
        if (isset($data['order_uid']) && isset($data['order_amount']) && isset($data['order_currency'])) {
            $string_to_sign = $data['order_uid'] . $data['order_amount'] . $data['order_currency'] . $secret_key;
        }
        
        return hash('sha256', $string_to_sign);
    }
    
    public function getCustomerVaultTokens($customer_id) {
        if (!$customer_id) {
            return array();
        }
        
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_vault_tokens` 
            WHERE `customer_id` = '" . (int)$customer_id . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'
            AND `enabled` = '1'
            ORDER BY `date_added` DESC");
        
        return $query->rows;
    }
    
    public function saveVaultToken($customer_id, $vault_token_uid, $vault_card_mask, $payment_method_id) {
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "holestpay_vault_tokens` SET 
            `customer_id` = '" . (int)$customer_id . "',
            `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "',
            `vault_token_uid` = '" . $this->db->escape($vault_token_uid) . "',
            `vault_card_mask` = '" . $this->db->escape($vault_card_mask) . "',
            `payment_method_id` = '" . $this->db->escape($payment_method_id) . "',
            `date_added` = NOW(),
            `date_modified` = NOW()");
    }
    
    // ENHANCED VAULT TOKEN MANAGEMENT (Email-based like WooCommerce)
    public function setVaultTokenDefault($vault_token_id, $customer_id) {
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        // Get customer email
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($customer_id);
        $customer_email = $customer_info ? $customer_info['email'] : '';
        
        // Clear all defaults for this customer
        $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_vault_tokens` SET 
            `is_default` = '0'
            WHERE `customer_email` = '" . $this->db->escape($customer_email) . "'
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        // Set new default
        $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_vault_tokens` SET 
            `is_default` = '1'
            WHERE `vault_token_id` = '" . (int)$vault_token_id . "'
            AND `customer_email` = '" . $this->db->escape($customer_email) . "'
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
    }
    
    public function removeVaultToken($vault_token_id, $customer_id) {
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_vault_tokens` SET 
            `enabled` = '0'
            WHERE `vault_token_id` = '" . (int)$vault_token_id . "'
            AND `customer_id` = '" . (int)$customer_id . "'
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
    }
    
    // SUBSCRIPTION MANAGEMENT METHODS
    public function createSubscription($order_id, $vault_token_uid, $subscription_data) {
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        $environment = $this->config->get('payment_holestpay_environment');
        
        // Set charge delay (20 minutes minimum like WooCommerce)
        $charge_after_ts = time() + (20 * 60); // 20 minutes
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "holestpay_subscriptions` SET 
            `order_id` = '" . (int)$order_id . "',
            `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "',
            `environment` = '" . $this->db->escape($environment) . "',
            `vault_token_uid` = '" . $this->db->escape($vault_token_uid) . "',
            `subscription_data` = '" . $this->db->escape(json_encode($subscription_data)) . "',
            `charge_after_ts` = '" . (int)$charge_after_ts . "',
            `charge_attempts` = '0',
            `status` = 'pending',
            `date_added` = NOW()");
        
        // Update order with charge delay info
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `hpay_data` = JSON_SET(COALESCE(`hpay_data`, '{}'), '$.charge_after_ts', '" . (int)$charge_after_ts . "'),
            `hpay_data` = JSON_SET(`hpay_data`, '$.charge_delay_message', 'First charge will be attempted at " . date('Y-m-d H:i:s', $charge_after_ts) . "')
            WHERE `order_id` = '" . (int)$order_id . "'");
    }
    
    public function getPendingSubscriptionCharges() {
        $current_time = time();
        
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_subscriptions` 
            WHERE `status` = 'pending'
            AND `charge_after_ts` <= '" . (int)$current_time . "'
            AND `charge_attempts` < 3
            ORDER BY `charge_after_ts` ASC
            LIMIT 10");
        
        return $query->rows;
    }
    
    public function updateSubscriptionChargeAttempt($subscription_id, $success = false) {
        $subscription = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_subscriptions` 
            WHERE `subscription_id` = '" . (int)$subscription_id . "'");
        
        if (!$subscription->num_rows) {
            return false;
        }
        
        $attempts = (int)$subscription->row['charge_attempts'] + 1;
        
        if ($success) {
            $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_subscriptions` SET 
                `charge_attempts` = '" . $attempts . "',
                `status` = 'active',
                `last_charge_attempt` = NOW()
                WHERE `subscription_id` = '" . (int)$subscription_id . "'");
        } else {
            // Calculate next attempt time (24h after 1st attempt, 48h after 2nd)
            $next_attempt_delay = ($attempts == 1) ? (24 * 60 * 60) : (48 * 60 * 60);
            $next_attempt_ts = time() + $next_attempt_delay;
            
            $status = ($attempts >= 3) ? 'failed' : 'pending';
            
            $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_subscriptions` SET 
                `charge_attempts` = '" . $attempts . "',
                `status` = '" . $status . "',
                `charge_after_ts` = '" . (int)$next_attempt_ts . "',
                `last_charge_attempt` = NOW()
                WHERE `subscription_id` = '" . (int)$subscription_id . "'");
        }
    }
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Catalog\Model\Extension\Holestpay\Payment\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/payment/holestpay.php";
}
