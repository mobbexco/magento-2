<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class CustomConfigProvider implements ConfigProviderInterface
{

    protected $_helper;

    public function __construct(
        \Mobbex\Webpay\Helper\Data $helper
    ) {
        $this->_helper = $helper;
    }

    // get configuration
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
