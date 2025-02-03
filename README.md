# VN Payment Gateway ![Status](https://img.shields.io/badge/status-complete-darkgreen) ![Version](https://img.shields.io/badge/version-0.0.1-blue)
A helper for integrating with almost all payment gateways in Vietnam.

## Installation

To install the library, use Composer:

```sh
composer require iamcot/payment-gateway
```

## Usage

### Initialize the Processor
First, you need to initialize the payment processor. You can choose between Momo, Onepay, and Vnpay.

```php
<?php
use Iamcot\PaymentGateway\Processor;

$params = [
    'accessKey' => 'your-access-key',
    'secretKey' => 'your-secret-key',
    'paymentUrl' => 'your-payment-url',
    'returnUrl' => 'your-return-url',
    'notifyUrl' => 'your-notify-url',
    'partnerCode' => 'your-partner-code'
];

$processor = Processor::getProcessor('momo', $params);
```

### Validate Payment Data
Before processing the payment, you need to validate the payment data.
```php
<?php
$input = [
    'amount' => 100000, // Amount in VND
    'orderid' => 'order123',
    'ip' => '127.0.0.1',
    'save_card' => false,
    'token_num' => '',
    'token_exp' => '',
    'user_id' => ''
];

$validationResult = $processor->validate($input);

if ($validationResult !== true) {
    // Handle validation error
    echo $validationResult;
}
```

### Process Payment
After validation, you can process the payment to get the payment URL.

```php
<?php
$response = $processor->processPayment();

if ($response->status) {
    // Redirect to the payment URL
    header('Location: ' . $response->data);
} else {
    // Handle payment error
    echo $response->errorCode;
}
```

### Handle Return Data
After the payment is completed, the payment gateway will redirect to your return URL with the payment data.

```php
<?php
$returnData = $_GET; // Or $_POST depending on the gateway

$response = $processor->processReturnData(http_build_query($returnData));

if ($response->status) {
    // Payment successful
    echo 'Payment successful';
} else {
    // Handle payment error
    echo $response->errorCode;
}
```

### Handle Notify Data
Some payment gateways will send a notification to your notify URL with the payment data.

```php
<?php
$result = $processor->processFeedbackData($notifyData, 'notify');

if ($response['status']) {
    // Payment successful
    echo  $processor->prepareNotifyResponse($notifyData, $result)
} else {
    // Handle payment error
}
```
## License
Feel free to use it ;)