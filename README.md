# HolestPay Payment Gateway for OpenCart 4

A comprehensive payment and shipping integration module for OpenCart 4 that provides full HolestPay functionality including multiple payment methods, subscriptions, shipping cost calculation, and order management.

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
- **OpenCart 4 Compatibility**: Built specifically for OpenCart 4.x
- **Multi-environment**: Sandbox and Production environment support
- **Webhook Processing**: Handles configuration, order updates, and payment results
- **Database Integration**: Custom tables for HolestPay data storage
- **JavaScript Integration**: Admin and frontend JavaScript objects

## Installation

### Prerequisites
- OpenCart 4.0.0.0 or higher
- PHP 7.4 or higher
- Required PHP extensions: curl, json, openssl
- SSL certificate (recommended for production)

### Installation

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
All requests to HolestPay are signed using a complex signature generation:
```php
// HolestPay uses a multi-step signature process
$amt_for_signature = number_format((float)$order_amount, 8, '.', '');
$cstr = $transaction_uid . '|' . $status . '|' . $order_uid . '|' . $amt_for_signature . '|' . $order_currency . '|' . $vault_token_uid . '|' . $subscription_uid . $rand;
$cstrmd5 = md5($cstr . $merchant_site_uid);
$signature = hash('sha512', $cstrmd5 . $secret_key);
```

### Webhook Verification
All incoming webhooks are verified using MD5 signature:
```php
// For posconfig-updated webhooks
$expected_checkstr = md5($merchant_site_uid . $secret_key);

// For other webhooks, verify using the signature in the payload
if ($webhook_data['checkstr'] !== $expected_checkstr) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}
```

## API Integration

### HolestPay Request Structure

```php
// Example HolestPay request generation
$hpay_request = array(
    'merchant_site_uid' => 'your-merchant-site-uid',
    'order_uid' => $order_info['order_id'],
    'order_name' => '#' . $order_info['order_id'],
    'order_amount' => $order_info['total'],
    'order_currency' => $order_info['currency_code'],
    'order_items' => array(
        array(
            'name' => 'Product Name',
            'quantity' => 1,
            'price' => 99.99,
            'total' => 99.99
        )
    ),
    'order_billing' => array(
        'email' => 'customer@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+1234567890',
        'company' => 'Company Name',
        'address' => '123 Main St',
        'address2' => 'Apt 1',
        'city' => 'New York',
        'country' => 'US',
        'postcode' => '10001',
        'lang' => 'en-gb'
    ),
    'order_shipping' => array(
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company' => 'Company Name',
        'address' => '123 Main St',
        'address2' => 'Apt 1',
        'city' => 'New York',
        'country' => 'US',
        'postcode' => '10001'
    ),
    'vault_token_uid' => '', // For saved payment methods
    'subscription_uid' => '', // For recurring payments
    'verificationhash' => 'generated_signature'
);
```

### Signature Generation

```php
// HolestPay signature generation
public function generateSignature($data, $secret_key) {
    $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
    
    // Format amount to 8 decimal places
    $amt_for_signature = number_format((float)$data['order_amount'], 8, '.', '');
    
    // Build concatenated string
    $cstr = trim($data['transaction_uid'] ?? '') . '|';
    $cstr .= trim($data['status'] ?? '') . '|';
    $cstr .= trim($data['order_uid'] ?? '') . '|';
    $cstr .= trim($amt_for_signature) . '|';
    $cstr .= trim($data['order_currency'] ?? '') . '|';
    $cstr .= trim($data['vault_token_uid'] ?? '') . '|';
    $cstr .= trim($data['subscription_uid'] ?? '');
    $cstr .= trim($data['rand'] ?? '');
    
    // First MD5 hash of concatenated string + merchant_site_uid
    $cstrmd5 = md5($cstr . $merchant_site_uid);
    
    // Then SHA512 hash of MD5 result + secret_key
    $sha512calc = hash('sha512', $cstrmd5 . $secret_key);
    
    return strtolower($sha512calc);
}
```

### Webhook Processing

```php
// Example webhook processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['topic'])) {
        switch ($input['topic']) {
            case 'posconfig-updated':
                // Update POS configuration
                $this->processPosConfigWebhook($input);
                break;
            case 'orderupdate':
                // Update order status
                $this->processOrderUpdateWebhook($input);
                break;
            case 'payresult':
                // Process payment result
                $this->processPayResultWebhook($input);
                break;
        }
    }
}
```

## Troubleshooting

### Common Issues

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

### Installation
- **Download**: `holestpay.ocmod.zip` package
- **Install**: Use OpenCart Extension Installer
- **Configure**: Set merchant credentials in admin panel

### File Structure
```
holestpay-opencart/
├── admin/
│   ├── controller/payment/
│   │   └── holestpay.php              # Admin controller
│   └── model/payment/
│       └── holestpay.php              # Admin model
├── catalog/
│   └── controller/payment/
│       └── holestpay.php              # Catalog controller
├── holestpay.ocmod.xml                # OpenCart modification file
├── install.json                       # Extension manifest
├── install.php                        # Installation script
└── README.md                          # This file
```

## Support

- **Email**: support@pay.holest.com
- **Website**: https://pay.holest.com/support
- **Documentation**: https://docs.pay.holest.com/opencart

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
- OpenCart 4 compatibility
