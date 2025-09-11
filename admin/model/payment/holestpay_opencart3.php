<?php
/**
 * OpenCart 3 Compatible HolestPay Payment Model
 * This file provides OpenCart 3 compatibility for the HolestPay extension
 */

class ModelPaymentHolestpay extends Model {
    
    public function install() {
        try {
            // Create HolestPay configuration table
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_config` (
                `config_id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(20) NOT NULL DEFAULT 'sandbox',
                `merchant_site_uid` varchar(255) NOT NULL,
                `config_data` longtext,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`config_id`),
                UNIQUE KEY `environment_merchant` (`environment`, `merchant_site_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
            
            // Create HolestPay vault tokens table
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_vault_tokens` (
                `token_id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_id` int(11) NOT NULL,
                `vault_token_uid` varchar(255) NOT NULL,
                `card_mask` varchar(50) NOT NULL,
                `payment_method_id` varchar(100) NOT NULL,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`token_id`),
                UNIQUE KEY `customer_token` (`customer_id`, `vault_token_uid`),
                KEY `customer_id` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
            
            // Create HolestPay subscriptions table
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_subscriptions` (
                `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `customer_id` int(11) NOT NULL,
                `vault_token_uid` varchar(255) NOT NULL,
                `subscription_data` longtext,
                `next_charge_date` datetime NOT NULL,
                `charge_attempts` int(11) NOT NULL DEFAULT 0,
                `max_charge_attempts` int(11) NOT NULL DEFAULT 3,
                `status` enum('active','paused','cancelled','failed') NOT NULL DEFAULT 'active',
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`subscription_id`),
                KEY `order_id` (`order_id`),
                KEY `customer_id` (`customer_id`),
                KEY `next_charge_date` (`next_charge_date`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
            
            // Add HolestPay fields to order table
            $this->addOrderTableModifications();
            
            // Set default configuration
            $this->setDefaultConfiguration();
            
            $this->log->write('HolestPay: Extension installed successfully');
            
        } catch (Exception $e) {
            $this->log->write('HolestPay Install Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function uninstall() {
        try {
            // Remove configuration settings
            $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'payment_holestpay'");
            
            // Optionally remove database tables (commented out to preserve data)
            // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_config`");
            // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_vault_tokens`");
            // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_subscriptions`");
            
            $this->log->write('HolestPay: Extension uninstalled successfully');
            
        } catch (Exception $e) {
            $this->log->write('HolestPay Uninstall Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function addOrderTableModifications() {
        // Check if columns already exist
        $result = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_uid'");
        if (!$result->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `hpay_uid` varchar(255) DEFAULT NULL");
        }
        
        $result = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_status'");
        if (!$result->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `hpay_status` varchar(100) DEFAULT ''");
        }
        
        $result = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_data'");
        if (!$result->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `hpay_data` longtext");
        }
    }
    
    private function setDefaultConfiguration() {
        $default_config = array(
            'payment_holestpay_status' => 1,
            'payment_holestpay_environment' => 'sandbox',
            'payment_holestpay_title' => 'HolestPay Payment Gateway',
            'payment_holestpay_description' => 'Pay securely with HolestPay using various payment methods',
            'payment_holestpay_sort_order' => 1,
            'payment_holestpay_geo_zone_id' => 0,
            'payment_holestpay_order_status_id' => 5, // Complete
            'payment_holestpay_order_status_failed_id' => 10 // Failed
        );
        
        foreach ($default_config as $key => $value) {
            $existing = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->db->escape($key) . "'");
            if (!$existing->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET 
                    `store_id` = 0,
                    `code` = 'payment_holestpay',
                    `key` = '" . $this->db->escape($key) . "',
                    `value` = '" . $this->db->escape($value) . "',
                    `serialized` = 0");
            }
        }
    }
    
    // Add HolestPay order management methods
    public function addHolestPayOrderFields($order_id, $hpay_uid = null, $hpay_status = '', $hpay_data = '') {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `hpay_uid` = '" . $this->db->escape($hpay_uid ?: $order_id) . "',
            `hpay_status` = '" . $this->db->escape($hpay_status) . "',
            `hpay_data` = '" . $this->db->escape($hpay_data) . "'
            WHERE `order_id` = '" . (int)$order_id . "'");
    }
    
    public function updateHolestPayOrderStatus($order_id, $hpay_status) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `hpay_status` = '" . $this->db->escape($hpay_status) . "'
            WHERE `order_id` = '" . (int)$order_id . "'");
    }
    
    public function updateHolestPayOrderData($order_id, $hpay_data) {
        // Get existing data
        $query = $this->db->query("SELECT `hpay_data` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        if ($query->num_rows) {
            $existing_data = json_decode($query->row['hpay_data'], true) ?: array();
            $new_data = json_decode($hpay_data, true) ?: array();
            $merged_data = array_merge($existing_data, $new_data);
            
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
                `hpay_data` = '" . $this->db->escape(json_encode($merged_data)) . "'
                WHERE `order_id` = '" . (int)$order_id . "'");
        }
    }
    
    public function getHolestPayOrderData($order_id) {
        $query = $this->db->query("SELECT `hpay_uid`, `hpay_status`, `hpay_data` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");
        
        if ($query->num_rows) {
            return array(
                'hpay_uid' => $query->row['hpay_uid'],
                'hpay_status' => $query->row['hpay_status'],
                'hpay_data' => json_decode($query->row['hpay_data'], true) ?: array()
            );
        }
        
        return array('hpay_uid' => null, 'hpay_status' => '', 'hpay_data' => array());
    }
}
