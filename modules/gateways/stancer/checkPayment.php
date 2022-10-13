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

$liveSecretKey = $gatewayParams['liveSecretKey'];
$testSecretKey = $gatewayParams['testSecretKey'];
$testMode = $gatewayParams['testMode'];

$client = new Client(['base_uri' => 'https://api.stancer.com']);

$unique_time = $_GET['unique_time'];
$unique_id = $_GET['unique_id'];

if ($unique_time === null || $unique_time === '' || $unique_id === null || $unique_id === '') {
    die('Error ! Missing unique_time OR unique_id');
}

// Get Payment data
$endpoint = '/v1/checkout/';
$response = $client->get($endpoint, [
    'auth' => [($testMode ? $testSecretKey : $liveSecretKey)],
    'query' => [
        'unique_id' => $unique_id
    ]
]);
$responseCode = $response->getStatusCode();
$responseBody = $response->getBody();
$responseData = json_decode($responseBody->getContents());
logModuleCall('stancer', $endpoint, 'GET https://api.stancer.com' . $endpoint . '?unique_id=' . $unique_id, "HTTP Response Code: " . $responseCode . PHP_EOL . "HTTP Response Phrase: " . $response->getReasonPhrase() . PHP_EOL . $responseBody, $responseData);

if ($responseCode != '200') {
    die('Error ! Your payment is not found !');
}

$payments = $responseData->payments;

if (count($payments) !== 1) {
    die('Error ! Your payment is not found !');
}

$payment = $payments[0];

switch ($payment->status) {
    case 'captured':
        $transactionStatus = 'Success';
        break;
    case 'to_capture':
        $transactionStatus = 'Pending';
        break;
    default:
        $transactionStatus = 'Failure';
        break;
}

//Check hash & unique_id
if (
    $unique_id != md5(
        hash('sha512', $gatewayParams['systemurl']) .
            hash('sha512', $unique_time) .
            hash('sha512', $payment->order_id) .
            hash('sha512', $payment->amount) .
            hash('sha512', ($testMode ? $testSecretKey : $liveSecretKey))
    )
) {
    $transactionStatus = 'Hash Verification Failure';
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
$invoiceId = checkCbInvoiceID($payment->order_id, 'stancer');

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($payment->id);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction('stancer', ['unique_id' => $unique_id, 'payment_id' => $payment->id], $transactionStatus);

if (!$testMode) {
    if ($transactionStatus === 'Success') {
        /**
         * Add Invoice Payment.
         *
         * Applies a payment transaction entry to the given invoice ID.
         *
         * @param int $invoiceId         Invoice ID
         * @param string $transactionId  Transaction ID
         * @param float $paymentAmount   Amount paid (defaults to full balance)
         * @param float $paymentFee      Payment fee (optional)
         * @param string $gatewayModule  Gateway module name
         */
        addInvoicePayment(
            $invoiceId,
            $payment->id,
            ($payment->amout / 100),
            ($payment->fee / 100),
            'stancer'
        );
    } elseif ($transactionStatus === 'Pending') {
        localAPI('UpdateInvoice', [
            'invoiceid' => $invoiceId,
            'status' => 'Payment Pending'
        ]);
    }
}

switch ($transactionStatus) {
    case 'Success':
        $redirectionMessage = 'paymentsuccess';
        break;
    case 'Pending':
        $redirectionMessage = 'paymentinititated';
        break;
    default:
        $redirectionMessage = 'paymentfailed';
        break;
}

header('location: ' . $gatewayParams['systemurl'] . 'viewinvoice.php?id=' . $invoiceId . '&' . $redirectionMessage . '=true');
