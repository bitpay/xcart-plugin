<?php

namespace XLite\Module\BitPay\BitPay\Controller\Admin;

use \XLite\Module\BitPay\BitPay\lib\XcartWrapper;

class BitpayAjax extends \XLite\Controller\Admin\AAdmin
{
	protected function doNoAction() 
    {
        $endpoint = \XLite\Core\Request::getInstance()->endpoint;

        switch($endpoint) {
            case "connect":
                return $this->doActionConnect();
                break;
        }
        $network = \XLite\Core\Request::getInstance()->network;
        if ('testnet' !== $network && 'livenet' !== $network) {
            return;
        }

        $method_id = \XLite\Core\Request::getInstance()->method_id;
        $method = $this->getPaymentMethod($method_id);

        $redirect_url = \XLite\Core\Request::getInstance()->redirecturl;

    	$bitpayWrapper = new XcartWrapper($method);
        $bitpayWrapper->setNetwork($network);
    	$url = $bitpayWrapper->getPairingUrl();
        $redirect_url = "&redirect=".urlencode($redirect_url);
        $url = $url.$redirect_url;
    	\XLite\Core\Event::generatedConnectUrl(array($url));
    }  

    protected function getPaymentMethod($method_id)
    {
        return \XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')
            ->find($method_id);
    } 

    protected function doActionConnect()
    {
        $method = \XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')->createQueryBuilder('p')->where('p.service_name = \'BitPay\'')->getResult()[0];
        $bitpayWrapper = new XcartWrapper($method);
        $connection = $bitpayWrapper->setting('connection');
        $private_key = $bitpayWrapper->setting('private_key');
        if (!empty($private_key) || $connection !== 'disconnected') {
            $bitpayWrapper->checkConnection();
        }

        \XLite\Core\Event::connectionState($connection);
        
    }
}