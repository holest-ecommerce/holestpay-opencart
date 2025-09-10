<?php
namespace Opencart\Catalog\Model\Payment;

class Holestpay extends \Opencart\System\Engine\Model {

    public function getHPayLanguageCode() {
        $current_language = $this->language->get('code');
        if(stripos($current_language,'yu') !== false ){
            return 'rs';
        }else if(stripos($current_language,'sr') !== false || stripos($current_language,'rs') !== false){
            return 'rs-cyr';
        }else if(stripos($current_language,'mk') !== false ){
            return 'mk';
        }else{
            return strtolower(substr($current_language,0,2));
        }
    }
    
    public function getMethod($address, $total = null) {
        // Debug logging to check if method is being called
        
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
        if ($status && $this->config->get('payment_holestpay_status')) {
            // Get HolestPay payment methods from configuration
            $payment_methods = $this->getHolestPayMethods();
            
            if (!empty($payment_methods)) {

				$p_names = array();

                $hpaylang = $this->getHPayLanguageCode();
                foreach($payment_methods as $index => $payment_method){
					$p_names[] = $payment_method["Name"];
                    if(isset($payment_method['localized']) && isset($payment_method['localized'][$hpaylang])){
                        $payment_methods[$index] = array_merge($payment_method, $payment_method['localized'][$hpaylang]);
                    }
                }

                // Return single method object with sub-methods array
                $method_data = array(
                    'code'       => 'holestpay',
                    'title'      => implode(" | ",$p_names),
                    'terms'      => '',
                    'sort_order' => $this->config->get('payment_holestpay_sort_order') ?: 1,
                    'hpay_methods' => $payment_methods,
                    'hpaylang'     => $hpaylang
                );
            }
        }
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
                    $method_data['hpay_id'] = $method_data['HPaySiteMethodId'];
                    $method_data['sort_order'] = $method_data['Order'];
                    $payment_methods[] = $method_data;
                }
            }
            $current_language = 
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
                if (isset($method_data['Enabled']) && $method_data['Enabled'] === true && !$method_data['Hidden']) {
                    $method_data['hpay_id'] = $method_data['HPaySiteMethodId'];
                    $method_data['sort_order'] = $method_data['Order'];
                    $shipping_methods[] = $method_data;
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
        
        // Generate order items (without shipping for cart data, but with fees)
        $order_items = $this->generateOrderItems($order_info, false);
        
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
        try{
            // Calculate total including shipping (cart total already includes taxes)
            $cart_total = $this->cart->getTotal();
            $shipping_cost = 0.0;

            // Insert HolestPay shipping methods dynamically using helper class
            require_once(__DIR__ . '/../shipping/holestpay_shipping_helper.php');
            
            // Get shipping address for method calculation
            $address = array();
            if (isset($this->session->data['shipping_address'])) {
                $address = $this->session->data['shipping_address'];
            }
            
            // Create helper instance
            $shipping_helper = new HolestPayShippingHelper($this->registry);
            
            // Get enabled HolestPay shipping methods
            $hpay_shipping_methods = $shipping_helper->getHolestPayShippingMethods($address);

            // Get cart data for shipping calculation
            $cart_weight = 0;
            $cart_amount = $cart_total;
            $cart_currency = $this->session->data['currency'];
            
            foreach ($this->cart->getProducts() as $product) {
                $cart_weight += $product['weight'] * $product['quantity'];
            }

            if(!isset($this->session->data['needs_reload'])){   
                $this->session->data['needs_reload'] = 0;
            }

            $is_hpay_shipping_id = null;
            // Get shipping cost from session for the selected shipping method
            if (isset($this->session->data['shipping_methods'])) {
                $selected_method = isset($this->session->data['shipping_method']) ? $this->session->data['shipping_method'] : "";
                foreach ($this->session->data['shipping_methods'] as $key => $shipping_method) {
                    $this->session->data['shipping_methods'][$key]["hpay_checked"] = 1;
                    if (isset($shipping_method['quote'])) {
                        foreach ($shipping_method['quote'] as $quote_key => $quote) {
                            //check if there is hpay shipping method with same name
                            foreach ( $hpay_shipping_methods as $hpay_shipping_method) {
                                if (trim(strtolower($hpay_shipping_method['method_name'])) === trim(strtolower($shipping_method['title']))) {
                                    //this is connected hpay shipping method
                                    $cost = $shipping_helper->calculateShippingCost($hpay_shipping_method, $cart_weight, $cart_amount, $cart_currency, $address);
                                    
                                    if($this->session->data['shipping_methods'][$key]['quote'][$quote_key]['cost'] != $cost){
                                        $this->session->data['needs_reload'] = time();
                                    }
                                    
                                    $this->session->data['shipping_methods'][$key]['quote'][$quote_key]['cost'] = $cost;
                                    $this->session->data['shipping_methods'][$key]['quote'][$quote_key]['text'] = $cost . " " . $this->session->data['currency'];
                                    $is_hpay_shipping_id = $hpay_shipping_method['hpay_id'];
                                    $quote['cost'] = $cost;
                                    break;
                                }
                            }

                            if (isset($quote['code']) && $quote['code'] === $selected_method && isset($quote['cost'])) {
                                $shipping_cost = (float)$quote['cost'];
                            }
                        }
                    }
                }
            }
            
            $order_total = $cart_total + $shipping_cost;
            $cart_data = array(
                'cart_amount'    => $cart_total,
                'order_amount'   => $order_total,
                'order_currency' => $this->session->data['currency'],
                'order_items'    => array(),
                'order_billing'  => array(),
                'order_shipping' => array(),
                'shipping_cost'  => $shipping_cost,
                'needs_reload'   => $this->session->data['needs_reload']
            );

            if( $is_hpay_shipping_id ){
                $cart_data['shipping_method'] = $is_hpay_shipping_id;
            }else{
                $cart_data['shipping_method'] = '';
            }

            //$cart_data['session_data'] = $this->session->data;
            
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
            
            // Get billing and shipping addresses, with fallback logic
            $billing_address = isset($this->session->data['payment_address']) ? $this->session->data['payment_address'] : array();
            $shipping_address = isset($this->session->data['shipping_address']) ? $this->session->data['shipping_address'] : array();
            
            // If billing address is missing, copy from shipping address
            if (empty($billing_address) && !empty($shipping_address)) {
                $billing_address = $shipping_address;
            }
            
            // If shipping address is missing, copy from billing address
            if (empty($shipping_address) && !empty($billing_address)) {
                $shipping_address = $billing_address;
            }
            
            // Get customer data for email and other customer info
            $customer_data = isset($this->session->data['customer']) ? $this->session->data['customer'] : array();
            
            // Set billing data - try customer data first, then fall back to address data
            $cart_data['order_billing'] = array(
                'email' => isset($customer_data['email']) ? $customer_data['email'] : (isset($billing_address['email']) ? $billing_address['email'] : ''),
                'first_name' => isset($customer_data['firstname']) ? $customer_data['firstname'] : (isset($billing_address['firstname']) ? $billing_address['firstname'] : ''),
                'last_name' => isset($customer_data['lastname']) ? $customer_data['lastname'] : (isset($billing_address['lastname']) ? $billing_address['lastname'] : ''),
                'phone' => isset($customer_data['telephone']) ? $customer_data['telephone'] : (isset($billing_address['telephone']) ? $billing_address['telephone'] : ''),
                'is_company' => 0,
                'company' => isset($billing_address['company']) ? $billing_address['company'] : '',
                'company_tax_id' => '',
                'company_reg_id' => '',
                'address' => isset($billing_address['address_1']) ? $billing_address['address_1'] : '',
                'address2' => isset($billing_address['address_2']) ? $billing_address['address_2'] : '',
                'city' => isset($billing_address['city']) ? $billing_address['city'] : '',
                'country' => isset($billing_address['iso_code_2']) ? $billing_address['iso_code_2'] : '',
                'state' => isset($billing_address['zone']) ? $billing_address['zone'] : '',
                'postcode' => isset($billing_address['postcode']) ? $billing_address['postcode'] : '',
                'lang' => $this->config->get('config_language')
            );
            
            // Set shipping data
            $cart_data['order_shipping'] = array(
                'shippable' => true,
                'is_cod' => false,
                'first_name' => isset($shipping_address['firstname']) ? $shipping_address['firstname'] : '',
                'last_name' => isset($shipping_address['lastname']) ? $shipping_address['lastname'] : '',
                'phone' => isset($shipping_address['telephone']) ? $shipping_address['telephone'] : '',
                'company' => isset($shipping_address['company']) ? $shipping_address['company'] : '',
                'address' => isset($shipping_address['address_1']) ? $shipping_address['address_1'] : '',
                'address2' => isset($shipping_address['address_2']) ? $shipping_address['address_2'] : '',
                'city' => isset($shipping_address['city']) ? $shipping_address['city'] : '',
                'country' => isset($shipping_address['iso_code_2']) ? $shipping_address['iso_code_2'] : '',
                'state' => isset($shipping_address['zone']) ? $shipping_address['zone'] : '',
                'postcode' => isset($shipping_address['postcode']) ? $shipping_address['postcode'] : ''
            );

            return $cart_data;
        }catch(Throwable $ex){
            error_log("Error in getCartData: " . $ex->getMessage());
            return null;
        }
    }
    
    public function generateOrderItems($order_info, $include_shipping = true) {
        $this->load->model('checkout/order');
        $order_products = $this->model_checkout_order->getProducts($order_info['order_id']);
        
        $items = array();
        
        // Add products
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
        
        // Always add fees, conditionally add shipping
        $this->load->model('checkout/order');
        $order_totals = $this->model_checkout_order->getTotals($order_info['order_id']);
        
        foreach ($order_totals as $total) {
            // Skip products (already added above) and subtotal
            if (in_array($total['code'], ['product', 'subtotal'])) {
                continue;
            }
            
            // Add shipping costs only if requested
            if ($total['code'] == 'shipping' && $total['value'] > 0 && $include_shipping) {
                $items[] = array(
                    'posuid' => 'shipping_' . $total['code'],
                    'type' => 'shipping',
                    'name' => $total['title'],
                    'sku' => 'SHIPPING',
                    'qty' => 1,
                    'price' => $total['value'],
                    'subtotal' => $total['value'],
                    'virtual' => false
                );
            }
            // Always add fees (tax, discount, etc.)
            elseif (in_array($total['code'], ['tax', 'discount', 'coupon', 'voucher', 'reward', 'handling', 'low_order_fee']) && $total['value'] != 0) {
                $items[] = array(
                    'posuid' => 'fee_' . $total['code'],
                    'type' => 'fee',
                    'name' => $total['title'],
                    'sku' => strtoupper($total['code']),
                    'qty' => 1,
                    'price' => $total['value'],
                    'subtotal' => $total['value'],
                    'virtual' => false
                );
            }
        }
        
        return $items;
    }
    
    public function generateSignature($data, $secret_key) {
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
