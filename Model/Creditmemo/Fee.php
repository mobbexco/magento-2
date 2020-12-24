<?php

namespace Mobbex\Webpay\Model\Creditmemo;

/**
 * Class Fee
 * @package Mobbex\Webpay\Model\Creditmemo
 */
class Fee extends \Magento\Sales\Model\Order\Total\AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Creditmemo $creditMemo
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditMemo)
    {
        $order = $creditMemo->getOrder();
        $fee = $order->getFee();

        if ($fee) {
            $creditMemo->setFee($fee);
            $creditMemo->setGrandTotal($creditMemo->getGrandTotal() + $fee);
            $creditMemo->setBaseGrandTotal($creditMemo->getBaseGrandTotal() + $fee);
        }

        return $this;
    }
}