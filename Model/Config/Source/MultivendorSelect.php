<?php
namespace Mobbex\Webpay\Model\Config\Source;

class MultivendorSelect implements \Magento\Framework\Option\ArrayInterface
{ 
    /**
     * Return array of options as value-label pairs, eg. value => label
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ''        => 'Disable',
            'active'  => 'Active',
            'unified' => 'Unified',
        ];
    }
}