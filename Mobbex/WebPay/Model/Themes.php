<?php
namespace Mobbex\WebPay\Model;

class Themes implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'light', 'label' => __('Claro')],
            ['value' => 'dark', 'label' => __('Oscuro')]
        ];
    }
}
