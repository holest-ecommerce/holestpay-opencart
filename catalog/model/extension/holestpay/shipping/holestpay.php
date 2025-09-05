<?php
namespace Opencart\Catalog\Model\Extension\Holestpay\Shipping;

// Ensure the main model is loaded
if (!class_exists('\Opencart\Catalog\Model\Shipping\Holestpay')) {
    require_once __DIR__ . '/../../../shipping/holestpay.php';
}

// Create alias/proxy class that extends the main model
if (!class_exists('\Opencart\Catalog\Model\Extension\Holestpay\Shipping\Holestpay')) {
    class Holestpay extends \Opencart\Catalog\Model\Shipping\Holestpay {
        // This class simply extends the main model
        // All functionality is inherited from the parent class
    }
}
?>