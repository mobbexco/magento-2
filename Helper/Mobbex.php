<?php

namespace Mobbex\Webpay\Helper;

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Helper
 */
class Mobbex extends \Magento\Framework\App\Helper\AbstractHelper
{
    const VERSION = '4.2.0';

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

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Quote\Model\QuoteFactory */
    public $quoteFactory;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customFieldFactory;

    /** @var \Magento\Checkout\Model\Cart */
    public $_cart;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    /** @var \Magento\Framework\UrlInterface */
    public $_urlBuilder;

    /** @var \Magento\Checkout\Model\Session */
    public $_checkoutSession;

    /** @var \Magento\Framework\App\ResourceConnection */
    public $resourceConnection;

    public $_request;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $_storeManager,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Api\Data\OrderInterface $_orderInterface,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        \Magento\Framework\Event\ObserverFactory $observerFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Framework\App\ResourceConnection $connection
    ) {
        $this->config             = $config;
        $this->logger             = $logger;
        $this->customFieldFactory = $customFieldFactory;
        $this->quoteFactory       = $quoteFactory;
        $this->_cart              = $cart;
        $this->_order             = $order;
        $this->_checkoutSession   = $checkoutSession;
        $this->_urlBuilder        = $urlBuilder;
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
        $this->resourceConnection = $connection;
    }

    /**
     * @return bool
     */
    public function getCheckout()
    {
        // get order data
        $orderIncrementalId = $this->_checkoutSession->getLastRealOrderId();
        $orderEntityId      = $this->_checkoutSession->getLastRealOrder()->getEntityId();
        $orderData   = $this->_order->load($orderEntityId);
        $orderCustomer = $orderData->getCustomer();
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
            'createdAt'      => $orderCustomer ? \Mobbex\dateToTime($orderCustomer->getCreatedAt()) : null,
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
            $subscription = $this->config->getCatalogSetting($product->getId(), 'subscription_uid');
            $entity       = $this->getEntity($item);

            if ($subscription) {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => $subscription,
                    'total'     => round($item->getPrice(), 2)
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
        extract($this->config->getAllProductsPlans($products));
        
        if (!empty($this->_orderInterface->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $this->_orderInterface->getShippingDescription(),
                'total' => $this->_orderInterface->getShippingInclTax(),
            ];
        }

        $mobbexCheckout = new \Mobbex\Modules\Checkout(
            $orderEntityId,
            (float) $orderAmount,
            $this->getEndpointUrl('paymentreturn', ['order_id' => $orderIncrementalId]),
            $this->getEndpointUrl('webhook', ['order_id' => $orderIncrementalId, 'mbbx_token' => $this->config->generateToken()]),
            $orderData->getOrderCurrencyCode(),
            $items,
            \Mobbex\Repository::getInstallments($orderedItems, $common_plans, $advanced_plans),
            $customer,
            $this->getAddresses($orderData),
            'all',
            'mobbexCheckoutRequest',
            "Pedido #$orderIncrementalId",
            $this->config->get('custom_reference') ? $this->getCustomReference($orderEntityId) : null
        );

        //Add order id to the response
        $mobbexCheckout->response['orderId'] = $orderIncrementalId;

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
            $subscription = $this->config->getCatalogSetting($item->getProductId(), 'subscription_uid');

            if ($subscription) {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => $subscription
                ];
            } else {
                $items[] = [
                    'description' => $item->getName(),
                    'quantity'    => $item->getQty(),
                    'total'       => (float) $item->getPrice(),
                    'entity'      => $this->getEntity($item),
                ];
            }
        }

        //Get products active plans
        $products = [];
        foreach ($quote->getItemsCollection() as $item)
        $products[] = $item->getProduct();
        extract($this->config->getAllProductsPlans($products));
            
        // Add shipping item if possible
        if ($shippingAmount)
        $items[] = [
            'description' => 'Shipping',
            'total'       => $shippingAmount,
        ];

        // Get the name checking the position
        $firstName = isset($shippingAddress['firstname']) ? $shippingAddress['firstname'] : '';
        $lastName  = isset($shippingAddress['lastname'])  ? $shippingAddress['lastname']  : '';

        $customer = [
            'email'          => $quote->getCustomerEmail(),
            'name'           => "$firstName $lastName",
            'identification' => '',
            'uid'            => $quote->getCustomerId(),
            'phone'          => isset($shippingAddress['telephone']) ? $shippingAddress['telephone'] : '',
        ];
            
        try {

            $mobbexCheckout = new \Mobbex\Modules\Checkout(
                $quote->getId(),
                (float) $quote->getGrandTotal(),
                $this->getEndpointUrl('paymentreturn', ['quote_id' => $quote->getId()]),
                $this->getEndpointUrl('webhook', ['quote_id' => $quote->getId()]),
                $quote->getStore()->getCurrentCurrencyCode(),
                isset($items) ? $items : [],
                \Mobbex\Repository::getInstallments($quote->getItemsCollection(), $common_plans, $advanced_plans),
                $customer,
                $this->getAddresses($quote),
                'none',
                'mobbexQuoteCheckoutRequest',
                "Carrito #" . $quote->getId(),
                \Mobbex\Modules\Checkout::generateReference($quote->getId()) . '_DRAFT_CHECKOUT'
            );

            $this->logger->log('debug', "Helper Mobbex > getCheckoutFromQuote | Checkout Response: ", $mobbexCheckout->response); 
            
            return $mobbexCheckout->response;

        } catch (\Exception $e) {
            $this->logger->log('error', 'Helper Mobbex > getCheckoutFromQuote | '.$e->getMessage(), isset($e->data) ? $e->data : []);
            return false;
        }
        
    }

    public function getEndpointUrl($controller, $data = [])
    {   
        if($this->config->get('debug_mode'))
            $data['XDEBUG_SESSION_START'] = 'PHPSTORM';
            
        return $this->_urlBuilder->getUrl("sugapay/payment/$controller", [
            '_secure'      => true,
            '_current'     => true,
            '_use_rewrite' => true,
            '_query'       => $data,
        ]);
    }

    /**
     * Get the entity from item
     * 
     * @param object $item
     * 
     * @return string|null $entity
     */
    public function getEntity($item)
    {
        // Checks if multivendor mode is active
        if($this->config->get('multivendor') != 'active' && $this->config->get('multivendor') != 'unified')
            return;

        $product = $item->getProduct();

        // Try to get entity from product
        if($this->config->getCatalogSetting($product->getId(), 'entity'))
            return $this->config->getCatalogSetting($product->getId(), 'entity');

        // Executes our own hook to try to get entity from vnecoms vendor or product vendor
        $entity = $this->executeHook('mobbexGetVendorEntity', false, $item);

        if(!empty($entity))
            return $entity;

        // Try to get entity from category
        $categories = $product->getCategoryIds();
        foreach ($categories as $category) {
            if($this->config->getCatalogSetting($category, 'entity', 'category'))
                return $this->config->getCatalogSetting($category, 'entity', 'category'); 
        }
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
     * 
     * @param class $data
     * 
     * @return array $addresses
     */
    public function getAddresses($data)
    {
        $addresses = $addressesData = array();

        //Check if there are billing address
        if($data->getBillingAddress())
            $addressesData[] = $data->getBillingAddress()->getData();
        
        //Check if there are shipping address
        if($data->getShippingAddress())
            $addressesData[] = $data->getShippingAddress()->getData();

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
     * Obtains the custom reference seted in configs.
     * @param int $id Order id.
     * 
     * @return string
     */
    public function getCustomReference($id)
    {
        $string = $this->config->get('custom_reference');
        $connection = $this->resourceConnection->getConnection();

        // Get columns
        preg_match_all('/\{([^}]+)\}/', $string, $matches);

        foreach ($matches[0] as $index => $placeholder) {
            // Get table and column name
            $column = $matches[1][$index];

            // Get query
            $query = $connection->select()
                ->from($connection->getTableName('sales_order'), [$column])
                ->where('entity_id = :entity_id');

            // get result
            $result = $connection->fetchOne($query, ['entity_id' => (int)$id]);

            // Update reference
            $string = str_replace($placeholder, $result ?: '', $string);
        }

        return $string;
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

            $this->logger->log('debug', 'Helper Mobbex > executeHook', ['Init data', $name, $filter, gettype($value), count($observers)]);

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
                $this->logger->log('debug', 'Helper Mobbex > executeHook', ['Executed function', $observerData['instance'], $name]);

                if ($filter)
                    $args[0] = $value;
            }

            return $value;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Helper Mobbex > executeHook | Mobbex Hook Error: ', $e->getMessage());
        }
    }

}
