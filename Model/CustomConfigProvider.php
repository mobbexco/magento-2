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
        $this->checkoutData = $this->_helper->getCheckoutMockup();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $checkoutData = $this->checkoutData;
        $config = [
            'payment' => [
                'webpay' => [
                    'config' => [
                        'embed' => $this->config->getEmbedPayment(),
                        'wallet' => $this->config->getWalletActive()
                    ],
                    'banner'         => $this->config->getBannerCheckout(),
                    'paymentMethods' => isset($this->checkoutData['paymentMethods']) ? $this->checkoutData['paymentMethods'] : [],
                ]
            ]
        ];

        return $config;
    }


}
