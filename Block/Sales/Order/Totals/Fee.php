<?php

namespace Mobbex\Webpay\Block\Sales\Order\Totals;

use Magento\Sales\Model\Order;

/**
 * Class Fee 
 *
 * @package Mobbex\Webpay\Block\Sales\Order\Totals
 */
class Fee extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Framework\DataObject
     */
    protected $_source;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $mobbexTransaction;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Mobbex\Webpay\Model\TransactionFactory $mobbexTransactionFactory,
        $data = []
    ) {
        parent::__construct($context, $data);
        $this->mobbexTransaction = $mobbexTransactionFactory->create();
    }

    public function initTotals()
    {
        // Get order (or invoice/credit memo) and fee
        $order = $this->getParentBlock()->getSource();
        $fee = $order->getFee();

        if ($fee == 0)
            return $this;

        $fee = new \Magento\Framework\DataObject([
            'code'   => 'fee',
            'strong' => false,
            'value'  => $fee,
            'label'  => sprintf(
                '%s financiero (%s)',
                $fee > 0 ? 'Costo' : 'Descuento',
                $this->getPlanDescription($order)
            ),
        ]);

        $this->getParentBlock()->addTotalBefore($fee, 'grand_total');

        return $this;
    }

    public function getPlanDescription($order)
    {
        $transaction = $this->mobbexTransaction->getTransactions(['order_id' => $order->getIncrementId(), 'parent' => 1]);
        return $transaction && isset($transaction['installment_name']) ? $transaction['installment_name'] : '';
    }
}