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
    ) 
    {
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
                    'quoteId'           => $this->helper->_checkoutSession->getQuote()->getId(),
                    'walletCreditCards' => $checkoutData['wallet'],
                    'paymentMethods'    => $checkoutData['paymentMethods'],
                    'config'            => [
                        'embed'           => $this->config->get('embed'),
                        'wallet'          => $this->config->get('wallet'),
                        'banner'          => $this->config->get('checkout_banner'),
                        'primaryColor'    => $this->config->get('color'),
                        'showMethodIcons' => $this->config->get('show_method_icons'),
                    ],
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
            'paymentMethods' => [],
            'wallet'         => [],
        ];

        // Set some vars to improve code readability
        $methodIcon = $this->config->get('method_icon');
        $checkoutTitle = $this->config->get('checkout_title');

        if(!empty($checkoutData['paymentMethods'])) {
            foreach ($checkoutData['paymentMethods'] as $method) {
                $mainMethod = $method['subgroup'] == 'card_input' || count($checkoutData['paymentMethods']) === 1;

                $data['paymentMethods'][] = [
                    'id'    => $method['subgroup'],
                    'value' => "$method[group]:$method[subgroup]",
                    'name'  => ($mainMethod && $checkoutTitle) || empty($method['subgroup_title']) ? $checkoutTitle : $method['subgroup_title'],
                    'image' => $mainMethod && $methodIcon ? $methodIcon : $method['subgroup_logo'],
                    'style' => "background-color:{$this->config->get('background')};",
                ];
            }
        } else {
            $data['paymentMethods'][] = [
                'id'    => 'sugapay',
                'value' => '',
                'name'  => $checkoutTitle,
                'image' => $methodIcon ?: 'https://res.mobbex.com/images/sources/mobbex.png',
            ];
        }

        if($this->config->get('wallet') && !empty($checkoutData['wallet'])) {
            foreach ($checkoutData['wallet'] as $key => $card) {
                if(!empty($card['installments'])){
                    $data['wallet'][] = [
                        'id'           => 'wallet-card-' . $key,
                        'value'        => 'card-' . $key,
                        'name'         => $card['name'],
                        'img'          => $card['source']['card']['product']['logo'],
                        'maxlength'    => $card['source']['card']['product']['code']['length'],
                        'placeholder'  => $card['source']['card']['product']['code']['name'],
                        'hiddenValue'  => $card['card']['card_number'],
                        'installments' => $card['installments']
                    ];
                }
            }
        }

        return $data;
    }
}
