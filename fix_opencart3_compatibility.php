<?php
/**
 * HolestPay OpenCart 3 Compatibility Fix Script
 * This script helps fix OpenCart 3 compatibility issues
 */

// Detect OpenCart installation
$opencart_root = dirname(__FILE__);
$admin_dir = $opencart_root . '/admin';
$catalog_dir = $opencart_root . '/catalog';

// Check if we're in the right directory
if (!file_exists($admin_dir) || !file_exists($catalog_dir)) {
    die("Error: This script must be run from the OpenCart root directory.\n");
}

echo "HolestPay OpenCart 3 Compatibility Fix\n";
echo "=====================================\n\n";

// Check OpenCart version
$version = 'Unknown';
if (file_exists($opencart_root . '/config.php')) {
    include_once($opencart_root . '/config.php');
    if (defined('VERSION')) {
        $version = VERSION;
    }
}

echo "OpenCart Version: " . $version . "\n";

// Detect if it's OpenCart 3
$is_opencart3 = false;
if (version_compare($version, '4.0.0.0', '<')) {
    $is_opencart3 = true;
    echo "Detected: OpenCart 3.x\n\n";
} else {
    echo "Detected: OpenCart 4.x or higher\n";
    echo "This script is only needed for OpenCart 3.x\n";
    exit(0);
}

// Check if HolestPay files exist
$files_to_check = array(
    'admin/controller/payment/holestpay_opencart3.php',
    'admin/model/payment/holestpay_opencart3.php',
    'catalog/controller/payment/holestpay_opencart3.php'
);

$missing_files = array();
foreach ($files_to_check as $file) {
    if (!file_exists($opencart_root . '/' . $file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    echo "Error: Missing required files:\n";
    foreach ($missing_files as $file) {
        echo "  - " . $file . "\n";
    }
    echo "\nPlease ensure all HolestPay extension files are uploaded.\n";
    exit(1);
}

echo "All required files found.\n\n";

// Copy OpenCart 3 files to standard locations
$file_mappings = array(
    'admin/controller/payment/holestpay_opencart3.php' => 'admin/controller/payment/holestpay.php',
    'admin/model/payment/holestpay_opencart3.php' => 'admin/model/payment/holestpay.php',
    'catalog/controller/payment/holestpay_opencart3.php' => 'catalog/controller/payment/holestpay.php'
);

echo "Copying OpenCart 3 compatible files...\n";

$success_count = 0;
$error_count = 0;

foreach ($file_mappings as $source => $destination) {
    $source_path = $opencart_root . '/' . $source;
    $dest_path = $opencart_root . '/' . $destination;
    
    if (copy($source_path, $dest_path)) {
        echo "  ✓ Copied: " . $destination . "\n";
        $success_count++;
    } else {
        echo "  ✗ Failed: " . $destination . "\n";
        $error_count++;
    }
}

echo "\n";

if ($error_count > 0) {
    echo "Some files could not be copied. Please check file permissions.\n";
    echo "Required permissions: 755 for directories, 644 for files\n\n";
    
    echo "Manual commands:\n";
    foreach ($file_mappings as $source => $destination) {
        echo "cp " . $source . " " . $destination . "\n";
    }
} else {
    echo "All files copied successfully!\n";
}

echo "\nNext steps:\n";
echo "1. Go to your OpenCart admin panel\n";
echo "2. Navigate to Extensions > Extensions\n";
echo "3. Select 'Payments' from the dropdown\n";
echo "4. Look for 'HolestPay' in the list\n";
echo "5. Click 'Install' and then 'Edit' to configure\n\n";

if ($error_count == 0) {
    echo "✓ OpenCart 3 compatibility fix completed successfully!\n";
} else {
    echo "⚠ Please fix the file permission issues and run this script again.\n";
}
?>
