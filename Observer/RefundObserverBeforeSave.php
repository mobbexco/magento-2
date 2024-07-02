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
        $creditMemo = $observer->getData('creditmemo');
        $order      = $creditMemo->getOrder();
        $amount     = $creditMemo->getGrandTotal();

        if ($order->getPayment()->getMethodInstance()->getCode() != 'sugapay')
            return;

        $parent = $this->transaction->getTransactions(['parent' => 1, 'order_id' => $order->getIncrementId()]);
        $childs = $this->transaction->getMobbexChilds($parent);

        if (!$parent || !$this->config->get('online_refund'))
            return $this->logger->log('error', 'RefundObserverBeforeSave > execute | This is not a refundable transaction.', ['transaction' => isset($parent['data']) ? $parent['data'] : [], 'online_refund' => $this->config->get('online_refund')]);

        try {
            if ($amount <= 0 || $amount > $order->getGrandTotal())
                throw new \Exception("Refund Error: Invalid amount provided for refund ($amount)");

            switch ($parent['operation_type']) {
                case 'payment.multiple-vendor':
                    $this->processMultivendorRefund($creditMemo, $parent, $childs);
                    break;

                case 'payment.multiple-sources':
                    $this->requestRefund($amount, (count($childs) == 1 ? reset($childs) : $parent)['payment_id']);
                    break;

                default:
                    $this->requestRefund($amount, $parent['payment_id']);
                    break;
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->log(
                'error', 
                'RefundObserverBeforeSave > execute | ' . $e->getMessage(), 
                [
                    'refund_amount'  => $amount,
                    'order_total'    => $order->getGrandTotal(),
                    'exception_data' => isset($e->data) ? $e->data : []
                ]
            );

            // Throw exception again to prevent creditmemo save
            throw $e;
        }
    }

    /**
     * Process a refund for a multivendor order.
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
     * @param mixed $parent 
     * @param mixed $childs
     * 
     * @return bool
     * 
     * @throws \Exception
     */
    public function processMultivendorRefund($creditMemo, $parent, $childs)
    {
        // If the parent only has one child, use it
        if (count($childs) == 1) 
            return $this->requestRefund($creditMemo->getGrandTotal(), reset($childs)['payment_id']);

        // If is a total refund, use parent
        if ($this->isTotalRefund($creditMemo))
            return $this->requestRefund($creditMemo->getOrder()->getGrandTotal(), $parent['payment_id']);

        // Get entities and remove duplicated (to check really how many entities are)
        $entities = array_unique($this->getCreditMemoEntities($creditMemo));
        $entity = reset($entities);

        if (!$entity)
            throw new \Exception("Refund Error: No entities found for the creditmemo items");

        if (count($entities) > 1) 
            throw new \Exception("Refund Error: Trying to make a partial refund on items of different entities");

        // Search entity on childs array
        $childPos = array_search($entity, array_column($childs, 'entity_uid'));

        if ($childPos === false)
            throw new \Exception("Refund Error: Not found child operation for the entity provided ($entity)");

        // Only refunds one child at a time (there is only one amount)
        return $this->requestRefund($creditMemo->getGrandTotal(), $childs[$childPos]['payment_id']);
    }

    /**
     * Get creditmemo entities.
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
     *
     * @return array
     */
    public function getCreditMemoEntities($creditMemo)
    {
        return array_map(function($item) {
            $entity = $this->helper->getEntity($item->getOrderItem());

            if (!$entity)
                throw new \Exception("Refund Error: No entity found for a creditmemo item.");

            return $entity;
        }, $creditMemo->getAllItems());
    }

    /**
     * Check if the creditmemo is a total refund.
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
     * 
     * @return bool
     */
    public function isTotalRefund($creditMemo)
    {
        return abs($creditMemo->getGrandTotal() - $creditMemo->getOrder()->getGrandTotal()) < 1;
    }

    /**
     * Make a refund request to mobbex api.
     * 
     * @param float $amount
     * @param string $operationId
     * 
     * @return bool 
     */
    public function requestRefund($amount, $operationId)
    {
        return (bool) \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "operations/$operationId/refund",
            'body'   => ['total' => floatval($amount), 'emitEvent' => false]
        ]);
    }
}
