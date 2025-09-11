# HolestPay Payment Gateway for OpenCart 3 & 4

A comprehensive payment and shipping integration module for OpenCart that provides full HolestPay functionality including multiple payment methods, subscriptions, shipping cost calculation, and order management.

> **🔧 OpenCart 3 Compatibility**: This extension includes special compatibility files for OpenCart 3.x. If you're using OpenCart 3 and the extension doesn't appear in Extensions > Payments, see the [OpenCart 3 Installation Guide](#opencart-3-installation) below.

## Features

### Payment Processing
- **Multiple Payment Methods**: Support for all HolestPay payment methods as separate options
- **Subscription Support**: Recurring payments with MIT (Merchant Initiated Transactions) and COF (Card on File)
- **Vault Token Management**: Save and reuse payment methods for faster checkout
- **Request Signing**: Secure communication with digital signatures
- **Real-time Processing**: Immediate payment processing and status updates

### Shipping Integration
- **Multiple Shipping Methods**: HolestPay shipping methods with real-time cost calculation
- **Dynamic Pricing**: Cost calculation based on weight, dimensions, and destination
- **Zone-based Shipping**: Different rates for domestic and international shipping

### Admin Features
- **Order Management**: Complete HolestPay command interface in order details
- **Configuration Management**: Automatic configuration sync via webhooks
- **Status Monitoring**: Real-time order and payment status tracking
- **Webhook Integration**: Automatic order updates from HolestPay system

### Technical Features
- **OpenCart 3 & 4 Compatibility**: Single codebase works with both versions using automatic version detection
- **Multi-environment**: Sandbox and Production environment support
- **Webhook Processing**: Handles configuration, order updates, and payment results
- **Database Integration**: Custom tables for HolestPay data storage
- **JavaScript Integration**: Admin and frontend JavaScript objects
- **Automatic Compatibility**: Detects OpenCart version and loads appropriate controller files

## Installation

### Prerequisites
- OpenCart 3.0.0.0 or higher (OpenCart 3.x and 4.x supported)
- PHP 7.4 or higher
- Required PHP extensions: curl, json, openssl
- SSL certificate (recommended for production)

### For OpenCart 4.x

#### Automatic Installation (Recommended)
1. Download the `holestpay.ocmod.zip` package
2. Go to Extensions → Installer in your OpenCart admin
3. Upload the zip file
4. Go to Extensions → Extensions → Payments
5. Find "HolestPay Payment Gateway" and click Install
6. Click Edit to configure the module

#### Manual Installation
1. Extract the zip file
2. Upload files to your OpenCart directory maintaining the folder structure
3. Go to Extensions → Extensions → Payments
4. Find "HolestPay Payment Gateway" and click Install

## OpenCart 3 Installation

> **⚠️ Important**: OpenCart 3 uses a different controller structure than OpenCart 4. This extension includes special compatibility files to work with both versions.

### Why OpenCart 3 Needs Special Files?

OpenCart 3 and 4 have different controller structures:
- **OpenCart 3**: Uses class-based structure (`ControllerPaymentHolestpay`)
- **OpenCart 4**: Uses namespace-based structure (`Opencart\Admin\Controller\Payment\Holestpay`)

This extension includes both versions and automatically detects which one to use.

### Quick Installation (Recommended)

1. **Download** the `holestpay.ocmod.zip` package
2. **Upload** all files to your OpenCart directory
3. **Run the compatibility fix script**:
   ```bash
   cd /path/to/your/opencart/
   php fix_opencart3_compatibility.php
   ```
4. **Go to admin panel** → Extensions → Extensions → Payments
5. **Find "HolestPay"** and click Install
6. **Click Edit** to configure your settings

### Manual Installation

If the automatic script doesn't work:

1. **Extract** the zip file
2. **Upload** all files to your OpenCart directory
3. **Copy OpenCart 3 compatible files**:
   ```bash
   # Copy admin files
   cp admin/controller/payment/holestpay_opencart3.php admin/controller/payment/holestpay.php
   cp admin/model/payment/holestpay_opencart3.php admin/model/payment/holestpay.php
   
   # Copy catalog files
   cp catalog/controller/payment/holestpay_opencart3.php catalog/controller/payment/holestpay.php
   ```
4. **Go to admin panel** → Extensions → Extensions → Payments
5. **Find "HolestPay"** and click Install

### Troubleshooting

**Problem**: Extension doesn't appear in Extensions > Payments

**Solutions**:
1. **Check file permissions**:
   ```bash
   chmod 755 admin/controller/payment/
   chmod 644 admin/controller/payment/holestpay.php
   chmod 755 admin/model/payment/
   chmod 644 admin/model/payment/holestpay.php
   chmod 755 catalog/controller/payment/
   chmod 644 catalog/controller/payment/holestpay.php
   ```

2. **Clear OpenCart cache**:
   - Go to Dashboard → Gear Icon → Developer Settings
   - Click "Refresh" for both Theme and SASS caches

3. **Check error logs**:
   - Go to System → Maintenance → Error Logs
   - Look for any HolestPay-related errors

4. **Verify files exist**:
   ```bash
   ls -la admin/controller/payment/holestpay.php
   ls -la admin/model/payment/holestpay.php
   ls -la catalog/controller/payment/holestpay.php
   ```

### Files Included for OpenCart 3

- `admin/controller/payment/holestpay_opencart3.php` - OpenCart 3 admin controller
- `admin/model/payment/holestpay_opencart3.php` - OpenCart 3 admin model
- `catalog/controller/payment/holestpay_opencart3.php` - OpenCart 3 catalog controller
- `fix_opencart3_compatibility.php` - Automatic compatibility fix script
- `OPENCART3_INSTALLATION.md` - Detailed installation guide

For detailed troubleshooting, see [OPENCART3_INSTALLATION.md](OPENCART3_INSTALLATION.md).

## Configuration

### Required Settings
1. **Environment**: Choose Sandbox for testing or Production for live transactions
2. **Merchant Site UID**: Your unique identifier provided by HolestPay
3. **Secret Key**: Your secret key for secure communication

### Webhook Configuration
1. Copy the webhook URL from the configuration page
2. Configure it in your HolestPay panel under webhook settings
3. HolestPay will automatically send configuration data to your site

### Optional Settings
- **Title**: Payment method display name
- **Description**: Description shown during checkout
- **Order Status**: Status for successful payments
- **Failed Order Status**: Status for failed payments
- **Geo Zone**: Restrict to specific geographical zones
- **Sort Order**: Display order among payment methods

## Database Structure

The module creates the following tables:

### holestpay_config
Stores large JSON configuration data from HolestPay.

### holestpay_payment_methods
Individual payment methods with their configurations.

### holestpay_shipping_methods
Available shipping methods with cost calculation data.

### holestpay_vault_tokens
Customer saved payment methods for recurring use.

### holestpay_subscriptions
Subscription and recurring payment data.

### Order Table Modifications
Adds three fields to the order table:
- `hpay_uid`: HolestPay order identifier
- `hpay_status`: Combined payment/shipping/fiscal/integration status
- `hpay_data`: Current order data from HolestPay

## JavaScript Integration

### HolestPayAdmin
Available in admin area for:
- Configuration management
- Order status monitoring
- Payment command execution (CAPTURE, VOID, REFUND)
- Real-time data updates

### HolestPayCheckout
Available in frontend for:
- Payment method selection
- Vault token management
- Cart data monitoring
- Payment form presentation
- Subscription options

## Webhook Integration

The module handles three webhook topics:

### Configuration Webhook
- Receives HolestPay configuration updates
- Updates payment and shipping methods
- Stores large configuration data

### Order Update Webhook
- Receives order status changes
- Updates OpenCart order status
- Syncs HolestPay data

### Payment Result Webhook
- Processes payment completion
- Updates order status
- Saves vault tokens
- Handles subscription setup

## Subscription Support

### Requirements
- Customer must be logged in
- Payment method must support MIT or COF
- Vault token must be saved

### Implementation
- Uses `/charge` endpoint for recurring payments
- Automatic payment processing
- Subscription status management
- Customer notification system

## Security Features

### Request Signing
All requests to HolestPay are signed using:
```php
$signature = hash('sha256', $order_uid . $order_amount . $order_currency . $secret_key);
```

### Webhook Verification
All incoming webhooks are verified using HMAC-SHA256:
```php
$expected_signature = hash_hmac('sha256', $webhook_data, $secret_key);
```

## API Integration

### Payment Request Structure
```json
{
    "merchant_site_uid": "your_merchant_uid",
    "order_uid": "order_123",
    "order_amount": "99.99",
    "order_currency": "USD",
    "payment_method": "payment_method_id",
    "vault_token_uid": "optional_token",
    "cof": "none|required",
    "signature": "request_signature"
}
```

### Shipping Cost Calculation
```json
{
    "shipping_method_id": "shipping_method_id",
    "destination": {
        "country": "US",
        "zone": "CA",
        "city": "Los Angeles",
        "postcode": "90210"
    },
    "cart": {
        "total_weight": 2.5,
        "total_volume": 1000,
        "total_value": 199.99,
        "items": [...]
    }
}
```

## Troubleshooting

### OpenCart 3 Specific Issues

**Q: Extension doesn't appear in Extensions > Payments on OpenCart 3**
A: This is the most common issue. The solution is to copy the OpenCart 3 compatible files:
```bash
# Run the automatic fix script
php fix_opencart3_compatibility.php

# OR manually copy the files
cp admin/controller/payment/holestpay_opencart3.php admin/controller/payment/holestpay.php
cp admin/model/payment/holestpay_opencart3.php admin/model/payment/holestpay.php
cp catalog/controller/payment/holestpay_opencart3.php catalog/controller/payment/holestpay.php
```

**Q: "Class not found" errors on OpenCart 3**
A: This means the wrong controller files are being used. Make sure you've copied the `*_opencart3.php` files to the standard locations.

**Q: Extension installs but doesn't work properly on OpenCart 3**
A: Check that all three files are copied:
- `admin/controller/payment/holestpay.php` (copied from holestpay_opencart3.php)
- `admin/model/payment/holestpay.php` (copied from holestpay_opencart3.php)
- `catalog/controller/payment/holestpay.php` (copied from holestpay_opencart3.php)

### General Issues

**Payment methods not showing**
- Check if webhook configuration is correct
- Verify HolestPay has sent configuration data
- Check database table `holestpay_payment_methods`

**Shipping costs not calculating**
- Verify shipping methods are configured in HolestPay
- Check cart weight and dimensions are set
- Review shipping method configuration

**Webhook not receiving data**
- Verify webhook URL is accessible
- Check server logs for errors
- Confirm signature verification is working

### Debug Information

Enable debug logging by adding to your config files:
```php
define('HOLESTPAY_DEBUG', true);
```

Check logs in:
- `system/storage/logs/holestpay.log`
- Server error logs
- Browser developer console

## Quick Reference

### OpenCart 3 Users
- **Problem**: Extension not visible in Extensions > Payments
- **Solution**: Run `php fix_opencart3_compatibility.php`
- **Files**: Use `*_opencart3.php` files for OpenCart 3

### OpenCart 4 Users
- **Installation**: Standard extension installer works
- **Files**: Uses namespace-based controllers automatically

### File Structure
```
holestpay-opencart/
├── admin/
│   ├── controller/payment/
│   │   ├── holestpay.php              # OpenCart 4 controller
│   │   ├── holestpay_opencart3.php    # OpenCart 3 controller
│   │   └── holestpay_compatibility.php # Version detection
│   └── model/payment/
│       ├── holestpay.php              # OpenCart 4 model
│       └── holestpay_opencart3.php    # OpenCart 3 model
├── catalog/
│   └── controller/payment/
│       ├── holestpay.php              # OpenCart 4 controller
│       ├── holestpay_opencart3.php    # OpenCart 3 controller
│       └── holestpay_compatibility.php # Version detection
├── fix_opencart3_compatibility.php    # OpenCart 3 fix script
├── OPENCART3_INSTALLATION.md          # Detailed OpenCart 3 guide
└── README.md                          # This file
```

## Support

- **Email**: support@pay.holest.com
- **Website**: https://pay.holest.com/support
- **Documentation**: https://docs.pay.holest.com/opencart
- **OpenCart 3 Issues**: See [OPENCART3_INSTALLATION.md](OPENCART3_INSTALLATION.md)

## License

This module is licensed under commercial license. Please contact HolestPay for licensing terms.

## Changelog

### Version 1.0.0 (2024-12-19)
- Initial release
- Complete HolestPay payment integration
- Multiple payment methods support
- Shipping methods with cost calculation
- Subscription and recurring payments
- Vault token management
- Webhook integration
- Admin order management
- OpenCart 3 & 4 compatibility
