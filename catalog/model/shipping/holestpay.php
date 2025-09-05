<?php
namespace Opencart\Catalog\Model\Shipping;

class Holestpay extends \Opencart\System\Engine\Model {
    
    public function getQuote($address) {
        $this->load->language('shipping/holestpay');
        
        // Check if HolestPay payment module is configured (needed for API access)
        if (!$this->config->get('payment_holestpay_merchant_site_uid') || 
            !$this->config->get('payment_holestpay_secret_key')) {
            return false;
        }
        
        // Get enabled HolestPay shipping methods from configuration
        $shipping_methods = $this->getHolestPayShippingMethods($address);
        
        // If no enabled shipping methods found, shipping is effectively disabled
        if (empty($shipping_methods)) {
            return false;
        }
        
        // CRITICAL: Return single shipping method object with sub-methods
        // Similar to how payment methods work in OpenCart
        $hpay_shipping_quotes = array();
        
        foreach ($shipping_methods as $shipping_method) {
            // Calculate shipping cost for this method
            $cost = $this->calculateShippingCost($shipping_method, $address);
            
            // CRITICAL: Use HPaySiteMethodId for identification (like Magento sample)
            $hpay_site_method_id = $shipping_method['hpay_site_method_id'];
            
            $hpay_shipping_quotes[$shipping_method['method_code']] = array(
                'code'         => 'holestpay.' . $shipping_method['method_code'],
                'title'        => $shipping_method['method_name'],
                'cost'         => $cost,
                'tax_class_id' => 0,
                'text'         => $this->currency->format($cost, $this->session->data['currency']),
                'hpay_site_method_id' => $hpay_site_method_id, // CRITICAL: Store HPaySiteMethodId
                'hpay_id'      => $shipping_method['hpay_id'],
                'description'  => $shipping_method['description']
            );
        }
        
        // Return single shipping method object with sub-methods in quote
        $method_data = array(
            'code'         => 'holestpay',
            'title'        => $this->language->get('text_title') ?: 'HolestPay Shipping',
            'quote'        => $hpay_shipping_quotes,
            'sort_order'   => $this->config->get('shipping_holestpay_sort_order') ?: 1,
            'error'        => false
        );
        
        return $method_data;
    }
    
    private function getHolestPayShippingMethods($address) {
        $environment = $this->config->get('payment_holestpay_environment');
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        if (!$environment || !$merchant_site_uid) {
            return array();
        }
        
        // Get HolestPay configuration to check for enabled shipping methods
        $query = $this->db->query("SELECT `config_data` FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if (!$query->num_rows) {
            return array();
        }
        
        $config_data = json_decode($query->row['config_data'], true);
        if (!$config_data || !isset($config_data['POS']['shipping']) || !is_array($config_data['POS']['shipping'])) {
            return array();
        }
        
        // Filter only enabled shipping methods
        $enabled_methods = array();
        foreach ($config_data['POS']['shipping'] as $method) {
            if (isset($method['Enabled']) && $method['Enabled'] === true) {
                $enabled_methods[] = array(
                    'hpay_site_method_id' => $method['HPaySiteMethodId'], // CRITICAL: Use HPaySiteMethodId
                    'hpay_id' => $method['Uid'],
                    'method_code' => $method['ShippingMethod'],
                    'method_name' => $method['SystemTitle'] ?? $method['Uid'], // Use SystemTitle instead of Name
                    'description' => isset($method['Description']) ? $method['Description'] : '',
                    'sort_order' => isset($method['Order']) ? $method['Order'] : 0,
                    'config_data' => json_encode($method)
                );
            }
        }
        
        // Sort by order
        usort($enabled_methods, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });
        
