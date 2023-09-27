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

        if ($paymentMethod != 'sugapay')
            return;

        $trx = $this->transaction->getTransactions(['parent' => 1, 'order_id' => $order->getIncrementId()]);

        if (!$trx || !isset($trx['data']) || !$this->config->get('online_refund'))
            return $this->logger->log('error', 'RefundObserverBeforeSave > execute | This is not a refundable transaction.', ['transaction' => isset($trx['data']) ? $trx['data'] : [], 'online_refund' => $this->config->get('online_refund')]);

        $data = json_decode($trx['data'], true);

        try {

            if ($amount <= 0 || !isset($data['checkout']['total']) || $amount > $data['checkout']['total'])
                throw new \Exception('Refund Error: Sorry! This is not a refundable transaction. Try again in the Mobbex console');
            else
                $this->processRefund($amount == $data['checkout']['total'] ? $trx['total'] : $amount, $trx['payment_id']);

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
            $this->logger->log(
                'error', 
                'RefundObserverBeforeSave > execute | ' . $e->getMessage(), 
                ['refund_amount' => $amount, 'checkout_total' => isset($data['checkout']['total']) ? $data['checkout']['total'] : '', 'exception_data' => isset($e->data) ? $e->data : []]
            );
        }
    }

    public function processRefund($amount, $paymentId)
    {
            $result = \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => 'operations/' . $paymentId . '/refund',
                'body'   => ['total' => floatval($amount), 'emitEvent' => false]
            ]) ?: [];

            return !empty($result);
    }
}
