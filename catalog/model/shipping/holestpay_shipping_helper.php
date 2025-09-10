<?php
namespace Opencart\Catalog\Model\Payment;
/**
 * HolestPay Shipping Helper Class
 * Simple PHP class for calculating HolestPay shipping costs
 */
class HolestPayShippingHelper {
    
    private $db;
    private $config;
    private $cart;
    private $session;
    private $currency;
    
    public function __construct($registry) {
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->cart = $registry->get('cart');
        $this->session = $registry->get('session');
        $this->currency = $registry->get('currency');
    }
    
    /**
     * Get HolestPay shipping methods from configuration
     */
    public function getHolestPayShippingMethods($address) {
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
                    'hpay_site_method_id' => $method['HPaySiteMethodId'],
                    'hpay_id' => $method['HPaySiteMethodId'],
                    'method_code' => $method['Uid'],
                    'method_name' => $method['Name'],
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
    
    /**
     * Calculate shipping cost for a specific method
     */
    public function calculateShippingCost($method_data, $weight, $cart_amount, $cart_currency, $address) {
        try {
            $cost = 0.00;
            $config_data = json_decode($method_data['config_data'], true);
            
            if (!$config_data) {
                return 0.00;
            }
            
            // Check if shipping is free above certain order amount
            if (isset($config_data['Free Above Order Amount']) && $config_data['Free Above Order Amount']) {
                $free_threshold = (float)$config_data['Free Above Order Amount'];
                if ($cart_amount >= $free_threshold) {
                    return 0.00;
                }
            }
            
            // Calculate cost based on weight and price table
            if (isset($config_data['Price Table']) && !empty($config_data['Price Table'])) {
                $price_table = $config_data['Price Table'];
                
                // Sort by max weight
                usort($price_table, function($a, $b) {
                    return (int)($a['MaxWeight'] ?? 0) - (int)($b['MaxWeight'] ?? 0);
                });
                
                $cost_found = false;
                $max_cost = 0;
                $max_weight = 0;
                
                foreach ($price_table as $weight_rate) {
                    if ($weight <= (int)$weight_rate['MaxWeight']) {
                        $cost = (float)$weight_rate['Price'];
                        $cost_found = true;
                        break;
                    }
                    $max_cost = (float)$weight_rate['Price'];
                    $max_weight = (int)$weight_rate['MaxWeight'];
                }
                
                // If weight exceeds max in table, calculate additional cost
                if (!$cost_found && isset($config_data['After Max Weight Price Per Kg'])) {
                    $additional_weight = $weight - $max_weight;
                    $additional_cost = ($additional_weight / 1000) * (float)$config_data['After Max Weight Price Per Kg'];
                    $cost = $max_cost + $additional_cost;
                }
            }
            
            // Add COD cost if applicable
            if (isset($config_data['COD Cost']) && $config_data['COD Cost']) {
                $cod_cost = $config_data['COD Cost'];
                if (strpos($cod_cost, '%') !== false) {
                    $percentage = (float)str_replace(['%', ' '], '', $cod_cost);
                    $cost *= (1.00 + $percentage / 100);
                } else {
                    $cost += (float)$cod_cost;
                }
            }
            
            // Add additional cost
            if (isset($config_data['Additional Cost']) && $config_data['Additional Cost']) {
                $additional_cost = $config_data['Additional Cost'];
                if (strpos($additional_cost, '%') !== false) {
                    $percentage = (float)str_replace(['%', ' '], '', $additional_cost);
                    $cost *= (1.00 + $percentage / 100);
                } else {
                    $cost += (float)$additional_cost;
                }
            }
            
            // Apply price multiplication based on cart amount
            if (isset($config_data['Price Multiplication']) && !empty($config_data['Price Multiplication'])) {
                $price_multiplication = $config_data['Price Multiplication'];
                
                // Sort by minimum cart total
                usort($price_multiplication, function($a, $b) {
                    return (float)($a['MinCartTotal'] ?? 0) - (float)($b['MinCartTotal'] ?? 0);
                });
                
                $multiplication = 1.00;
                foreach ($price_multiplication as $cart_amt_level) {
                    if (empty($cart_amt_level['MinCartTotal']) || !is_numeric($cart_amt_level['MinCartTotal'])) {
                        continue;
                    }
                    
                    $min_cart_total = (float)$cart_amt_level['MinCartTotal'];
                    if ($cart_amount >= $min_cart_total) {
                        if (!empty($cart_amt_level['Multiplication']) && is_numeric($cart_amt_level['Multiplication'])) {
                            $multiplication = (float)$cart_amt_level['Multiplication'];
                        } else {
                            $multiplication = 1.00;
                        }
                    }
                }
                
                if ($multiplication != 1.00) {
                    $cost = $cost * $multiplication;
                }
            }
            
            return round($cost, 2);
            
        } catch (Exception $e) {
            error_log("HolestPay Shipping Cost Calculation Error: " . $e->getMessage());
            return 0.00;
        }
    }
    
    
    /**
     * Get HolestPay language code
     */
    private function getHPayLanguageCode() {
        $language_code = $this->session->data['language'] ?? 'en-gb';
        
        if(stripos($language_code,'yu') !== false ){
            return 'rs';
        }else if(stripos($language_code,'sr') !== false || stripos($language_code,'rs') !== false){
            return 'rs-cyr';
        }else if(stripos($language_code,'mk') !== false ){
            return 'mk';
        }else{
            return strtolower(substr($language_code,0,2));
        }
    }
}