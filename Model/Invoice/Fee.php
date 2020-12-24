<?php

namespace Mobbex\Webpay\Model\Invoice;

/**
 * Class Fee
 * @package Mobbex\Webpay\Model\Invoice
 */
class Fee extends \Magento\Sales\Model\Order\Total\AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $fee = $order->getFee();

        if ($fee) {
            $invoice->setFee($fee);
            $invoice->setGrandTotal($invoice->getGrandTotal() + $fee);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $fee);
        }

        return $this;
    }
}