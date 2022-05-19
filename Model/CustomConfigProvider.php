<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Mobbex\Webpay\Helper\Data;
use Mobbex\Webpay\Helper\Config;
use Magento\Quote\Model\QuoteFactory;

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
    
    protected $quoteFactory;

    /**
     * CustomConfigProvider constructor.
     * @param Data $helper
     */
    public function __construct(
        Data $helper,
        Config $config,
        QuoteFactory $quoteFactory
    ) {
        $this->_helper = $helper;
        $this->config = $config;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * @return array
     */
    public function getConfig()
    {   
        //get checkout mockup
        $checkoutData = $this->formatCheckoutData($this->_helper->getCheckoutMockup());

        $config = [
            'payment' => [
                'webpay' => [
                    'config' => [
                        'embed' => $this->config->getEmbedPayment(),
                        'wallet' => $this->config->getWalletActive()
                    ],
                    'banner'            => $this->config->getBannerCheckout(),
                    'paymentMethods'    => !empty($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [['id' => 'mbbx', 'value' => '', 'name' => $this->config->getTitleCheckout() ?: 'Pagar con Mobbex', 'image' => '']],
                    'walletCreditCards' => !empty($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
                    'returnUrl'         => !empty($checkoutData['returnUrl']) ? $checkoutData['returnUrl'] : '',
                    'paymentUrl'        => !empty($checkoutData['paymentUrl']) ? $checkoutData['paymentUrl'] : '',
                    'checkoutId'        => !empty($checkoutData['checkoutId']) ? $checkoutData['checkoutId'] : '',
                ]
            ]
        ];

        return $config;
    }

    /**
     * Get the checkout data and returns it formated
     * @param array $checkoutData
     * @return array
    */
    public function formatCheckoutData($checkoutData)
    {
        $data = [];

        if($checkoutData){

            $data = [
                'paymentMethods' => [],
                'wallet'         => [],
                'returnUrl'      => !empty($checkoutData['return_url']) ? $checkoutData['return_url'] : '',
                'paymentUrl'     => !empty($checkoutData['url']) ? $checkoutData['url'] : '',
                'checkoutId'     => !empty($checkoutData['id']) ? $checkoutData['id'] : '',
                'data'           => !empty($checkoutData) ? $checkoutData : []
            ];
    
            if(!empty($checkoutData['paymentMethods'])) {
                foreach ($checkoutData['paymentMethods'] as $method) {
                    $data['paymentMethods'][] = [
                        'id'    => $method['subgroup'],
                        'value' => $method['group'] . ':' . $method['subgroup'],
                        'name'  => $method['group'] == 'card' && $method['subgroup'] == 'card_input' && $this->config->getTitleCheckout() ? $this->config->getTitleCheckout() : $method['subgroup_title'],
                        'image' => $method['subgroup_logo']
                    ];
                }

                if(count($data['paymentMethods']) <= 1 && $this->config->getTitleCheckout())
                    $data['paymentMethods'][0]['name'] = $this->config->getTitleCheckout();

            } else {
                $data['paymentMethods'][] = [
                    'id'    => 'mobbex',
                    'value' => '',
                    'name'  => $this->config->getTitleCheckout(),
                    'image' => ''
                ];
            }
    
            if($this->config->getWalletActive() && !empty($checkoutData['wallet'])) {
                foreach ($checkoutData['wallet'] as $key => $card) {
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
