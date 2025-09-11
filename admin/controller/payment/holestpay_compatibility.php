<?php
/**
 * HolestPay Compatibility Detection Script
 * This script detects OpenCart version and loads the appropriate controller
 */

// Detect OpenCart version
$opencart_version = '4.0.0.0'; // Default to OpenCart 4

// Check for OpenCart 3 indicators
if (defined('VERSION')) {
    $version_parts = explode('.', VERSION);
    if (count($version_parts) >= 3) {
        $major_version = (int)$version_parts[0];
        if ($major_version < 4) {
            $opencart_version = '3.0.0.0';
        }
    }
}

// Check for namespace indicators (OpenCart 4 uses namespaces)
if (class_exists('Opencart\System\Engine\Controller')) {
    $opencart_version = '4.0.0.0';
} elseif (class_exists('Controller')) {
    $opencart_version = '3.0.0.0';
}

// Load appropriate controller based on version
if ($opencart_version >= '4.0.0.0') {
    // OpenCart 4 - use namespace-based controller
    if (!class_exists('\Opencart\Admin\Controller\Payment\Holestpay')) {
        require_once __DIR__ . '/holestpay.php';
    }
} else {
    // OpenCart 3 - use class-based controller
    if (!class_exists('ControllerPaymentHolestpay')) {
        require_once __DIR__ . '/holestpay_opencart3.php';
    }
}
