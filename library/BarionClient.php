<?php

/**
 * Copyright 2016 Barion Payment Inc. All Rights Reserved.
 * <p/>
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * <p/>
 * http://www.apache.org/licenses/LICENSE-2.0
 * <p/>
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
*  
*  BarionClient.php
*  PHP library for implementing REST API calls towards the Barion Payment system.
*  
*/

namespace Bencurio\Barion;

use Bencurio\Barion\Enum\BarionConstants;
use Bencurio\Barion\Enum\BarionEnvironment;
use Bencurio\Barion\Enum\QRCodeSize;
use Bencurio\Barion\Models\Account\AccountsRequestModel;
use Bencurio\Barion\Models\Account\AccountsResponseModel;
use Bencurio\Barion\Models\ApiErrorModel;
use Bencurio\Barion\Models\BaseResponseModel;
use Bencurio\Barion\Models\Payment\CancelAuthorizationRequestModel;
use Bencurio\Barion\Models\Payment\CancelAuthorizationResponseModel;
use Bencurio\Barion\Models\Payment\CaptureRequestModel;
use Bencurio\Barion\Models\Payment\CaptureResponseModel;
use Bencurio\Barion\Models\Payment\Complete3DSPaymentRequestModel;
use Bencurio\Barion\Models\Payment\Complete3DSPaymentResponseModel;
use Bencurio\Barion\Models\Payment\FinishReservationRequestModel;
use Bencurio\Barion\Models\Payment\FinishReservationResponseModel;
use Bencurio\Barion\Models\Payment\PaymentQRRequestModel;
use Bencurio\Barion\Models\Payment\PaymentStateRequestModel;
use Bencurio\Barion\Models\Payment\PaymentStateResponseModel;
use Bencurio\Barion\Models\Payment\PreparePaymentRequestModel;
use Bencurio\Barion\Models\Payment\PreparePaymentResponseModel;
use Bencurio\Barion\Models\Transfer\BankTransferRequestModel;
use Bencurio\Barion\Models\Transfer\BankTransferResponseModel;
use Bencurio\Barion\Models\Refund\RefundRequestModel;
use Bencurio\Barion\Models\Refund\RefundResponseModel;
use Bencurio\Barion\Models\Transfer\EmailTransferRequestModel;
use Bencurio\Barion\Models\Transfer\EmailTransferResponseModel;

class BarionClient
{
    private $Environment;

    private $APIVersion;
    private $POSKey;
    private $APIKey;

    private $BARION_API_URL = "";
    private $BARION_WEB_URL = "";

    private $UseBundledRootCertificates;

    /**
     *  Constructor
     *
     * @param string $poskey The secret POSKey of your shop
     * @param int $version The version of the Barion API
     * @param string $env The environment to connect to
     * @param bool $useBundledRootCerts Set this to true if you're having problem with SSL connection
     */
    function __construct($poskey, $apikey, $version = 2, $env = BarionEnvironment::Prod, $useBundledRootCerts = false)
    {

        $this->POSKey = $poskey;
        $this->APIKey = $apikey;
        $this->APIVersion = $version;
        $this->Environment = $env;

        switch ($env) {

            case BarionEnvironment::Test:
                $this->BARION_API_URL = BarionConstants::BARION_API_URL_TEST;
                $this->BARION_WEB_URL = BarionConstants::BARION_WEB_URL_TEST;
                break;

            case BarionEnvironment::Prod:
            default:
                $this->BARION_API_URL = BarionConstants::BARION_API_URL_PROD;
                $this->BARION_WEB_URL = BarionConstants::BARION_WEB_URL_PROD;
                break;
        }

        $this->UseBundledRootCertificates = $useBundledRootCerts;
    }

    /* -------- BARION API CALL IMPLEMENTATIONS -------- */


