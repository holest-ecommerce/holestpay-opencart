<?php
namespace Opencart\Catalog\Model\Extension\Holestpay\Payment;

// Ensure the main model is loaded
if (!class_exists('\Opencart\Catalog\Model\Payment\Holestpay')) {
    require_once __DIR__ . '/../../../payment/holestpay.php';
}

// Create alias/proxy class that extends the main model
if (!class_exists('\Opencart\Catalog\Model\Extension\Holestpay\Payment\Holestpay')) {
    class Holestpay extends \Opencart\Catalog\Model\Payment\Holestpay {
        // This class simply extends the main model
        // All functionality is inherited from the parent class
    }
}
?>