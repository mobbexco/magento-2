<?php

namespace Mobbex\Webpay\Model\Method;

/**
 * Class Sugapay.
 * 
 * @package Mobbex\Webpay\Model\Method
 */
class SugapayPos extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'sugapay_pos';

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
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

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

        $sendEmail = $this->_scopeConfig->getValue(
            'payment/sugapay/checkout/email_settings/email_before_payment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Set if can send email
        $order->setCanSendNewEmailFlag(!!$sendEmail);

        // set default payment status
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_NEW);

        // Set if is notified
        $stateObject->setIsNotified(!!$sendEmail);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool|mixed
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return true;
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }

        $checkResult = new \Magento\Framework\DataObject();
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

        $orderMinAmount  = (float)$this->_scopeConfig->getValue('payment/sugapay/min_amount');

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

//https://deepwiki.com/search/como-hace-para-hacer-los-facad_f445372b-2cad-4ec8-8208-6694aeb94fb4?mode=fast