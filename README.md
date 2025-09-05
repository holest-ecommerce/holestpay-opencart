# HolestPay Payment Gateway for OpenCart 3 & 4

A comprehensive payment and shipping integration module for OpenCart that provides full HolestPay functionality including multiple payment methods, subscriptions, shipping cost calculation, and order management.

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
- **OpenCart 3 & 4 Compatibility**: Single codebase works with both versions
- **Multi-environment**: Sandbox and Production environment support
- **Webhook Processing**: Handles configuration, order updates, and payment results
- **Database Integration**: Custom tables for HolestPay data storage
- **JavaScript Integration**: Admin and frontend JavaScript objects

## Installation

### Automatic Installation (Recommended)
1. Download the `holestpay-opencart.zip` package
2. Go to Extensions → Installer in your OpenCart admin
3. Upload the zip file
4. Go to Extensions → Extensions → Payments
5. Find "HolestPay Payment Gateway" and click Install
6. Click Edit to configure the module

### Manual Installation
1. Extract the zip file
2. Upload files to your OpenCart directory maintaining the folder structure:
   ```
   admin/
   catalog/
   install.json
   ```
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
- OpenCart 3 & 4 compatibility
