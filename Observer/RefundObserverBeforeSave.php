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
        $instantiator->setProperties($this, ['logger', 'config', 'mobbexTransactionFactory', 'sdk']);
        $this->transaction = $this->mobbexTransactionFactory->create();
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getData('creditmemo');
        $amount     = $creditMemo->getGrandTotal();

        $order      = $creditMemo->getOrder();
        $payment    = $order->getPayment();

        $paymentMethod = $payment->getMethodInstance()->getCode();

        if ($paymentMethod != 'webpay')
            return;

        $trx = $this->transaction->getTransactions(['parent' => 1, 'order_id' => $order->getIncrementId()]);

        if (!$trx || !$this->config->get('online_refund'))
            return;

        $data = json_decode($trx['data'], true);

        if ($amount <= 0 || $amount > $data['checkout']['total']) {
            
            $message = __('Refund Error: Sorry! This is not a refundable transaction. Try again in the Mobbex console');
            $this->messageManager->addErrorMessage($message);
            
            // If amount is invalid throw exception
            try {
                throw new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase($message));
            } catch (\Exception $e) {
                $this->logger->log('error', "RefundObserverBeforeSave > execute | $message");
            }

        } else {
            $this->processRefund($amount == $data['checkout']['total'] ? $trx['total'] : $amount, $trx['payment_id']);
        }

    }

    public function processRefund($amount, $paymentId)
    {
        try {

            $result = \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => 'operations/' . $paymentId . '/refund',
                'body'   => ['total' => floatval($amount), 'emitEvent' => false]
            ]) ?: [];

            return !empty($result);

        } catch (\Exception $e) {
            $this->logger->log('error', 'RefundObserverBeforeSave > execute | ' . $e->getMessage(), isset($e->data) ? $e->data : []);
        }
    }
}
