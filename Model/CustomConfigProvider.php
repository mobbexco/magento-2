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
        $checkoutData = $this->formatCheckoutData($this->_helper->getCheckoutMockup($this->getQuotedata()));

        $config = [
            'payment' => [
                'webpay' => [
                    'config' => [
                        'embed' => $this->config->getEmbedPayment(),
                        'wallet' => $this->config->getWalletActive()
                    ],
                    'banner'            => $this->config->getBannerCheckout(),
                    'paymentMethods'    => $checkoutData['paymentMethods'] ?: [],
                    'walletCreditCards' => $checkoutData['wallet'] ?: [],
                    'returnUrl'         => $checkoutData['returnUrl'],
                    'paymentUrl'        => $checkoutData['paymentUrl'],
                    'checkoutId'        => $checkoutData['checkoutId'],
                ]
            ]
        ];

        return $config;
    }

    /**
     * Get the order data from Quote.
     * @return array
     */
    public function getQuoteData()
    {
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $cartObj        = $objectManager->get('\Magento\Checkout\Model\Cart');
        $quote          = $cartObj->getQuote();
        $items          = $quote->getItemsCollection();
        $shipAdressData = $quote->getBillingAddress()->getData();

        //Get ordered items
        foreach ($items as $item) {
            $orderedItems[] = [
                "product_id" => $item->getProductId(),
                "name"       => $item->getName(),
                "qty"        => $item->getQty(),
                "price"      => $item->getPrice(),
            ];
        }

        $quoteData = [
            'entity_id'        => $quote->getId(),
            'customer_id'      => $quote->getCustomer()->getId(),
            'price'            => $quote->getGrandTotal(),
            'currency_id'      => $quote->getStore()->getCurrentCurrency()->getCode(),
            'email'            => $quote->getCustomerEmail(),
            'shipping_address' => [
                'firstname'            => $shipAdressData['firstname'],
                'lastname'             => $shipAdressData['lastname'],
                'street'               => $shipAdressData['street'],
                'city'                 => $shipAdressData['city'],
                'region'               => $shipAdressData['region'],
                'postcode'             => $shipAdressData['postcode'],
                'telephone'            => $shipAdressData['telephone'],
                'save_in_address_book' => 1
            ],
            'items'          => $orderedItems,
            'shipping_total' => $quote->getShippingAddress()->getShippingAmount(),
        ];

        return $quoteData;
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
            'returnUrl'      => $checkoutData['return_url'],
            'paymentUrl'     => $checkoutData['url'],
            'checkoutId'     => $checkoutData['id'],
            'data'           => $checkoutData
        ];

        if($checkoutData['paymentMethods']) {
            foreach ($checkoutData['paymentMethods'] as $method) {
                $data['paymentMethods'][] = [
                    'id'    => $method['subgroup'],
                    'value' => $method['group'] . ':' . $method['subgroup'],
                    'name'  => $method['subgroup_title'],
                    'image' => $method['subgroup_logo']
                ];
            }
        }

        if($this->config->getWalletActive() && isset($checkoutData['wallet'])) {
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

        return $data;
    }
}
