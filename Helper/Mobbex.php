<?php

namespace Mobbex\Webpay\Helper;

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Helper
 */
class Mobbex extends \Magento\Framework\App\Helper\AbstractHelper
{
    const VERSION = '3.5.0';

    /** @var \Mobbex\Webpay\Helper\Instantiator */
    public $instantiator;

    /** @var ScopeConfigInterface */
    public $scopeConfig;

    /** @var ObjectManagerInterface */
    protected $_objectManager;

    /** @var StoreManagerInterface */
    protected $_storeManager;

    /** @var Image */
    protected $imageHelper;
    
    /** @var ProductMetadataInterface */
    protected $productMetadata;
    
    /** @var Session */
    protected $customerSession;

    /** @var \Magento\Sales\Api\Data\OrderInterface */
    protected $_orderInterface;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var \Magento\Framework\Event\ConfigInterface */
    public $eventConfig;

    /** @var \Magento\Framework\Event\ObserverFactory */
    public $observerFactory;

    /** @var \Magento\Directory\Model\RegionFactory */
    public $regionFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $_storeManager,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Api\Data\OrderInterface $_orderInterface,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        \Magento\Framework\Event\ObserverFactory $observerFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory
    ) {
        $instantiator->setProperties($this, ['config', 'logger', 'customFieldFactory', 'quoteFactory', '_cart', '_order', '_urlBuilder', '_checkoutSession']);
        $this->scopeConfig        = $scopeConfig;
        $this->_storeManager      = $_storeManager;
        $this->imageHelper        = $imageHelper;
        $this->productMetadata    = $productMetadata;
        $this->customerSession    = $customerSession;
        $this->productRepository  = $productRepository;
        $this->eventConfig        = $eventConfig;
        $this->observerFactory    = $observerFactory;
        $this->_orderInterface    = $_orderInterface;
        $this->regionFactory      = $regionFactory;
    }

    /**
     * @return bool
     */
    public function getCheckout()
    {
        // get order data
        $orderId     = $this->_checkoutSession->getLastRealOrderId();
        $orderData   = $this->_order->load($this->_checkoutSession->getLastRealOrder()->getEntityId());
        $orderAmount = round($this->_orderInterface->getData('base_grand_total'), 2);

        // Get customer data
        if ($orderData->getBillingAddress()){
            if (!empty($orderData->getBillingAddress()->getTelephone())) {
                $phone = $orderData->getBillingAddress()->getTelephone();
            }
        }

        $customer = [
            'name'           => $orderData->getCustomerName(),
            'email'          => $orderData->getCustomerEmail(), 
            'uid'            => $orderData->getCustomerId(),
            'phone'          => isset($phone) ? $phone : '',
            'identification' => $this->getDni($orderData),
        ];

        //Get Items
        $items = $products = [];
        $orderedItems = $this->_orderInterface->getAllVisibleItems();
        
        foreach ($orderedItems as $item) {

            $product      = $item->getProduct();
            $products[]   = $product;
            $price        = $item->getRowTotalInclTax() ? : $product->getFinalPrice();
            $subscription = $this->getProductSubscription($product->getId());
            $entity       = $this->getEntity($product);
            
            if($subscription['enable'] === 'yes') {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => $subscription['uid']
                ];
            } else {
                $items[] = [
                    "image"       => $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                    "description" => $product->getName(),
                    "quantity"    => $item->getQtyOrdered(),
                    "total"       => round($price, 2),
                    "entity"      => $entity
                ];
            }
        }

        //Get products active plans
        extract($this->config->getProductPlans($products));
        
        if (!empty($this->_orderInterface->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $this->_orderInterface->getShippingDescription(),
                'total' => $this->_orderInterface->getShippingInclTax(),
            ];
        }

        $mobbexCheckout = new \Mobbex\Modules\Checkout(
            $orderId,
            (float) $orderAmount,
            $this->getEndpointUrl('paymentreturn', ['order_id' => $orderId]),
            $this->getEndpointUrl('webhook', ['order_id' => $orderId]),
            $items,
            \Mobbex\Repository::getInstallments($orderedItems, $common_plans, $advanced_plans),
            $customer,
            $this->getAddresses([$orderData->getBillingAddress()->getData(), $orderData->getShippingAddress()->getData()])
        );

        //Add order id to the response
        $mobbexCheckout->response['orderId'] = $orderId;

        $this->logger->log('debug', "Helper Mobbex > getCheckout | Checkout Response: ", $mobbexCheckout->response);

        return $mobbexCheckout->response;
    }

    /**
     * Create a checkout from the given quote.
     * 
     * @param Magento\Quote\Model\Quote $quote
     * 
     * @return array
     */
    public function createCheckoutFromQuote()
    {
        // Get quote
        $quote = $this->_checkoutSession->getQuote();
        
        // Get customer and shipping data
        $shippingAddress = $quote->getBillingAddress()->getData();
        $shippingAmount  = $quote->getShippingAddress()->getShippingAmount();
        
        foreach ($quote->getItemsCollection() as $item) {
            $subscriptionConfig = $this->getProductSubscription($item->getProductId());
            
            if ($subscriptionConfig['enable'] === 'yes') {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => $subscriptionConfig['uid']
                ];
            } else {
                $items[] = [
                    'description' => $item->getName(),
                    'quantity'    => $item->getQty(),
                    'total'       => (float) $item->getPrice(),
                    'entity'      => $this->getEntity($item->getProduct()),
                ];
            }
        }

            //Get products active plans
            $products = [];
            foreach ($quote->getItemsCollection() as $item)
            $products[] = $item->getProduct();
            extract($this->config->getProductPlans($products));
            
            // Add shipping item if possible
            if ($shippingAmount)
            $items[] = [
                'description' => 'Shipping',
                'total'       => $shippingAmount,
            ];
            
            $customer = [
                'email'          => $quote->getCustomerEmail(),
                'name'           => "$shippingAddress[firstname] $shippingAddress[lastname]",
                'identification' => '',
                'uid'            => $quote->getCustomerId(),
                'phone'          => $shippingAddress['telephone'],
            ];
            
            try {

            $mobbexCheckout = new \Mobbex\Modules\Checkout(
                $quote->getId(),
                (float) $quote->getGrandTotal(),
                $this->getEndpointUrl('paymentreturn', ['quote_id' => $quote->getId()]),
                $this->getEndpointUrl('webhook', ['quote_id' => $quote->getId()]),
                isset($items) ? $items : [],
                \Mobbex\Repository::getInstallments($quote->getItemsCollection(), $common_plans, $advanced_plans),
                $customer,
                $this->getAddresses([$quote->getBillingAddress()->getData(), $quote->getShippingAddress()->getData()]),
                'none',
                'mobbexQuoteCheckoutRequest'
            );

            $this->logger->log('debug', "Helper Mobbex > getCheckoutFromQuote | Checkout Response: ", $mobbexCheckout->response); 
            
            return ['data' => $mobbexCheckout->response, 'quote_id' => $quote->getId()];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Helper Mobbex > getCheckoutFromQuote | '.$e->getMessage(), isset($e->data) ? $e->data : []);
            return false;
        }
        
    }

    public function getEndpointUrl($controller, $data = [])
    {   
        if($this->config->get('debug_mode'))
            $data['XDEBUG_SESSION_START'] = 'PHPSTORM';
            
        return $this->_urlBuilder->getUrl("webpay/payment/$controller", [
            '_secure'      => true,
            '_current'     => true,
            '_use_rewrite' => true,
            '_query'       => $data,
        ]);
    }

    /**
     * Get yhe entity of a product
     * @param object $product
     * @return string $entity
     */
    public function getEntity($product)
    {
        if($this->config->getCatalogSetting($product->getId(), 'entity'))
            return $this->config->getCatalogSetting($product->getId(), 'entity');

        $categories = $product->getCategoryIds();
        foreach ($categories as $category) {
            if($this->config->getCatalogSetting($category, 'entity', 'category'))
                return $this->config->getCatalogSetting($category, 'entity', 'category'); 
        }

        return '';
	}

    /**
     * Retrieve product subscription data.
     * 
     * @param int|string $id
     * 
     * @return array
     */
    public function getProductSubscription($id)
    {
        foreach (['is_subscription', 'subscription_uid'] as $value)
            ${$value} = $this->config->getCatalogSetting($id, $value);

        return ['enable' => $is_subscription, 'uid' => $subscription_uid];
    }

    /**
     * Get DNI configured by quote or current user if logged in.
     * 
     * @param object $order
     * 
     * @return string $dni 
     */
    public function getDni($order)
    {
        $address   = $order->getBillingAddress()->getData();
        $dniColumn = $this->config->get('dni_column');

        if ($dniColumn && isset($address[$dniColumn]))
            return $address[$dniColumn];

        $customField = $this->customFieldFactory->create();

        // Get dni custom field from quote or current user if logged in
        $customerId = $this->customerSession->getCustomer()->getId();
        $object     = $customerId ? 'customer' : 'quote';
        $rowId      = $customerId ? $customerId : $order->getQuoteId();

        return $customField->getCustomField($rowId, $object, 'dni') ?: '';
    }

    /**
     * Get Addresses data for Mobebx Checkout.
     * @param array $addressesData
     * @return array $addresses
     */
    public function getAddresses($addressesData)
    {
        $addresses = [];

        foreach ($addressesData as $address) {
            $region = $this->regionFactory->create()->load($address['region_id'])->getData();
            $street = (string) $address['street'];

            $addresses[] = [
                'type'         => isset($address["address_type"]) ? $address["address_type"] : '',
                'country'      => isset($address["country_id"]) ? \Mobbex\Repository::convertCountryCode($address["country_id"]) : '',
                'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', trim($street))),
                'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', trim($street)), '', trim($street)),
                'streetNotes'  => '',
                'zipCode'      => isset($address["postcode"]) ? $address["postcode"] : '',
                'city'         => isset($address["city"]) ? $address["city"] : '',
                'state'        => (isset($address["country_id"]) && isset($region['code'])) ? str_replace((string) $address["country_id"] . '-', '', (string) $region['code']) : ''
            ];
        }

        return $addresses;
    }

    /**
     * Execute a hook and retrieve the response.
     * 
     * @param string $name The hook name (in camel case).
     * @param bool $filter Filter first arg in each execution.
     * @param mixed ...$args Arguments to pass.
     * 
     * @return mixed Last execution response or value filtered. Null on exceptions.
     */
    public function executeHook($name, $filter = false, ...$args)
    {
        try {
            // Use snake case to search event
            $eventName = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');

            // Get registered observers and first arg to return as default
            $observers = $this->eventConfig->getObservers($eventName) ?: [];
            $value     = $filter ? reset($args) : false;

            foreach ($observers as $observerData) {
                // Instance observer
                $instanceMethod = !empty($observerData['shared']) ? 'get' : 'create';
                $observer       = $this->observerFactory->$instanceMethod($observerData['instance']);

                // Get method to execute
                $method = [$observer, $name];

                // Only execute if is callable
                if (!empty($observerData['disabled']) || !is_callable($method))
                    continue;

                $value = call_user_func_array($method, $args);

                if ($filter)
                    $args[0] = $value;
            }

            return $value;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Helper Mobbex > executeHook | Mobbex Hook Error: ', $e->getMessage());
        }
    }

}
