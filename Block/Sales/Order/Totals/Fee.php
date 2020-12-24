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

    /**
     * Get totals source model
     *
     * @return \Magento\Framework\DataObject
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals()
    {
        if(!$this->getSource()->getFee()) {
            return $this;
        }

        $fee = new \Magento\Framework\DataObject(
            [
                'code' => 'fee',
                'strong' => false,
                'value' => $this->getSource()->getFee(),
                'label' => __('Payment fee'),
            ]
        );

        $this->getParentBlock()->addTotalBefore($fee, 'grand_total');

        return $this;
    }
}