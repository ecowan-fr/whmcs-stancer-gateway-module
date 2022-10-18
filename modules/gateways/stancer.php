<?php


use GuzzleHttp\Client;

/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "gatewaymodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function stancer_MetaData() {
    return array(
        'DisplayName' => 'Stancer',
        'APIVersion' => '1.1', // Use API Version 1.1
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function stancer_config() {
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Stancer',
        ),
        // a text field type allows for single line text input
        'livePublicKey' => array(
            'FriendlyName' => 'Public KEY - LIVE',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your live public key',
        ),
        // a password field type allows for masked text input
        'liveSecretKey' => array(
            'FriendlyName' => 'Secret KEY - LIVE',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your live secret key',
        ),
        // a text field type allows for single line text input
        'testPublicKey' => array(
            'FriendlyName' => 'Public KEY - TEST',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your test public key',
        ),
        // a password field type allows for masked text input
        'testSecretKey' => array(
            'FriendlyName' => 'Secret KEY - TEST',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your test secret key',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        )
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function stancer_link($params) {
    $finalDataReturned = '';
    $livePublicKey = $params['livePublicKey'];
    $liveSecretKey = $params['liveSecretKey'];
    $testPublicKey = $params['testPublicKey'];
    $testSecretKey = $params['testSecretKey'];
    $testMode = $params['testMode'];
    if ($testMode) {
        $finalDataReturned .= '<div class="alert alert-info top-margin-5 bottom-margin-5"><strong><span class="title">TEST MODE !</span></strong></div>';
    }

    if ($testMode and ($testPublicKey == '' || $testSecretKey == '')) {
        $finalDataReturned .= '<div class="alert alert-danger top-margin-5 bottom-margin-5"><strong><span class="title">Warning !</span></strong><br>API keys are not present in the configuration</div>';
    } elseif (!$testMode and ($livePublicKey == '' || $liveSecretKey == '')) {
        $finalDataReturned .= '<div class="alert alert-danger top-margin-5 bottom-margin-5"><strong><span class="title">Warning !</span></strong><br>API keys are not present in the configuration</div>';
    } else {
        $postfields = [];
        $postfields['amount'] = $params['amount'] * 100;
        $postfields['currency'] = strtolower($params['currency']);
        $postfields['description'] = $params["description"];
        $postfields['invoice_id'] = $params['invoiceid'];
        $postfields['email'] = $params['clientdetails']['email'];
        $postfields['customer'] = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];

        $htmlOutput = '<form method="post" action="' . $params['systemurl'] . '/modules/gateways/stancer/processPayment.php">';
        $htmlOutput .= '<input class="btn btn-success btn-sm" type="submit" value="' . $params['langpaynow'] . '" />';
        foreach ($postfields as $k => $v) {
            $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . urlencode($v) . '" />';
        }
        $htmlOutput .= '</form>';
        $finalDataReturned .= $htmlOutput;
    }
    return $finalDataReturned;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function stancer_refund($params) {
    $liveSecretKey = $params['liveSecretKey'];
    $testSecretKey = $params['testSecretKey'];
    $testMode = $params['testMode'];

    $client = new Client(['base_uri' => 'https://api.stancer.com']);

    //Refund a payment
    $endpoint = '/v1/refunds/';
    $body = json_encode([
        'payment' => $params['transid'],
        'amount' => ($params['amount'] * 100)
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

    if (
        $responseCode == '200' &&
        ($responseData->status == 'refund_sent' ||
            $responseData->status == 'refunded' ||
            $responseData->status == 'to_refund')
    ) {
        $status = 'success';
        $refundTransactionId = $responseData->id;
    } else {
        $status = 'error';
        $refundTransactionId = null;
    }

    return [
        'status' => $status,
        'rawdata' => $responseData,
        'transid' => $refundTransactionId,
    ];
}
