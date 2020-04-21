<?php

namespace Mobbex\Webpay\Model;

class Mobbex extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'webpay';

    // set payment gateway information
    protected $_code = self::CODE;

    protected $_isOffline = false;
    protected $_isInitializeNeeded = true;

    protected $_isGateway = true;

    protected $_infoBlockType = 'Mobbex\Webpay\Block\Info';

    protected $_supportedCurrencyCodes = array('ARS');

    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();

        // get order
        $order = $payment->getOrder();

        // make sure emails are sent after a successful payment
        $order->setCanSendNewEmailFlag(false);

        // set default payment status
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

        // mark customer as not notified
        $stateObject->setIsNotified(false);
    }
}
