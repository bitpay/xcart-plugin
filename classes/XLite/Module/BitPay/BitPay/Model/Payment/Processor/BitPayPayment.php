<?php

namespace XLite\Module\BitPay\BitPay\Model\Payment\Processor;

use \XLite\Module\BitPay\BitPay\lib\XcartWrapper;

class BitPayPayment extends \XLite\Model\Payment\Base\WebBased
{
    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/BitPay/BitPay/config.tpl';
    }

    protected function getFormURL()
    {
        $params = $this->getFormFields();

        $method = \XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')->createQueryBuilder('p')->where('p.service_name = \'BitPay\'')->getResult()[0];
        $bitpayWrapper = new XcartWrapper($method);
        $invoice = $bitpayWrapper->buildInvoice($params);
        $response = $bitpayWrapper->createInvoice($invoice);
        header('Location: ' . $invoice->getUrl());
    }

    protected function getFormFields()
    {
        $order = $this->getOrder();
        if ( $order == NULL ) {
            error_log("No order available. Cannot get Invoice Information.");
            return;
        }
        $fields = array(
            'transactionID' => $this->transaction->getPublicTxnId(),
            'returnURL' => $this->getReturnURL('txnId', $this->transaction->getPublicTxnId()) . '&status=Paid',
            'MerchantID' => $this->getSetting('merchantID'),
            'MerchantKey' => $this->getSetting('merchantKey'),
            'TransactionAmount' => $this->transaction->getValue(),
            'ReferenceNumber' => $this->getSetting('orderPrefix') . $this->transaction->getTransactionId(),
            'EmailAddress' => $this->getProfile()->getLogin(),
            'ClientIPAddress' => $this->getClientIP(),
            'ProductDescription' => 'Order #' . $this->getOrder()->getOrderId(),
            'PostBackURL' => $this->getCallbackURL("txnId", $this->transaction->getPublicTxnId()),
            'orderId' => $this->getOrder()->getOrderNumber(),
            'price' => $this->getOrder()->getCurrency()->roundValue($this->transaction->getOrder()->getTotal()),
            'currencyCode' => $this->getOrder()->getCurrency()->getCode(),
        );
        if ($billingAddress = $this->getProfile()->getBillingAddress()) {
            $fields += array(
                'BillingNameFirst' => $billingAddress->getFirstname(),
                'BillingNameLast' => $billingAddress->getLastname(),
                'BillingFullName' => $billingAddress->getFirstname()
                    . ' ' . $billingAddress->getLastname(),
                'BillingAddress' => $billingAddress->getStreet(),
                'BillingZipCode' => $billingAddress->getZipcode(),
                'BillingCity' => $billingAddress->getCity(),
                'BillingState' => $billingAddress->getState()->getCode(),
                'BillingCountry' => $billingAddress->getCountry()->getCode(),
                'PhoneNumber' => $billingAddress->getPhone(),
            );
        }
        if ($shippingAddress = $this->getProfile()->getShippingAddress()) {
            $fields += array(
                'ShippingAddress1' => $shippingAddress->getStreet(),
                'ShippingAddress2' => '',
                'ShippingCity' => $shippingAddress->getCity(),
                'ShippingState' => $shippingAddress->getState()->getCode(),
                'ShippingZipCode' => $shippingAddress->getZipcode(),
                'ShippingCountry' => $shippingAddress->getCountry()->getCode(),
            );
        }
        return $fields;
    }

    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processReturn($transaction);

        $result = \XLite\Core\Request::getInstance()->status;

        $status = ('Paid' == $result)
            ? $transaction::STATUS_PENDING
            : $transaction::STATUS_FAILED;

        $this->transaction->setStatus($status);
    }

    protected function getBitPayPaymentSettings()
    {
        return array(
            'riskSpeed' => $this->getSetting('riskSpeed'),
            'network' => $this->getSetting('network'), 
            'redirectURL' => $this->getSetting('redirectURL'),
            'debug' => $this->getSetting('debug'),
            'version' => $this->getSetting('version'),
            'private_key' => $this->getSetting('private_key'),
            'public_key' => $this->getSetting('public_key'),
            'connection' => $this->getSetting('connection'),
            'pairing_code' => $this->getSetting('pairing_code'),
            'token' => $this->getSetting('token'),
            'pairing_expiration' => $this->getSetting('pairing_expiration'),
            'status' => $this->getSetting('status'),
            );
    }

   /* 
    * Server to server callback 
    *  
    */
    public function processCallback(\XLite\Model\Payment\Transaction $transaction)
    {

        $post = file_get_contents("php://input");
        if (true === empty($post)) {
            return array('error' => 'No post data');
            error_log("No post data");
        }
        $json = json_decode($post, true);
        if (true === is_string($json)) {
            return array('error' => $json);
            error_log($json);
        }
        if (false === array_key_exists('posData', $json)) {
            return array('error' => 'no posData');
            error_log("no posData");
        }
        if (false === array_key_exists('id', $json)) {
            return 'Cannot find invoice ID';
            error_log("Cannot find invoice ID");
        }

        $method = \XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')->createQueryBuilder('p')->where('p.service_name = \'BitPay\'')->getResult()[0];
        $bitpayWrapper = new XcartWrapper($method);
        $response = $bitpayWrapper->getInvoice($json['id']);
        $invoiceId = $response->getPosData();

        switch ($response->getStatus()) {
            case 'paid':
                error_log("Paid for Invoice ID: ".$invoiceId);
                error_log("The payment has been received, but the transaction has not been confirmed on the bitcoin network. This will be updated when the transaction has been confirmed.");
                $status = $transaction::STATUS_PENDING;
                break;  
            case 'confirmed':
                error_log("Confirmed for Invoice ID: ".$invoiceId);
                error_log("The payment has been received, and the transaction has been confirmed on the bitcoin network. This will be updated when the transaction has been completed.");
                $status = $transaction::STATUS_PENDING;
                break;
            case 'complete':
                error_log("Complete for Invoice ID: ".$invoiceId);
                error_log("The transaction is now complete.");
                $status = $transaction::STATUS_SUCCESS;
                break;
        }

        $transaction->setStatus($status);
    }

    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return true;
    }

}