<?php
namespace Opencart\Admin\Controller\Extension\Holestpay\Payment;

// Ensure the main controller is loaded
if (!class_exists('\Opencart\Admin\Controller\Payment\Holestpay')) {
    require_once __DIR__ . '/../../../payment/holestpay.php';
}

// Create alias/proxy class that extends the main controller
if (!class_exists('\Opencart\Admin\Controller\Extension\Holestpay\Payment\Holestpay')) {
    class Holestpay extends \Opencart\Admin\Controller\Payment\Holestpay {
        // This class simply extends the main controller
        // All functionality is inherited from the parent class
    }
}
?>