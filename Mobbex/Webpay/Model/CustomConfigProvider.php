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
        $config = [
            'payment' => [
                'webpay' => [
                    'config' => [
                        'embed' => $this->_helper->mobbex->getEmbedPayment()
                    ]
                ]
            ]
        ];

        return $config;
    }
}
