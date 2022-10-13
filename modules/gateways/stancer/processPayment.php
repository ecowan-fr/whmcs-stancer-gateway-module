<?php

use GuzzleHttp\Client;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayParams = getGatewayVariables('stancer');

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID(urldecode($_POST["invoice_id"]), 'stancer');

$livePublicKey = $gatewayParams['livePublicKey'];
$liveSecretKey = $gatewayParams['liveSecretKey'];
$testPublicKey = $gatewayParams['testPublicKey'];
$testSecretKey = $gatewayParams['testSecretKey'];
$testMode = $gatewayParams['testMode'];

$client = new Client(['base_uri' => 'https://api.stancer.com']);

$unique_time = time();
$unique_id = md5(hash('sha512', $gatewayParams['systemurl']) . hash('sha512', $unique_time) . hash('sha512', $invoiceId) . hash('sha512', $amount) . hash('sha512', ($testMode ? $testSecretKey : $liveSecretKey)));

//Create a payment
$endpoint = '/v1/checkout/';
$body = json_encode([
    'auth' => [
        'status' => 'request'
    ],
    'amount' => urldecode($_POST["amount"]),
    'currency' => urldecode($_POST["currency"]),
    'description' => urldecode($_POST["description"]),
    'order_id' => $invoiceId,
    'unique_id' => $unique_id,
    'methods_allowed' => 'card',
    'customer' => [
        'email' => urldecode($_POST["email"]),
        'name' => urldecode($_POST["customer"])
    ],
    'return_url' => $gatewayParams['systemurl'] . 'modules/gateways/stancer/checkPayment.php?unique_time=' . $unique_time . '&unique_id=' . $unique_id
]);

$response = $client->post($endpoint, [
    'http_errors' => false,
    'headers' => ['Content-Type' => 'application/json'],
    'auth' => [($testMode ? $testSecretKey : $liveSecretKey)],
    'body' => $body
]);
$responseCode = $response->getStatusCode();
$responseBody = $response->getBody();
$responseData = json_decode($responseBody->getContents());
logModuleCall('stancer', $endpoint, $body, "HTTP Response Code: " . $responseCode . PHP_EOL . "HTTP Response Phrase: " . $response->getReasonPhrase() . PHP_EOL . $responseBody, $responseData);

if ($responseCode == '200') {
    $payment_id = $responseData->id;

    header('location: https://payment.stancer.com/' . ($testMode ? $testPublicKey : $livePublicKey) . '/' . $payment_id);
} else {
    die('Error ! Impossible to create a payment object !');
}
