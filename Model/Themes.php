<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Themes
 * @package Mobbex\Webpay\Model
 */
class Themes implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'light', 'label' => __('Light')],
            ['value' => 'dark', 'label' => __('Dark')]
        ];
    }
}