    /**
     * Prepare a new Payment
     *
     * @param PreparePaymentRequestModel $model The request model for Payment preparation
     * @return PreparePaymentResponseModel Returns the response from the Barion API
     */
    public function PreparePayment(PreparePaymentRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_PREPAREPAYMENT;
        $response = $this->PostToBarion($url, $model);
        $rm = new PreparePaymentResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $rm->fromJson($json);
            if (!empty($rm->PaymentId)) {
                $rm->PaymentRedirectUrl = $this->BARION_WEB_URL . "?" . \http_build_query(array("id" => $rm->PaymentId));
            }
        }
        return $rm;
    }

    /**
     *
     * Finish an existing reservation
     *
     * @param FinishReservationRequestModel $model The request model for the finish process
     * @return FinishReservationResponseModel Returns the response from the Barion API
     */
    public function FinishReservation(FinishReservationRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_FINISHRESERVATION;
        $response = $this->PostToBarion($url, $model);
        $rm = new FinishReservationResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $rm->fromJson($json);
        }
        return $rm;
    }
    
    /**
     *
     * Capture the previously authorized money in a Delayed Capture Payment
     *
     * @param CaptureRequestModel $model The request model for the capture process
     * @return CaptureResponseModel Returns the response from the Barion API
     */
    public function Capture(CaptureRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_CAPTURE;
        $response = $this->PostToBarion($url, $model);
        $captureResponse = new CaptureResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $captureResponse->fromJson($json);
        }
        return $captureResponse;
    }

    /**
     *
     * Cancel a pending authorization on a Delayed Capture Payment
     *
     * @param CancelAuthorizationRequestModel $model The request model for cancelling the authorization
     * @return CancelAuthorizationResponseModel Returns the response from the Barion API
     */
    public function CancelAuthorization(CancelAuthorizationRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_CANCELAUTHORIZATION;
        $response = $this->PostToBarion($url, $model);
        $cancelAuthResponse = new CancelAuthorizationResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $cancelAuthResponse->fromJson($json);
        }
        return $cancelAuthResponse;
    }
    
    /**
     * Complete a previously 3DSecure-authenticated Payment
     *
     * @param Complete3DSPaymentRequestModel $model The request model for completing the authenticated Payment
     * @return Complete3DSPaymentResponseModel Returns the response from the Barion API
     */
    public function Complete3DSPayment(Complete3DSPaymentRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_3DS_COMPLETE;
        $response = $this->PostToBarion($url, $model);
        $rm = new Complete3DSPaymentResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $rm->fromJson($json);
        }
        return $rm;
    }

    /**
     * Refund a Payment partially or totally
     *
     * @param RefundRequestModel $model The request model for the refund process
     * @return RefundResponseModel Returns the response from the Barion API
     */
    public function RefundPayment(RefundRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_REFUND;
        $response = $this->PostToBarion($url, $model);
        $rm = new RefundResponseModel();
        if (!empty($response)) {
            $json = json_decode($response, true);
            $rm->fromJson($json);
        }
        return $rm;
    }


    /**
     * Get detailed information about a given Payment
     *
     * @param string $paymentId The Id of the Payment
     * @return PaymentStateResponseModel Returns the response from the Barion API
     */
    public function GetPaymentState($paymentId)
    {
        $model = new PaymentStateRequestModel($paymentId);
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_PAYMENTSTATE;
        $response = $this->GetFromBarion($url, $model);
        $ps = new PaymentStateResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $ps->fromJson($json);
        }
        return $ps;
    }

    /**
     * Get the QR code image for a given Payment
     *
     * NOTE: This call is deprecated and is only working with username & password authentication.
     * If no username and/or password was set, this method returns NULL.
     *
     * @deprecated
     * @param string $username The username of the shop's owner
     * @param string $password The password of the shop's owner
     * @param string $paymentId The Id of the Payment
     * @param string $qrCodeSize The desired size of the QR image
     * @return mixed|string Returns the response of the QR request
     */
    public function GetPaymentQRImage($username, $password, $paymentId, $qrCodeSize = QRCodeSize::Large)
    {
        $model = new PaymentQRRequestModel($paymentId);
        $model->POSKey = $this->POSKey;
        $model->UserName = $username;
        $model->Password = $password;
        $model->Size = $qrCodeSize;
        $url = $this->BARION_API_URL . BarionConstants::API_ENDPOINT_QRCODE;
        $response = $this->GetFromBarion($url, $model);
        return $response;
    }

    /**
     * Transfer the specified amount to a bank account
     *
     * @param string $model The BankTransferRequestModel to be passed
     * @return BankTransferResponseModel Returns the response from the Barion API
     */
    public function BankTransfer(BankTransferRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . BarionConstants::API_ENDPOINT_BANK_TRANSFER;
        $response = $this->PostToBarion($url, $model);

        $ps = new BankTransferResponseModel();
        if (!empty($response)) {
            $json = json_decode($response, true);
            $ps->fromJson($json);
        }
        return $ps;
    }

    /**
     * Transfer the specified amount to a Barion Wallet
     *
     * @param string $model The EmailTransferRequestModel to be passed
     * @return EmailTransferResponseModel Returns the response from the Barion API
     */
    public function EmailTransfer(EmailTransferRequestModel $model)
    {
        $model->POSKey = $this->POSKey;
        $url = $this->BARION_API_URL . BarionConstants::API_ENDPOINT_EMAIL_TRANSFER;
        $response = $this->PostToBarion($url, $model);

        $et = new EmailTransferResponseModel();
        if (!empty($response)) {
            $json = json_decode($response, true);
            $et->fromJson($json);
        }
        return $et;
    }

    /**
     * Query the existing ccounts of the calling user
     *
     * @return AccountsResponseModel Returns the response from the Barion API
     */
    public function Accounts()
    {
        $model = new AccountsRequestModel();
        $url = $this->BARION_API_URL . "/v" . $this->APIVersion . BarionConstants::API_ENDPOINT_ACCOUNTS;
        $response = $this->GetFromBarion($url, $model);
        $a = new AccountsResponseModel();
        if (!empty($response)) {
            $json = \json_decode($response, true);
            $a->fromJson($json);
        }
        return $a;
    }

    /* -------- CURL HTTP REQUEST IMPLEMENTATIONS -------- */

    /*
    *
    */
    /**
     * Managing HTTP POST requests
     *
     * @param string $url The URL of the API endpoint
     * @param object $data The data object to be sent to the endpoint
     * @return mixed|string Returns the response of the API
     */
    private function PostToBarion($url, $data)
    {
        $ch = \curl_init();
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if ($userAgent == "") {
            $cver = \curl_version();
            $userAgent = "curl/" . $cver["version"] . " " .$cver["ssl_version"];
        }

        $postData = \json_encode($data);

        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_POST, 1);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "User-Agent: $userAgent", "x-api-key: $this->APIKey"));
        
        if(\substr(\phpversion(), 0, 3) < 5.6) {
            \curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        }

        if ($this->UseBundledRootCertificates) {
            \curl_setopt($ch, CURLOPT_CAINFO, \join(DIRECTORY_SEPARATOR, array(\dirname(__FILE__), 'ssl', 'cacert.pem')));

            if ($this->Environment == BarionEnvironment::Test) {
                \curl_setopt($ch, CURLOPT_CAPATH, \join(DIRECTORY_SEPARATOR, array(\dirname(__FILE__), 'ssl', 'gd_bundle-g2.crt')));
            }
        }

        $output = \curl_exec($ch);
        if ($err_nr = \curl_errno($ch)) {
            $error = new ApiErrorModel();
            $error->ErrorCode = "CURL_ERROR";
            $error->Title = "CURL Error #" . $err_nr;
            $error->Description = curl_error($ch);

            $response = new BaseResponseModel();
            $response->Errors = array($error);
            $output = \json_encode($response);
        }
        \curl_close($ch);

        return $output;
    }


    /**
     * Managing HTTP GET requests
     *
     * @param string $url The URL of the API endpoint
     * @param object $data The data object to be sent to the endpoint
     * @return mixed|string Returns the response of the API
     */
    private function GetFromBarion($url, $data)
    {
        $ch = \curl_init();

        $getData = \http_build_query($data);
        $fullUrl = $url . '?' . $getData;
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if ($userAgent == "") {
            $cver = \curl_version();
            $userAgent = "curl/" . $cver["version"] . " " .$cver["ssl_version"];
        }

        \curl_setopt($ch, CURLOPT_URL, $fullUrl);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array("User-Agent: $userAgent", "x-api-key: $this->APIKey"));
        
        if(\substr(\phpversion(), 0, 3) < 5.6) {
            \curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        }

        if ($this->UseBundledRootCertificates) {
            \curl_setopt($ch, CURLOPT_CAINFO, \join(DIRECTORY_SEPARATOR, array(\dirname(__FILE__), 'ssl', 'cacert.pem')));

            if ($this->Environment == BarionEnvironment::Test) {
                \curl_setopt($ch, CURLOPT_CAPATH, \join(DIRECTORY_SEPARATOR, array(\dirname(__FILE__), 'ssl', 'gd_bundle-g2.crt')));
            }
        }

        $output = \curl_exec($ch);
        if ($err_nr = \curl_errno($ch)) {
            $error = new ApiErrorModel();
            $error->ErrorCode = "CURL_ERROR";
            $error->Title = "CURL Error #" . $err_nr;
            $error->Description = \curl_error($ch);

            $response = new BaseResponseModel();
            $response->Errors = array($error);
            $output = \json_encode($response);
        }
        \curl_close($ch);

        return $output;
    }
}