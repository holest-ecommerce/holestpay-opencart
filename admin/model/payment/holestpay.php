<?php
namespace Opencart\Admin\Model\Payment;

class Holestpay extends \Opencart\System\Engine\Model {
    
    public function install() {
        try {
            // Create HolestPay configuration table for storing large JSON configurations
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_config` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(20) NOT NULL,
                `merchant_site_uid` varchar(100) NOT NULL,
                `config_data` MEDIUMTEXT,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `env_merchant` (`environment`, `merchant_site_uid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
        
            // Add HolestPay specific fields to order table (with individual column checks)
            try {
                $check_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_uid'");
                if ($check_query->num_rows == 0) {
                    $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD COLUMN `hpay_uid` VARCHAR(100) NULL AFTER `order_id`");
                }
            } catch (Throwable $e) {
                error_log('HolestPay Install - Could not add hpay_uid column: ' . $e->getMessage());
            }
            
            try {
                $check_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_status'");
                if ($check_query->num_rows == 0) {
                    $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD COLUMN `hpay_status` TEXT NULL AFTER `hpay_uid`");
                }
            } catch (Throwable $e) {
                error_log('HolestPay Install - Could not add hpay_status column: ' . $e->getMessage());
            }
            
            try {
                $check_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'hpay_data'");
                if ($check_query->num_rows == 0) {
                    $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD COLUMN `hpay_data` MEDIUMTEXT NULL AFTER `hpay_status`");
                }
            } catch (Throwable $e) {
                error_log('HolestPay Install - Could not add hpay_data column: ' . $e->getMessage());
            }
        
        // Create HolestPay payment methods table for sub-payment methods
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_payment_methods` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hpay_id` varchar(50) NOT NULL,
            `environment` varchar(20) NOT NULL,
            `merchant_site_uid` varchar(100) NOT NULL,
            `method_name` varchar(255) NOT NULL,
            `method_code` varchar(50) NOT NULL,
            `enabled` tinyint(1) NOT NULL DEFAULT '1',
            `sort_order` int(3) NOT NULL DEFAULT '1',
            `supports_mit` tinyint(1) NOT NULL DEFAULT '0',
            `supports_cof` tinyint(1) NOT NULL DEFAULT '0',
            `config_data` TEXT,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `method_env` (`method_code`, `environment`, `merchant_site_uid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
        
        // Create HolestPay shipping methods table
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_shipping_methods` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hpay_id` varchar(50) NOT NULL,
            `environment` varchar(20) NOT NULL,
            `merchant_site_uid` varchar(100) NOT NULL,
            `method_name` varchar(255) NOT NULL,
            `method_code` varchar(50) NOT NULL,
            `enabled` tinyint(1) NOT NULL DEFAULT '1',
            `sort_order` int(3) NOT NULL DEFAULT '1',
            `config_data` TEXT,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `shipping_method_env` (`method_code`, `environment`, `merchant_site_uid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
        
        // Create vault tokens table for subscription support
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_vault_tokens` (
            `vault_token_id` int(11) NOT NULL AUTO_INCREMENT,
            `customer_id` int(11) NOT NULL,
            `customer_email` varchar(255) NOT NULL,
            `merchant_site_uid` varchar(100) NOT NULL,
            `vault_token_uid` varchar(100) NOT NULL,
            `vault_card_mask` varchar(50) NOT NULL,
            `payment_method_id` varchar(50) NOT NULL,
            `vault_data` TEXT,
            `is_default` tinyint(1) NOT NULL DEFAULT '0',
            `enabled` tinyint(1) NOT NULL DEFAULT '1',
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`vault_token_id`),
            KEY `customer_email` (`customer_email`),
            KEY `customer_token` (`customer_id`, `vault_token_uid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
        
        // Create subscriptions table
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "holestpay_subscriptions` (
            `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `subscription_uid` varchar(100) NOT NULL,
            `vault_token_uid` varchar(100) NOT NULL,
            `payment_method_id` varchar(50) NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'active',
            `amount` decimal(15,4) NOT NULL,
            `currency` varchar(3) NOT NULL,
            `frequency` varchar(20) NOT NULL,
            `next_payment_date` datetime NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`subscription_id`),
            UNIQUE KEY `subscription_uid` (`subscription_uid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
            
        } catch (Throwable $e) {
            error_log('HolestPay Install Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Don't re-throw - installation should continue even with some errors
        }
    }
    
    public function uninstall() {
        try {
            // Remove custom order fields (MySQL-compatible syntax)
            try {
                // Check if columns exist before dropping them
                $columns_to_drop = ['hpay_uid', 'hpay_status', 'hpay_data'];
                
                foreach ($columns_to_drop as $column) {
                    $check_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE '" . $column . "'");
                    if ($check_query->num_rows > 0) {
                        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP COLUMN `" . $column . "`");
                    }
                }
            } catch (Throwable $e) {
                // Ignore errors if columns don't exist
                error_log("HolestPay uninstall: Could not remove order columns - " . $e->getMessage());
            }
            
            // Drop HolestPay tables
            try {
                $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_config`");
                $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_payment_methods`");
                $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_shipping_methods`");
                $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_vault_tokens`");
                $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "holestpay_subscriptions`");
            } catch (Throwable $e) {
                error_log("HolestPay uninstall: Could not drop tables - " . $e->getMessage());
            }
            
        } catch (Throwable $e) {
            error_log('HolestPay Uninstall Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            // Don't re-throw - module must be uninstallable even with errors
        }
    }
    
    public function getHolestPayConfig($environment, $merchant_site_uid) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "holestpay_config` 
            WHERE `environment` = '" . $this->db->escape($environment) . "' 
            AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        
        if ($query->num_rows) {
            return $query->row;
        }
        
        return false;
    }
    
    public function saveHolestPayConfig($environment, $merchant_site_uid, $config_data) {
        $existing = $this->getHolestPayConfig($environment, $merchant_site_uid);
        
        if ($existing) {
            $this->db->query("UPDATE `" . DB_PREFIX . "holestpay_config` SET 
                `config_data` = '" . $this->db->escape($config_data) . "',
                `date_modified` = NOW()
                WHERE `environment` = '" . $this->db->escape($environment) . "' 
                AND `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "holestpay_config` SET 
                `environment` = '" . $this->db->escape($environment) . "',
                `merchant_site_uid` = '" . $this->db->escape($merchant_site_uid) . "',
                `config_data` = '" . $this->db->escape($config_data) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()");
        }
    }
    
    public function getPaymentMethods($environment, $merchant_site_uid) {
        $config = $this->getHolestPayConfig($environment, $merchant_site_uid);
        if (!$config || !$config['config_data']) {
            return array();
        }
        
        $config_data = json_decode($config['config_data'], true);
        if (!$config_data || !isset($config_data['payment']) || !is_array($config_data['payment'])) {
            return array();
        }
        
        // Filter enabled payment methods and sort by Order
        $payment_methods = array_filter($config_data['payment'], function($method) {
            return isset($method['Enabled']) && $method['Enabled'] === true;
        });
        
        // Sort by Order field
        usort($payment_methods, function($a, $b) {
            return ($a['Order'] ?? 0) - ($b['Order'] ?? 0);
        });
        
        return $payment_methods;
    }
    
    public function getShippingMethods($environment, $merchant_site_uid) {
        $config = $this->getHolestPayConfig($environment, $merchant_site_uid);
        if (!$config || !$config['config_data']) {
            return array();
        }
        
        $config_data = json_decode($config['config_data'], true);
        if (!$config_data || !isset($config_data['shipping']) || !is_array($config_data['shipping'])) {
            return array();
        }
        
        // Filter enabled shipping methods and sort by Order
        $shipping_methods = array_filter($config_data['shipping'], function($method) {
            return isset($method['Enabled']) && $method['Enabled'] === true;
        });
        
        // Sort by Order field
        usort($shipping_methods, function($a, $b) {
            return ($a['Order'] ?? 0) - ($b['Order'] ?? 0);
        });
        
        return $shipping_methods;
    }
    
    public function updateOrderHPayData($order_id, $hpay_uid = null, $hpay_status = null, $hpay_data = null) {
        $updates = array();
        
        if ($hpay_uid !== null) {
            $updates[] = "`hpay_uid` = '" . $this->db->escape($hpay_uid) . "'";
        }
        
        if ($hpay_status !== null) {
            $updates[] = "`hpay_status` = '" . $this->db->escape($hpay_status) . "'";
        }
        
        if ($hpay_data !== null) {
            $updates[] = "`hpay_data` = '" . $this->db->escape($hpay_data) . "'";
        }
        
        if (!empty($updates)) {
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET " . implode(', ', $updates) . " 
                WHERE `order_id` = '" . (int)$order_id . "'");
        }
    }
    
    public function getOrderHPayData($order_id) {
        $query = $this->db->query("SELECT `hpay_uid`, `hpay_status`, `hpay_data` FROM `" . DB_PREFIX . "order` 
            WHERE `order_id` = '" . (int)$order_id . "'");
        
        if ($query->num_rows) {
            return $query->row;
        }
        
        return false;
    }
}

// Ensure extension alias is also available
if (!class_exists('\Opencart\Admin\Model\Extension\Holestpay\Payment\Holestpay')) {
    require_once __DIR__ . "/../extension/holestpay/payment/holestpay.php";
}
