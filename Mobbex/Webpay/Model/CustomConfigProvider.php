<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Mobbex\Webpay\Helper\Data;

/**
 * Class CustomConfigProvider
 * @package Mobbex\Webpay\Model
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Data
     */
    protected $_helper;

    /**
     * CustomConfigProvider constructor.
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->_helper = $helper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        // TODO: For Future Use. Keep It
        $config = [
            'payment' => [
                'webpay' => [
                    'config' => false
                ]
            ]
        ];

        return $config;
    }
}
