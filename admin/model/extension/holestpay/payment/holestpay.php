<?php
namespace Opencart\Admin\Model\Extension\Holestpay\Payment;

// Ensure the main model is loaded
if (!class_exists('\Opencart\Admin\Model\Payment\Holestpay')) {
    require_once __DIR__ . '/../../../payment/holestpay.php';
}

// Create alias/proxy class that extends the main model
if (!class_exists('\Opencart\Admin\Model\Extension\Holestpay\Payment\Holestpay')) {
    class Holestpay extends \Opencart\Admin\Model\Payment\Holestpay {
        // This class simply extends the main model
        // All functionality is inherited from the parent class
    }
}
?>