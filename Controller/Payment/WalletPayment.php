<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Mobbex\Webpay\Helper\Data;

/**
 * Class WalletPayment
 * @package Mobbex\Webpay\Controller\Payment
 */

class WalletPayment extends Action
{

    protected $resultJsonFactory;

    protected $_helper;

    public function __construct(
        Context $context,
        Data $_helper,
        JsonFactory $resultJsonFactory
    ) {
        $this->_helper = $_helper;
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * This functions is used by webpay.js 
     */
    public function execute()
    {
    
        $result  = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) 
        {
            $quoteData =json_decode($this->getRequest()->getParam('quote'));
            $customerData = json_decode($this->getRequest()->getParam('customer'));
            $itemsData = json_decode($this->getRequest()->getParam('items'));
            $customerAddresses = (array) $customerData->addresses;
            //the first key can be a number or  string its depend on user configuration
            $addressKey = array_key_first($customerAddresses);

            error_log("[itemsData : ".$itemsData." ]", 3, "/var/www/html/magento2.2/vendor/mobbexco/magento-2/walletController.log");
            //error_log("[Quote : ".$quoteData." ]", 3, "/var/www/html/magento2.2/vendor/mobbexco/magento-2/walletController.log");
            
            
            //grand_total
            //customer_is_guest
/*
            $tempOrder=[
                'currency_id'  => $quoteData->store_currency_code,
                'email'        => $quoteData->customer_email, //buyer email id
                'shipping_address' =>[
                            'firstname'	   => $quoteData->customer_firstname, //address Details
                            'lastname'	   => $quoteData->customer_lastname,
                            'street' => $customerAddresses[$addressKey]->street[0],
                            'city' => $customerAddresses[$addressKey]->city,
                            'region' => '',
                            'postcode' => $customerAddresses[$addressKey]->postcode,
                            'telephone' => $customerAddresses[$addressKey]->telephone,
                            'fax' => '',
                            'save_in_address_book' => 1
                        ],
                'items'=> [ //array of product which order you want to create
                            ['product_id'=>'1','qty'=>1],
                            ['product_id'=>'2','qty'=>2]
                        ]
           ];
*/
        }

        
       
       
       return null;

        /*$resultJson = $this->resultJsonFactory->create();

        $checkout = $this->_helper->getCheckout();
        $vac = [ 
            'returnUrl' => $checkout['return_url'], 
            'checkoutId' => $checkout['id']
        ];

        $resultJson->setData($vac);

        return $resultJson;*/
    }
}
