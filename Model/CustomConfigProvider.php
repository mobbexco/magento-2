<?php

namespace Mobbex\Webpay\Model;

/**
 * Class CustomConfigProvider
 * @package Mobbex\Webpay\Model
 */
class CustomConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    private $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    private $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    private $logger;

    /** @var \Mobbex\Webpay\Helper\Quote */
    private $quoteHelper;

    /** @var \Mobbex\Webpay\Helper\Pos */
    private $posHelper;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Quote $quoteHelper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Pos $posHelper
    ) {
        $this->sdk         = $sdk;
        $this->config      = $config;
        $this->logger      = $logger;
        $this->quoteHelper = $quoteHelper;
        $this->posHelper   = $posHelper;

        $this->sdk->init();
    }

    public function getConfig()
    {
        $defaultMethod = [
            'subgroup'        => '',
            'subgroup_title'  => $this->config->get('checkout_title'),
            'subgroup_logo'   => 'https://res.mobbex.com/images/sources/mobbex.png',
        ];

        // Only create checkout if wallet or payment_methods are active
        if ($this->config->get('wallet') || $this->config->get('payment_methods') || $this->config->get('transparent')) {
            try {
                $checkoutData = $this->quoteHelper->getCheckout(true);
            } catch (\Exception $e) {
                $this->logger->log('error', 'CustomConfigProvider > getConfig | ' . $e->getMessage(), isset($e->data) ? $e->data : []);
            }
        }

        if ($this->config->get('pos')) {
            try {
                $admin_user = $this->posHelper->getLacAdminUserId();
                $terminals = $this->posHelper->getUserAssignedPosList($this->posHelper->getLacAdminUserId());
            } catch (\Exception $e) {
                $this->logger->log('error', 'CustomConfigProvider. Error getting POS information ' . $e->getMessage(), isset($e->data) ? $e->data : []);
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
                    'offsite'           => $this->config->get('offsite') === '1', 
                    'transparent'       => $this->config->get('transparent') === '1',
                    'pos'               => $this->config->get('pos') === '1',
                    'banner'            => $this->config->get('checkout_banner'),
                    'color'             => $this->config->get('color'),
                    'background'        => $this->config->get('background'),
                    'show_method_icons' => $this->config->get('show_method_icons'),
                    'method_icon'       => $this->config->get('method_icon'),
                    'admin_user'        => isset($admin_user) ? $admin_user : null,
                    'terminals'         => isset($terminals) ? $terminals : [],
                ],
            ],
        ];

        $this->logger->log('debug', 'CustomConfigProvider > getConfig', $config);

        return $config;
    }
}
