<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Mobbex\Webpay\Helper\Data;
use Mobbex\Webpay\Helper\Custom;
use Magento\Quote\Model\QuoteFactory;
    
/**
 * Class WalletPayment
 * @package Mobbex\Webpay\Controller\Payment
 */

class WalletPayment extends Action
{

    protected $resultJsonFactory;

    protected $_helper;

    protected $_customHelper;

    public function __construct(
        Context $context,
        Data $_helper,
        Custom $_customHelper,
        JsonFactory $resultJsonFactory,
        QuoteFactory $quoteFactory
    ) {
        $this->_helper = $_helper;
        $this->_customHelper = $_customHelper;
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Generates and return a Mobbex Checkout if Wallet is active
     * This functions is used by webpay.js 
     * @return array
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        $result  = $this->resultJsonFactory->create();
        //Quote data 
        $quoteData =json_decode($this->getRequest()->getParam('quote'));
        //Customer data
        $customerData = json_decode($this->getRequest()->getParam('customer'));
        //Products in the checkout
        $itemsData = json_decode($this->getRequest()->getParam('items'));
        $totals = json_decode($this->getRequest()->getParam('totals'));
        $customerAddresses = (array) $customerData->addresses;
        if($customerAddresses)
        {
            //the first key can be a number or  string its depend on user configuration
            $addressKey = array_key_first($customerAddresses);
        }

        $itemsOrden = array();
        foreach($itemsData as $item)
        {
            //$item is a Sdt class
            array_push($itemsOrden , ["product_id" => $item->product_id, "qty" => $item->qty,"price" => $item->price, "name" => $item->name]);
        }

        
        $quoteData=[
            'entity_id' => $quoteData->entity_id,
            'customer_id' => $customerData->id,
            'price' => $quoteData->grand_total,
            'currency_id'  => $quoteData->store_currency_code,
            'email'        => $quoteData->customer_email, 
            'shipping_address' =>[
                        'firstname'	   => $quoteData->customer_firstname, 
                        'lastname'	   => $quoteData->customer_lastname,
                        'street' => $customerAddresses ? $customerAddresses[$addressKey]->street[0] : '',
                        'city' => $customerAddresses ? $customerAddresses[$addressKey]->city : '',
                        'region' => '',
                        'postcode' => $customerAddresses ?  $customerAddresses[$addressKey]->postcode : '',
                        'telephone' => $customerAddresses ? $customerAddresses[$addressKey]->telephone : '',
                        'save_in_address_book' => 1
                    ],
            'items'=> $itemsOrden,
            'shipping_total'=> $totals->base_shipping_incl_tax,

        ];

        //Make the Mobbex checkout using quote data
        $checkout = $this->_helper->getCheckoutWallet($quoteData);
        $vac = [ 
            'returnUrl' => $checkout['return_url'], 
            'checkoutId' => $checkout['id'],
            //array with the stored credit cards
            'wallet' => $checkout['wallet'],
        ];

        $resultJson->setData($vac);

        return $resultJson;
    }
}
