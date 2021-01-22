<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Mobbex\Webpay\Helper\Data;
use Mobbex\Webpay\Helper\Config;

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
     * @var Config
     */
    protected $config;
    /**
     * CustomConfigProvider constructor.
     * @param Data $helper
     */
    public function __construct(
        Data $helper,
        Config $config
    ) {
        $this->_helper = $helper;
        $this->config = $config;
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
                        'embed' => $this->config->getEmbedPayment()
                    ],
                    'banner' => $this->config->getBannerCheckout()
                ]
            ]
        ];

        return $config;
    }
}
