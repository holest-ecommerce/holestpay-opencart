HOLEST E-COMMERCE DOO https://ecommerce.holest.com
NestPay payment for OpenCart 4

- holestnestpay.ocmod.zip install via Extensions-> Installer, Upload
- Go to Extensions-> Extensions, filter the "Payments" type. You will find "NestPay payment method by Holest Engineering" in the list. Click on the pen to enter the settings.
- you will be prompted for an API key when you first access. Enter the key provided by us here.

PARAMETERS


Check "Enable" to enable extension.

Title is the name of how it will appear in the payment method selection list.

Description will appear above the payout button.

The CC logo is attached next to the payment method title.

NEST PAY usually only accepts local currency. You can use the currencies you want on the site, but if it is not your home currency you must set it in the field: "Pay explicitly in currency"
You can additionally include the option to refresh the course and even adjust the correction if the course from our server does not suit you: "Refresh exchnage rate" and "Conversion rate correction".

Language code is the code for the language on the bank page. In test mode it is always English. When you go into production, set up this code (2 lowercase letters).

You will get the "Merchant ID" from the bank when you sign the contract.

You need to create a "Merchant / API User" on the bank's portal. Make sure your status is "active" or "locked".

You will get the "Form submit URL" from the bank when you sign the contract.

You get the "API URL" from your bank when you sign the contract. Always yalika is just the end of the link in relation to "Form submit URL".

You need to generate the "store key" on the bank's portal. Make sure it is long enough and versatile, otherwise simple payment will not work.

The "no transaction email" extension sends a separate email to the customer with the transaction information. Do not tick this before they release you to production because they are requesting this (we are aware that these mails are redundant).

"Transaction type" select "Authorization" here if you are making a classic payment (immediately charges the client card).
If you are making a reservation then you should choose "Pre-Authorization". For "Pre-Authorization", for Capture or VOID actions use the bank portal.
For both types of payment for REFUND action use the bank portal.

"Refresh time" is the time that the client waits before returning to your site after CC input.

Store type is always 3d_pay_hosting

"Cancel url" gives you the option to explicitly set up a return link to your site if the customer cancels his payment.

"Payment success order status" / "Payment failed order status" - here you define the statuses in which to put order after successful / unsuccessful payment


CONTACT

www.holest.com
support@holest.com