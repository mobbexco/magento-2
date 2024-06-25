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
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var class */
    public $messageManager;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $transaction;

    public function __construct(
        Context $context,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Model\TransactionFactory $mobbexTransactionFactory
    )
    {
        $this->sdk            = $sdk;
        $this->config         = $config;
        $this->logger         = $logger;
        $this->helper         = $helper;
        $this->messageManager = $context->getMessageManager();
        $this->transaction    = $mobbexTransactionFactory->create();

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $creditmemo = $observer->getData('creditmemo');
        $amount     = $creditmemo->getGrandTotal();

        $order      = $creditmemo->getOrder();
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
            else (!empty($trx['childs']) && isset($creditmemo))
                    ? $this->processCreditmemoItemsRefunds($creditmemo, json_decode($trx['childs'], true), $order->getIncrementId())
                    : $this->processRefund($amount == $data['checkout']['total'] ? $trx['total'] : $amount, $trx['payment_id']);

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

    /**
     * Process refunds for credit memo items
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @param array $childsData
     * @param int $orderId
     */
    public function processCreditmemoItemsRefunds($creditmemo, $childsData, $orderId)
    {
        $childToRefund = [];
        $childs = $this->transaction->getMobbexChilds($childsData, $orderId);

        // Index childs by entity_uid for a faster lookup
        $childEntities = array_column($childs, null, 'entity_uid');

        foreach ($creditmemo->getAllItems() as $item) {
            $entity = $this->helper->getEntity($item->getOrderItem());
            $total  = $item->getRowTotal();
            // Set data based on item entity
            if (isset($childEntities[$entity])) {
                $child = $childEntities[$entity];
                $childToRefund[$entity]['payment_id'] = $child['payment_id'];
                $childToRefund[$entity]['total']      = ($childToRefund[$entity]['total'] ?? 0) + $total;
            }
        }
        // Process each refund
        foreach ($childToRefund as $entity => $child) {
            try {
                $this->processRefund($child['total'], $child['payment_id']);
            } catch (\Exception $e) {
                $this->logger->log(
                    'RefundObserverBeforeSave > execute | ' . $e->getMessage(), 
                    [
                        'refund_amount'  => $child['total'], 
                        'child_id'       => $child['payment_id'],
                        'entity'         => $entity,
                        'exception_data' => isset($e->data) ? $e->data : []
                    ]
                );
            }
        }
    }
}