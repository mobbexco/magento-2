<?php

namespace Mobbex\Webpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class CustomConfigProvider
 * @package Mobbex\Webpay\Model
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    /**
     * CustomConfigProvider constructor.
     */
    public function __construct(\Mobbex\Webpay\Helper\Instantiator $instantiator) 
    {
        $instantiator->setProperties($this, ['sdk', 'config', 'helper', 'logger', 'quoteFactory']);
    }

    /**
     * @return array
     */
    public function getConfig()
    {   
        //get checkout mockup
        $checkoutData = $this->formatCheckoutData($this->helper->createCheckoutFromQuote());

        $config = [
            'payment' => [
                'webpay' => [
                    'config' => [
                        'embed' => $this->config->get('embed'),
                        'wallet' => $this->config->get('wallet')
                    ],
                    'banner'            => $this->config->get('checkout_banner'),
                    'paymentMethods'    => !empty($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [['id' => 'mbbx', 'value' => '', 'name' => $this->config->get('checkout_title') ?: 'Pagar con Mobbex', 'image' => '']],
                    'walletCreditCards' => !empty($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
                    'paymentUrl'        => !empty($checkoutData['paymentUrl']) ? $checkoutData['paymentUrl'] : '',
                    'checkoutId'        => !empty($checkoutData['checkoutId']) ? $checkoutData['checkoutId'] : '',
                    'orderId'           => !empty($checkoutData['orderId']) ? $checkoutData['orderId'] : '',
                ]
            ]
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
            'paymentUrl'     => !empty($checkoutData['data']['url']) ? $checkoutData['data']['url'] : '',
            'checkoutId'     => !empty($checkoutData['data']['id']) ? $checkoutData['data']['id'] : '',
            'orderId'        => !empty($checkoutData['order_id']) ? $checkoutData['order_id'] : '',
            'data'           => !empty($checkoutData['data']) ? $checkoutData['data'] : []
        ];

        if(!empty($checkoutData['data']['paymentMethods'])) {
            foreach ($checkoutData['data']['paymentMethods'] as $method) {
                $data['paymentMethods'][] = [
                    'id'    => $method['subgroup'],
                    'value' => $method['group'] . ':' . $method['subgroup'],
                    'name'  => $method['group'] == 'card' && $method['subgroup'] == 'card_input' && $this->config->get('checkout_title') ? $this->config->get('checkout_title') : $method['subgroup_title'],
                    'image' => $method['subgroup_logo']
                ];
            }
            if(count($data['paymentMethods']) == 1 && $this->config->get('checkout_title'))
                $data['paymentMethods'][0]['name'] = $this->config->get('checkout_title');
                
        } else {
            $data['paymentMethods'][] = [
                'id'    => 'mobbex',
                'value' => '',
                'name'  => $this->config->get('checkout_title'),
                'image' => ''
            ];
        }

        if($this->config->get('wallet') && !empty($checkoutData['data']['wallet'])) {
            foreach ($checkoutData['data']['wallet'] as $key => $card) {
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
