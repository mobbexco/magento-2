<?php

namespace Mobbex\Webpay\Model;

class OrderRepository
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Sales\Api\OrderRepositoryInterface */
    public $orderRepository;

    /** @var \Magento\Framework\Api\SearchCriteriaBuilder */
    public $searchBuilder;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $transaction;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Model\Transaction $transaction,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchBuilder
    ) {
        $this->config          = $config;
        $this->transaction     = $transaction;
        $this->orderRepository = $orderRepository;
        $this->searchBuilder   = $searchBuilder;
    }

    /**
     * Get all the orders created using this payment method.
     * 
     * @param mixed[] $filters [
     *  [
     *      @type string Column name.
     *      @type mixed Column value.
     *      @type string Condition type. Default eq.
     *  ]
     * ]
     * 
     * @return array
     */
    public function getOrders(...$filters)
    {
        // First add filters to query
        foreach ($filters as $filter)
            $this->searchBuilder->addFilter(...$filter);

        return array_filter(
            $this->orderRepository->getList($this->searchBuilder->create())->getItems() ?: [],
            [$this, 'isCreatedByMobbex']
        );
    }

    /**
     * Get the orders created that has not any transaction.
     * 
     * @return array 
     */
    public function getNewOrders()
    {
        return array_filter(
            $this->getOrders(['status', 'pending']),
            function(...$args) {
                return !$this->hasTransaction(...$args);
            }
        );
    }

    /**
     * Retrieve true if the order was created by this payment method.
     * 
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * 
     * @return bool 
     */
    public function isCreatedByMobbex($order)
    {
        return $order->getPayment() && $order->getPayment()->getMethod() == 'sugapay';
    }

    /**
     * Retrieve true if the order has any trx saved in db.
     * 
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * 
     * @return bool 
     */
    public function hasTransaction($order)
    {
        return (bool) $this->transaction->getTransactions([
            'parent'   => 1,
            'order_id' => $order->getIncrementId()
        ]);
    }
}
