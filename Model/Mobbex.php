<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Model
 */
class Mobbex extends AbstractMethod
{
    const CODE = 'webpay';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var string
     */
    protected $_infoBlockType = 'Mobbex\Webpay\Block\Info';

    /**
     * @var array
     */
    protected $_supportedCurrencyCodes = ['ARS'];

    /**
     * @param string $paymentAction
     * @param object $stateObject
     * @return AbstractMethod|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();

        // get order
        $order = $payment->getOrder();

        // make sure emails are sent after a successful payment
        $order->setCanSendNewEmailFlag(false);

        // set default payment status
        $stateObject->setState(Order::STATE_NEW);
        $stateObject->setStatus(Order::STATE_NEW);

        // mark customer as not notified
        $stateObject->setIsNotified(false);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool|mixed
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }

        $checkResult = new DataObject();
        $checkResult->setData('is_available', true);

        // for future use in observers
        $this->_eventManager->dispatch(
            'payment_method_is_active',
            [
                'result' => $checkResult,
                'method_instance' => $this,
                'quote' => $quote
            ]
        );

        $orderMinAmount  = (float)$this->_scopeConfig->getValue('payment/webpay/min_amount');

        if ($orderMinAmount) {
            $orderTotal = (float)$quote->getGrandTotal();

            if ($orderTotal < $orderMinAmount) {
                $checkResult->setData('is_available', false);

                return $checkResult->getData('is_available');
            }
        }

        return $checkResult->getData('is_available');
    }
}
