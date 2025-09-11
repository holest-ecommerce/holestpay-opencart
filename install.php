<?php
/**
 * HolestPay OpenCart Extension Installation Script
 * This script handles proper installation of template files and database setup
 */

class HolestPayInstaller {
    
    private $db;
    private $config;
    
    public function __construct($db, $config = null) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Install the HolestPay extension
     */
    public function install() {
        try {
            // 1. Copy template files to correct locations
            $this->copyTemplateFiles();
            
            // 2. Copy language files to correct locations  
            $this->copyLanguageFiles();
            
            // 3. Copy JavaScript files to correct locations
            $this->copyJavaScriptFiles();
            
            // 4. Create database tables
            $this->createDatabaseTables();
            
            // 5. Add order table modifications
            $this->addOrderTableModifications();
            
            // 6. Set default configuration
            $this->setDefaultConfiguration();
            
            return array('success' => true, 'message' => 'HolestPay extension installed successfully for OpenCart 4');
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Copy template files from extension directory to standard locations
     */
    private function copyTemplateFiles() {
        $template_files = array(
            // Catalog templates
            'catalog/view/template/extension/holestpay/payment/holestpay.twig' => 'catalog/view/template/payment/holestpay.twig',
            'catalog/view/template/payment/holestpay_error.twig' => 'catalog/view/template/payment/holestpay_error.twig',
            'catalog/view/template/payment/holestpay_order_result.twig' => 'catalog/view/template/payment/holestpay_order_result.twig',
            
            // Admin templates
            'admin/view/template/extension/holestpay/payment/holestpay.twig' => 'admin/view/template/payment/holestpay.twig'
        );
        
        foreach ($template_files as $source => $destination) {
            if (file_exists(DIR_OPENCART . $source)) {
                $dest_dir = dirname(DIR_OPENCART . $destination);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                
                if (!copy(DIR_OPENCART . $source, DIR_OPENCART . $destination)) {
                    throw new Exception("Failed to copy template file: {$source} to {$destination}");
                }
            }
        }
    }
    
    /**
     * Copy language files from extension directory to standard locations
     */
    private function copyLanguageFiles() {
        $language_files = array(
            // Catalog language files
            'catalog/language/en-gb/extension/holestpay/payment/holestpay.php' => 'catalog/language/en-gb/payment/holestpay.php',
            'catalog/language/en-gb/extension/holestpay/shipping/holestpay.php' => 'catalog/language/en-gb/shipping/holestpay.php',
            'catalog/language/sr-rs/extension/holestpay/payment/holestpay.php' => 'catalog/language/sr-rs/payment/holestpay.php',
            'catalog/language/sr-rs/extension/holestpay/shipping/holestpay.php' => 'catalog/language/sr-rs/shipping/holestpay.php',
            'catalog/language/sr-yu/extension/holestpay/payment/holestpay.php' => 'catalog/language/sr-yu/payment/holestpay.php',
            'catalog/language/sr-yu/extension/holestpay/shipping/holestpay.php' => 'catalog/language/sr-yu/shipping/holestpay.php',
            'catalog/language/mk-mk/extension/holestpay/payment/holestpay.php' => 'catalog/language/mk-mk/payment/holestpay.php',
            'catalog/language/mk-mk/extension/holestpay/shipping/holestpay.php' => 'catalog/language/mk-mk/shipping/holestpay.php',
            
            // Admin language files
            'admin/language/en-gb/extension/holestpay/payment/holestpay.php' => 'admin/language/en-gb/payment/holestpay.php'
        );
        
        foreach ($language_files as $source => $destination) {
            if (file_exists(DIR_OPENCART . $source)) {
                $dest_dir = dirname(DIR_OPENCART . $destination);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                
                if (!copy(DIR_OPENCART . $source, DIR_OPENCART . $destination)) {
                    throw new Exception("Failed to copy language file: {$source} to {$destination}");
                }
            }
        }
    }
    
    /**
     * Copy JavaScript files to correct locations
     */
    private function copyJavaScriptFiles() {
        $js_files = array(
            'catalog/view/javascript/holestpay-checkout.js' => 'catalog/view/javascript/holestpay-checkout.js',
            'admin/view/javascript/holestpay-admin.js' => 'admin/view/javascript/holestpay-admin.js'
        );
        
        foreach ($js_files as $source => $destination) {
            if (file_exists(DIR_OPENCART . $source)) {
                $dest_dir = dirname(DIR_OPENCART . $destination);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                
                if (!copy(DIR_OPENCART . $source, DIR_OPENCART . $destination)) {
                    throw new Exception("Failed to copy JavaScript file: {$source} to {$destination}");
                }
            }
        }
    }
    
    /**
     * Create HolestPay database tables
     */
    private function createDatabaseTables() {
        // HolestPay configuration table
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
        
        // HolestPay vault tokens table
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
        
        // HolestPay subscriptions table
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
    }
    
    /**
     * Add HolestPay fields to order table
     */
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
    
    /**
     * Set default configuration values
     */
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
    
    /**
     * Uninstall the HolestPay extension
     */
    public function uninstall() {
        try {
            // Remove copied files
            $this->removeInstalledFiles();
            
            // Optionally remove database tables (commented out to preserve data)
            // $this->removeDatabaseTables();
            
            // Remove configuration settings
            $this->removeConfiguration();
            
            return array('success' => true, 'message' => 'HolestPay extension uninstalled successfully');
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Remove installed files
     */
    private function removeInstalledFiles() {
        $files_to_remove = array(
            'catalog/view/template/payment/holestpay.twig',
            'catalog/view/template/payment/holestpay_error.twig', 
            'catalog/view/template/payment/holestpay_order_result.twig',
            'admin/view/template/payment/holestpay.twig',
            'catalog/view/javascript/holestpay-checkout.js',
            'admin/view/javascript/holestpay-admin.js'
        );
        
        foreach ($files_to_remove as $file) {
            $file_path = DIR_OPENCART . $file;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    /**
     * Remove configuration settings
     */
    private function removeConfiguration() {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'payment_holestpay'");
    }
}

// Auto-run installation if this file is accessed directly
if (defined('DIR_OPENCART') && isset($db)) {
    $installer = new HolestPayInstaller($db);
    $result = $installer->install();
    
    if ($result['success']) {
        echo "HolestPay Extension Installation: SUCCESS - " . $result['message'];
    } else {
        echo "HolestPay Extension Installation: ERROR - " . $result['error'];
    }
}