        return $enabled_methods;
    }
    
    private function calculateShippingCost($shipping_method, $address) {
        // Get cart data for cost calculation
        $cart_data = $this->getCartDataForShipping();
        $config_data = json_decode($shipping_method['config_data'], true);
        
        if (!$config_data) {
            return 0;
        }
        
        // CRITICAL: Implement all HolestPay shipping cost calculation parameters
        $order_amount = $cart_data['total_value'];
        $order_weight = $cart_data['total_weight'];
        $shipping_currency = isset($config_data['ShippingCurrency']) ? $config_data['ShippingCurrency'] : $this->session->data['currency'];
        
        // 1. FREE ABOVE ORDER AMOUNT
        $free_above_amount = isset($config_data['Free Above Order Amount']) ? (float)$config_data['Free Above Order Amount'] : null;
        if ($free_above_amount !== null && $order_amount >= $free_above_amount) {
            return 0; // Free shipping
        }
        
        $base_cost = 0;
        
        // 2. PRICE TABLE - check if order amount falls into price table ranges
        if (isset($config_data['Price Table']) && is_array($config_data['Price Table'])) {
            foreach ($config_data['Price Table'] as $price_range) {
                $min_amount = isset($price_range['MinOrderTotal']) ? (float)$price_range['MinOrderTotal'] : 0;
                $max_amount = isset($price_range['MaxOrderTotal']) ? (float)$price_range['MaxOrderTotal'] : PHP_FLOAT_MAX;
                
                if ($order_amount >= $min_amount && $order_amount <= $max_amount) {
                    $base_cost = isset($price_range['ShippingCost']) ? (float)$price_range['ShippingCost'] : 0;
                    break;
                }
            }
        }
        
        // 3. PRICE MULTIPLICATION - multiply base cost by order amount percentage
        if (isset($config_data['Price Multiplication']) && is_array($config_data['Price Multiplication'])) {
            foreach ($config_data['Price Multiplication'] as $multiplication_range) {
                $min_cart_total = isset($multiplication_range['MinCartTotal']) ? (float)$multiplication_range['MinCartTotal'] : 0;
                
                if ($order_amount >= $min_cart_total) {
                    $multiplication = isset($multiplication_range['Multiplication']) ? (float)$multiplication_range['Multiplication'] : 1.0;
                    $base_cost *= $multiplication;
                }
            }
        }
        
        // 4. ADDITIONAL COST
        if (isset($config_data['Additional cost'])) {
            $base_cost += (float)$config_data['Additional cost'];
        }
        
        // 5. COD COST - add if payment method is Cash on Delivery
        $payment_method = isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : '';
        if (strpos($payment_method, 'cod') !== false && isset($config_data['COD cost'])) {
            $base_cost += (float)$config_data['COD cost'];
        }
        
        // 6. AFTER MAX WEIGHT PRICE PER KG
        $max_weight = isset($config_data['MaxWeight']) ? (float)$config_data['MaxWeight'] : 0;
        if ($max_weight > 0 && $order_weight > $max_weight) {
            $excess_weight = $order_weight - $max_weight;
            $price_per_kg = isset($config_data['After Max Weight Price Per Kg']) ? (float)$config_data['After Max Weight Price Per Kg'] : 0;
            $base_cost += $excess_weight * $price_per_kg;
        }
        
        // Convert currency if needed
        if ($shipping_currency !== $this->session->data['currency']) {
            $this->load->model('localisation/currency');
            $base_cost = $this->currency->convert($base_cost, $shipping_currency, $this->session->data['currency']);
        }
        
        return max(0, $base_cost); // Ensure non-negative cost
    }
    
    private function getCartDataForShipping() {
        $cart_data = array(
            'total_weight' => 0,
            'total_volume' => 0,
            'total_value' => 0,
            'items' => array()
        );
        
        foreach ($this->cart->getProducts() as $product) {
            $this->load->model('catalog/product');
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);
            
            if ($product_info) {
                $weight = (float)$product_info['weight'] * $product['quantity'];
                $length = (float)$product_info['length'];
                $width = (float)$product_info['width'];
                $height = (float)$product_info['height'];
                $volume = $length * $width * $height * $product['quantity'];
                
                $cart_data['total_weight'] += $weight;
                $cart_data['total_volume'] += $volume;
                $cart_data['total_value'] += $product['total'];
                
                $cart_data['items'][] = array(
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'quantity' => $product['quantity'],
                    'weight' => $weight,
                    'volume' => $volume,
                    'value' => $product['total'],
                    'dimensions' => array(
                        'length' => $length,
                        'width' => $width,
                        'height' => $height
                    )
                );
            }
        }
        
        return $cart_data;
    }
    
    private function requestShippingCost($calculation_request) {
        // This method would make an API call to HolestPay to calculate shipping cost
        // For now, we'll implement a simple calculation based on weight and distance
        
        $config_data = json_decode($calculation_request['shipping_method_id'], true);
        if (!$config_data) {
            return false;
        }
        
        $base_cost = isset($config_data['base_cost']) ? (float)$config_data['base_cost'] : 5.00;
        $weight_rate = isset($config_data['weight_rate']) ? (float)$config_data['weight_rate'] : 0.50;
        $value_rate = isset($config_data['value_rate']) ? (float)$config_data['value_rate'] : 0.01;
        
        $weight_cost = $calculation_request['cart']['total_weight'] * $weight_rate;
        $value_cost = $calculation_request['cart']['total_value'] * $value_rate;
        
        $total_cost = $base_cost + $weight_cost + $value_cost;
        
        // Apply any zone-specific multipliers
        $zone_multiplier = $this->getZoneMultiplier($calculation_request['destination']);
        $total_cost *= $zone_multiplier;
        
        return round($total_cost, 2);
    }
    
    private function getZoneMultiplier($destination) {
        // Simple zone-based multiplier
        // In a real implementation, this would be more sophisticated
        
        $domestic_countries = array('US', 'CA'); // Example domestic countries
        
        if (in_array($destination['country'], $domestic_countries)) {
            return 1.0; // Domestic shipping
        } else {
            return 2.5; // International shipping
        }
    }
    
    public function updateShippingMethods($methods, $environment, $merchant_site_uid) {
        // DEPRECATED: Shipping methods are now stored in POS configuration in holestpay_config table
        // This method is kept for backward compatibility but does nothing
        // Shipping methods are now managed through the POS configuration
        
        error_log("HolestPay: updateShippingMethods() called but shipping methods are now managed via POS configuration");
        return true;
    }
    
    public function getShippingMethodByCode($method_code) {
        // Load HolestPay payment model to access POS configuration
        $this->load->model('payment/holestpay');
        $config_data = $this->model_payment_holestpay->getHolestPayConfig();
        
        if (!$config_data || !isset($config_data['POS']['shipping'])) {
            return false;
        }
        
        // Find shipping method by method code in POS configuration
        if (isset($config_data['POS']['shipping']) && is_array($config_data['POS']['shipping'])) {
            foreach ($config_data['POS']['shipping'] as $method_data) {
                if (isset($method_data['ShippingMethod']) && $method_data['ShippingMethod'] === $method_code) {
                    return array(
                        'hpay_id' => $method_data['Uid'],
                        'method_name' => $method_data['SystemTitle'] ?? $method_data['Uid'],
                        'method_code' => $method_data['ShippingMethod'],
                        'cost' => 0, // Cost calculated elsewhere
                        'tax_class_id' => 0,
                        'enabled' => $method_data['Enabled'] ?? false,
                        'sort_order' => $method_data['Order'] ?? 0,
                        'environment' => $this->config->get('payment_holestpay_environment'),
                        'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
                        'hpay_site_method_id' => $method_data['HPaySiteMethodId'],
                        'config_data' => json_encode($method_data)
                    );
                }
            }
        }
        
        return false;
    }
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Catalog\Model\Extension\Holestpay\Shipping\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/shipping/holestpay.php";
}
