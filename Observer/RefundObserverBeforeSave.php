<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

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

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $transaction;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customField;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Model\TransactionFactory $mobbexTransactionFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
    )
    {
        $this->sdk            = $sdk;
        $this->config         = $config;
        $this->logger         = $logger;
        $this->helper         = $helper;
        $this->transaction    = $mobbexTransactionFactory->create();
        $this->customField    = $customFieldFactory;

        // Many times, the db logger do not work in this file (because the db rollback)
        $this->logger->useFileLogger = true;

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

        if ($order->getPayment()->getMethodInstance()->getCode() != 'sugapay' || !$this->config->get('online_refund'))
            return;

        $parent = $this->transaction->getTransactions(['parent' => 1, 'order_id' => $order->getIncrementId()]);
        $childs = $this->transaction->getMobbexChilds($parent);

        try {
            if (!$parent)
                throw new \Exception("Refund Error: No parent transactions found for this order. Try again later or disable online refunds");

            if ($amount <= 0 || $amount > $order->getGrandTotal())
                throw new \Exception("Refund Error: Invalid amount provided for refund ($amount)");

            switch ($parent['operation_type']) {
                case 'payment.multiple-vendor':
                    $this->processMultivendorRefund($creditMemo, $parent, $childs);
                    break;

                case 'payment.multiple-sources':
                    $this->requestRefund($creditMemo, (count($childs) == 1 ? reset($childs) : $parent));
                    break;

                default:
                    $this->requestRefund($creditMemo, $parent);
                    break;
            }
        } catch (\Exception $e) {
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
            return $this->requestRefund($creditMemo, reset($childs));

        // If is a total refund, use parent
        if ($this->isTotalRefund($creditMemo))
            return $this->requestRefund($creditMemo, $parent);

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
        return $this->requestRefund($creditMemo, $childs[$childPos]);
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
     * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
     * @param array $transaction Transaction formatted data.
     * 
     * @throws \Mobbex\Exception See \Mobbex\Api::request() 
     */
    public function requestRefund($creditMemo, $transaction)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri' => "operations/$transaction[payment_id]/refund",
            'raw' => true,
            'body' => [
                'total' => $creditMemo->getGrandTotal() >= $transaction['total']
                    ? null
                    : $creditMemo->getGrandTotal(),
            ]
        ]);

        // If the refund was successful, ignore the next refund webhook
        if (!empty($response['result']))
            $this->customField->create()->saveCustomField(
                $transaction['payment_id'],
                'payment',
                'ignore_refund_webhook',
                true
            );

        if (!empty($response['total'])) {
            $diff = $creditMemo->getGrandTotal() - $response['total'];
            $adjustment = $diff > 0 ? 'AdjustmentNegative' : 'AdjustmentPositive';

            $creditMemo->{"set$adjustment"}($creditMemo->{"get$adjustment"}() + abs($diff));
            $creditMemo->setGrandTotal($response['total']);
        }
    }
}
