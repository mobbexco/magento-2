<?php

namespace Mobbex\Webpay\Model\Config\Source;

class PaymentModeSelect implements \Magento\Framework\Option\ArrayInterface
{ 
    /**
     * Return array of options as value-label pairs, eg. value => label
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            'payment.v2'     => 'Disable',
            'payment.2-step' => 'Active',
        ];
    }
}