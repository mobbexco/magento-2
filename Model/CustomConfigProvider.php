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
        $checkoutData = $this->formatCheckoutData(
            $this->config->get('unified_mode') ?: $this->helper->createCheckoutFromQuote()
        );

        $config = [
            'payment' => [
                'webpay' => [
                    'walletCreditCards' => $checkoutData['wallet'],
                    'paymentMethods'    => $checkoutData['paymentMethods'],
                    'config'            => [
                        'embed'  => $this->config->get('embed'),
                        'wallet' => $this->config->get('wallet'),
                        'banner' => $this->config->get('checkout_banner'),
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
                'id'    => 'webpay',
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
