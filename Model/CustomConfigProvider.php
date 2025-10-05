<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class CustomConfigProvider
 * @package Mobbex\Webpay\Model
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Quote */
    public $quoteHelper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Quote $quoteHelper,
        \Mobbex\Webpay\Helper\Logger $logger
    ) {
        $this->sdk    = $sdk;
        $this->config = $config;
        $this->quoteHelper = $quoteHelper;
        $this->logger = $logger;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $defaultMethod = [
            'subgroup'        => '',
            'subgroup_title'  => $this->config->get('checkout_title'),
            'subgroup_logo'   => 'https://res.mobbex.com/images/sources/mobbex.png',
        ];

        // Only create checkout if wallet or payment_methods are active
        if ($this->config->get('wallet') || $this->config->get('payment_methods')) {
            try {
                $checkoutData = $this->quoteHelper->getCheckout(true);
            } catch (\Exception $e) {
                $this->logger->log('error', 'CustomConfigProvider > getConfig | ' . $e->getMessage(), isset($e->data) ? $e->data : []);
            }
        }

        $config = [
            'payment' => [
                'sugapay' => [
                    'quoteId'           => $this->quoteHelper->checkoutSession->getQuote()->getId(),
                    'checkoutId'        => isset($checkoutData['id']) ? $checkoutData['id'] : null,
                    'intentToken'       => isset($checkoutData['intent']['token']) ? $checkoutData['intent']['token'] : null,
                    'wallet'            => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
                    'paymentMethods'    => isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [$defaultMethod],
                    'embed'             => $this->config->get('embed'),
                    'banner'            => $this->config->get('checkout_banner'),
                    'color'             => $this->config->get('color'),
                    'background'        => $this->config->get('background'),
                    'show_method_icons' => $this->config->get('show_method_icons'),
                    'method_icon'       => $this->config->get('method_icon'),
                ],
            ],
        ];

        //Log data in debug mode
        $this->logger->log('debug', 'CustomConfigProvider > getConfig', $config);

        return $config;
    }
}
