# HolestPay OpenCart 3 Installation Guide

## Overview
This guide explains how to install the HolestPay payment extension on OpenCart 3.x.

## Installation Steps

### Method 1: Extension Installer (Recommended)
1. **Upload Extension**: Go to `Extensions > Installer` in your OpenCart 3 admin panel
2. **Upload the .ocmod.zip file** containing the HolestPay extension
3. **Install Extension**: Go to `Extensions > Extensions` and select "Payments" from the dropdown
4. **Find HolestPay** in the list and click the "Install" button
5. **Configure**: Click the "Edit" button to configure your HolestPay settings

### Method 2: Manual Installation
1. **Upload Files**: Upload all extension files to your OpenCart installation directory
2. **Run Install Script**: Execute `install.php` from your browser or command line
3. **Refresh Modifications**: Go to `Extensions > Modifications` and click "Refresh"
4. **Install Extension**: Go to `Extensions > Extensions > Payments` and install HolestPay

## Configuration
1. **Merchant Site UID**: Enter your HolestPay merchant site UID
2. **Secret Key**: Enter your HolestPay secret key
3. **Environment**: Choose between Sandbox or Production
4. **Order Status**: Set the order status for successful payments
5. **Geo Zone**: Select which regions can use this payment method

## Troubleshooting

### Extension Not Appearing in Extensions > Payments
If the HolestPay extension doesn't appear in the Extensions > Payments list:

1. **Check File Permissions**: Ensure the following directories are writable (755 or 775):
   - `admin/controller/payment/`
   - `admin/model/payment/`
   - `admin/language/en-gb/payment/`
   - `catalog/controller/payment/`
   - `catalog/language/en-gb/payment/`

2. **Clear Cache**: Clear your OpenCart cache:
   - Go to `Dashboard > Gear Icon > Developer Settings`
   - Click "Refresh" for both Theme and SASS caches

3. **Check Error Logs**: Look for errors in `System > Maintenance > Error Logs`

4. **Verify File Structure**: Ensure all files are in the correct locations:
   - `admin/controller/payment/holestpay_opencart3.php`
   - `admin/model/payment/holestpay_opencart3.php`
   - `catalog/controller/payment/holestpay_opencart3.php`

### Manual File Copy (if needed)
If the extension still doesn't appear, manually copy the OpenCart 3 specific files:

```bash
# Copy admin controller
cp admin/controller/payment/holestpay_opencart3.php admin/controller/payment/holestpay.php

# Copy admin model  
cp admin/model/payment/holestpay_opencart3.php admin/model/payment/holestpay.php

# Copy catalog controller
cp catalog/controller/payment/holestpay_opencart3.php catalog/controller/payment/holestpay.php
```

## OpenCart 3 vs 4 Differences
- **Namespace**: OpenCart 3 uses class-based structure (`ControllerPaymentHolestpay`)
- **OpenCart 4 uses namespaces** (`Opencart\Admin\Controller\Payment\Holestpay`)
- **URL Structure**: Different URL patterns for admin and catalog
- **Template Engine**: OpenCart 3 may use .tpl files instead of .twig

## Support
If you continue to have issues:
1. Check the error logs in your OpenCart admin panel
2. Verify your OpenCart version is 3.0.0.0 or higher
3. Ensure all required PHP extensions are installed (curl, json, openssl)
4. Contact HolestPay support with specific error messages

## Files Modified
- `admin/controller/payment/holestpay_opencart3.php` - OpenCart 3 admin controller
- `admin/model/payment/holestpay_opencart3.php` - OpenCart 3 admin model
- `catalog/controller/payment/holestpay_opencart3.php` - OpenCart 3 catalog controller
- `install.json` - Updated compatibility to 3.0.0.0+
- `install.php` - Added OpenCart 3 detection and file copying
