<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class RefundObserverBeforeSave
 * @package Mobbex\Webpay\Observer
 */
class RefundObserverBeforeSave implements ObserverInterface
{
    /**
     * @var Context
     */
    protected $context;
    
    /**
     * @var \Mobbex\Webpay\Helper\Instantiator
     */
    protected $instantiator;


    public function __construct(Context $context, \Mobbex\Webpay\Helper\Instantiator $instantiator)
    {
        $this->messageManager = $context->getMessageManager();
        $instantiator->setProperties($this, ['logger']);
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getData('creditmemo');
        $amount = $creditMemo->getGrandTotal();

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();

        $paymentMethod = $payment->getMethodInstance()->getCode();

        if ($paymentMethod != 'webpay') {
            return;
        }
        
        $paymentId = $payment->getAdditionalInformation('mobbex_data')['payment']['id'];


        // If amount is invalid throw exception
        if ($amount <= 0) {
            $message = __('Refund Error: Sorry! This is not a refundable transaction.');
            $this->messageManager->addErrorMessage($message);
            $this->logger->debug('err', $message);

            throw new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase($message));
        }

        $this->processRefund($amount, $paymentId);
    }

    public function processRefund($amount, $paymentId)
    {
        try {

            $result = \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => "operations/" . $paymentId . '/refund',
                'body'   => json_encode(['total' => floatval($amount)])
            ]) ?: [];

            return !empty($result);

        } catch (\Exception $e) {
            $this->logger->debug('err', $e->getMessage(), isset($e->data) ? $e->data : []);
        }
    }
}
