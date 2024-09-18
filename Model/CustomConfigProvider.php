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

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Quote\Model\QuoteFactory */
    public $quoteFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        $this->sdk          = $sdk;
        $this->config       = $config;
        $this->helper       = $helper;
        $this->logger       = $logger;
        $this->quoteFactory = $quoteFactory;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $checkoutData = $this->formatCheckoutData(
            $this->config->get('unified_mode') ?: $this->helper->createCheckoutFromQuote()
        );

        $config = [
            'payment' => [
                'sugapay' => [
                    'quoteId'        => $this->helper->_checkoutSession->getQuote()->getId(),
                    'wallet'         => $checkoutData['wallet'],
                    'paymentMethods' => $checkoutData['paymentMethods'],
                    'embed'          => $this->config->get('embed'),
                    'banner'         => $this->config->get('checkout_banner'),
                    'color'          => $this->config->get('color'),
                    'background'     => $this->config->get('background'),
                ],
            ],
        ];

        //Log data in debug mode
        $this->logger->log('debug', 'CustomConfigProvider > getConfig', $config);

        return $config;
    }

    /**
     * Get the checkout data and returns it formated
     * @param array $checkoutData
     * @return array
     */
    public function formatCheckoutData($checkoutData)
    {
        $data = [
            'paymentMethods' => $checkoutData['paymentMethods'],
            'wallet'         => $checkoutData['wallet'],
        ];

        if (empty($checkoutData['paymentMethods'])) {
            $data['paymentMethods'][] = [
                'id'    => 'sugapay',
                'value' => '',
                'name'  => $this->config->get('checkout_title'),
                'image' => $this->config->get('show_method_icons') ? 'https://res.mobbex.com/images/sources/mobbex.png' : ''
            ];
        }

        return $data;
    }
}
