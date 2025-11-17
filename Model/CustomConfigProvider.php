<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\LoginAsCustomer\Model\IsLoginAsCustomerEnabledForCustomerResult as LoginAsCustomer;

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
        \Magento\Backend\Model\Auth\Session $session,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        LoginAsCustomer $loginAsCustomer,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->sdk             = $sdk;
        $this->config          = $config;
        $this->helper          = $helper;
        $this->logger          = $logger;
        $this->quoteFactory    = $quoteFactory;
        $this->session         = $session;
        $this->customField     = $customFieldFactory->create();
        $this->logedAsCustomer = $session->isLoggedIn();

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $checkoutData = $this->config->get('unified_mode') ?: $this->helper->createCheckoutFromQuote();
        $defaultMethod = [
            'subgroup'        => '',
            'subgroup_title'  => $this->config->get('checkout_title'),
            'subgroup_logo'   => 'https://res.mobbex.com/images/sources/mobbex.png',
        ];

        

        $config = [
            'payment' => [
                'sugapay' => [
                    'quoteId'           => $this->helper->_checkoutSession->getQuote()->getId(),
                    'wallet'            => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
                    'paymentMethods'    => isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [$defaultMethod],
                    'embed'             => $this->config->get('embed'),
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
