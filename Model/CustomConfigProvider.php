<?php

namespace Mobbex\Webpay\Model;

/**
 * Class CustomConfigProvider
 * @package Mobbex\Webpay\Model
 */
class CustomConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Quote */
    public $quoteHelper;
    
    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Magento\Quote\Model\QuoteFactory */
    public $quoteFactory;

    /** @var \Magento\Backend\Model\Auth\Session */
    public $session;

    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Quote $quoteHelper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Magento\Backend\Model\Auth\Session $session,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->sdk    = $sdk;
        $this->config = $config;
        $this->quoteHelper = $quoteHelper;
        $this->logger = $logger;
        $this->helper          = $helper;
        $this->quoteFactory    = $quoteFactory;
        $this->session         = $session;
        $this->customField     = $customFieldFactory->create();

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
        if ($this->config->get('wallet') || $this->config->get('payment_methods') || $this->config->get('transparent')) {
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
                    'offsite'           => $this->config->get('offsite') === '1', 
                    'transparent'       => $this->config->get('transparent') === '1',
                    'banner'            => $this->config->get('checkout_banner'),
                    'color'             => $this->config->get('color'),
                    'background'        => $this->config->get('background'),
                    'show_method_icons' => $this->config->get('show_method_icons'),
                    'method_icon'       => $this->config->get('method_icon'),
                    'template'          =>  'Mobbex_Webpay/payment/'.(true ? 'sale_point' : 'sugapay'),
                    'terminals'         => false ? [] : $this->getMobbexPosList(),
                    'user'              => $this->helper->getImpersonation()
                ],
            ],
        ];

        //Log data in debug mode
        $this->logger->log('debug', 'CustomConfigProvider > getConfig', $config);

        return $config;
    }

    public function getMobbexPosList()
    { 
        $result = \Mobbex\Api::request([
            'method' => 'GET',
            'uri'    => "pos/",
        ]);

        $selectedPos = array_filter($result['docs'], function($pos){
            if(in_array($pos['uid'], json_decode($this->customField->getCustomField(1, 'user', 'pos_list'), true)))
                return $pos;
        });

        return $selectedPos;
    }
}
