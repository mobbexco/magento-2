<?php

namespace Mobbex\Webpay\Helper;

use Mobbex\Webpay\Helper\Config;
use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Catalog\Model\ProductRepository;

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Helper
 */
class Mobbex extends AbstractHelper
{
    const VERSION = '3.1.2';

    /**
     * @var Config
     */
    public $config;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * @var OrderInterface
     */
    public $order;

    /**
     * @var Order
     */
    public $modelOrder;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var CustomFieldFactory
     */
    protected $_customFieldFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /** @var \Magento\Framework\Event\ConfigInterface */ 
    public $eventConfig;

    /** @var \Magento\Framework\Event\ObserverFactory */
    public $observerFactory;

    /**
     * Mobbex constructor.
     * @param Config $config
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderInterface $order
     * @param Order $modelOrder
     * @param Cart $cart
     * @param ObjectManagerInterface $_objectManager
     * @param StoreManagerInterface $_storeManager
     * @param LoggerInterface $logger
     * @param UrlInterface $urlBuilder
     * @param Image $imageHelper
     * @param CustomFieldFactory $_customFieldFactory
     * @param ProductMetadataInterface $productMetadata
     * @param QuoteFactory $quoteFactory
     * @param Session $customerSession
     * @param EventManager $eventManager
     * @param SessionManagerInterface $session
     * @param ProductRepository $productRepository
     */
    public function __construct(
        Config $config,
        ScopeConfigInterface $scopeConfig,
        OrderInterface $order,
        Order $modelOrder,
        Cart $cart,
        ObjectManagerInterface $_objectManager,
        StoreManagerInterface $_storeManager,
        LoggerInterface $logger,
        UrlInterface $urlBuilder,
        Image $imageHelper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        QuoteFactory $quoteFactory,
        ProductMetadataInterface $productMetadata,
        Session $customerSession,
        ProductRepository $productRepository,
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        \Magento\Framework\Event\ObserverFactory $observerFactory
    ) {
        $this->config = $config;
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->quoteFactory = $quoteFactory;
        $this->_storeManager = $_storeManager;
        $this->_objectManager = $_objectManager;
        $this->log = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->imageHelper = $imageHelper;
        $this->_customFieldFactory = $customFieldFactory;
        $this->customFields = $customFieldFactory->create();
        $this->productMetadata = $productMetadata;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->eventConfig     = $eventConfig;
        $this->observerFactory = $observerFactory;
    }

    /**
     * @return bool
     */
    public function createCheckout($checkout)
    {
        $curl = curl_init();

        // get order object
        $this->order->loadByIncrementId($checkout->getLastRealOrder()->getEntityId());

        // get extra order data
        $orderData = $this->modelOrder->load($checkout->getLastRealOrder()->getEntityId());

        // get oder id
        $orderId = $checkout->getLastRealOrderId();

        // set order description as #ORDERID
        $description = __('Orden #') . $checkout->getLastRealOrderId();

        // get order amount
        $orderAmount = round($this->order->getData('base_grand_total'), 2);

        // Get phone
        $phone = '';
        if ($orderData->getBillingAddress()){
            if (!empty($orderData->getBillingAddress()->getTelephone())) {
                $phone = $orderData->getBillingAddress()->getTelephone();
            }
        }

        // Get customer data
        $customer = [
            'name'           => $orderData->getCustomerName(),
            'email'          => $orderData->getCustomerEmail(), 
            'uid'            => $orderData->getCustomerId(),
            'phone'          => $phone,
            'identification' => $this->getDni($orderData, $orderData->getQuoteId()),
        ];

        $items = [];
        $orderedItems = $this->order->getAllVisibleItems();

        foreach ($orderedItems as $item) {

            $product = $item->getProduct();
            $price = $item->getRowTotalInclTax() ? : $product->getFinalPrice();
            $subscription = $this->getProductSubscription($product->getId());
            $entity  = $this->getEntity($product);

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

        if (!empty($this->order->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $this->order->getShippingDescription(),
                'total' => $this->order->getShippingInclTax(),
            ];
        }

        //wallet
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $userSession = $objectManager->get('Magento\Customer\Model\Session');
        $is_wallet_active = ((bool) ($this->config->getWalletActive()) && $userSession->isLoggedIn());

        // Create data
        $data = $this->executeHook('mobbexCheckoutRequest', true, [
            'reference'    => $this->getReference($orderId),
            'currency'     => 'ARS',
            'description'  => $description,
            'test'         => (bool) ($this->config->getTestMode()),
            'return_url'   => $this->getEndpointUrl('paymentreturn', ['order_id' => $orderId]),
            'webhook'      => $this->getEndpointUrl('webhook', ['order_id' => $orderId]),
            'items'        => $items,
            "options"      => [
                "button" => (bool) ($this->config->getEmbedPayment()),
                "domain" => $this->urlBuilder->getUrl('/'),
                "theme"  => $this->getTheme(),
                "redirect" => [
                    "success" => true,
                    "failure" => false,
                ],
                "platform" => $this->getPlatform(),
            ],
            "multicard"    => (bool) ($this->config->getMulticard()),
            "multivendor"  => $this->config->getMultivendor() === 'disable' ? false : $this->config->getMultivendor(),
            "merchants"    => $this->getMerchants($items),
            'total'        => (float) $orderAmount,
            'customer'     => $customer,
            'installments' => $this->getInstallments($orderedItems),
            'timeout'      => 5,
            'wallet'       => $is_wallet_active
        ], $orderData);

        if($this->config->getDebugMode())
        {
            Data::log("Checkout Headers:" . print_r($this->getHeaders(), true), "mobbex_debug_" . date('m_Y') . ".log");
            Data::log("Checkout Headers:" . print_r($data, true), "mobbex_debug_" . date('m_Y') . ".log");
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $this->getHeaders(),
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Data::log("Checkout Error:" . print_r($err, true), "mobbex_error_" . date('m_Y') . ".log");
            return false;
        } else {
            $res = json_decode($response, true);
            Data::log("Checkout Response:" . print_r($res, true), "mobbex_" . date('m_Y') . ".log");
            $res['data']['return_url'] = $data['return_url']; 
            return $res['data'];
        }
    }

    /**
     * Create a checkout from the given quote.
     * 
     * @param Magento\Quote\Model\Quote $quote
     * 
     * @return array
     */
    public function createCheckoutFromQuote($quote)
    {
        // Get customer and shipping data
        $shippingAddress = $quote->getBillingAddress()->getData();
        $shippingAmount  = $quote->getShippingAddress()->getShippingAmount();

        foreach ($quote->getItemsCollection() as $item) {
            $subscriptionConfig = $this->getProductSubscription($item->getProductId());

            if($subscriptionConfig['enable'] === 'yes') {
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

        // Add shipping item if possible
        if ($shippingAmount)
            $items[] = [
                'description' => 'Shipping',
                'total'       => $shippingAmount,
            ];

        $body = $this->executeHook('mobbexQuoteCheckoutRequest', true, [
            'reference'    => $this->getReference($quote->getId()),
            'currency'     => 'ARS',
            'total'        => (float) $quote->getGrandTotal(),
            'description'  => 'Quote #' . $quote->getId(),
            'test'         => (bool) ($this->config->getTestMode()),
            'return_url'   => $this->getEndpointUrl('paymentreturn', ['quote_id' => $quote->getId()]),
            'webhook'      => $this->getEndpointUrl('webhook', ['quote_id' => $quote->getId()]),
            'items'        => isset($items) ? $items : [],
            'wallet'       => $this->config->getWalletActive() && $this->customerSession->isLoggedIn(),
            'multicard'    => (bool) ($this->config->getMulticard()),
            'multivendor'  => $this->config->getMultivendor() === 'disable' ? false : $this->config->getMultivendor(),
            'merchants'    => $this->getMerchants($items),
            'installments' => $this->getInstallments($quote->getItemsCollection(), true),
            'timeout'      => 5,
            'customer'     => [
                'email'          => $quote->getCustomerEmail(), 
                'name'           => "$shippingAddress[firstname] $shippingAddress[lastname]",
                'identification' => '',
                'uid'            => $quote->getCustomerId(),
                'phone'          => $shippingAddress['telephone'],
            ],
            'options'      => [
                'button'   => (bool) ($this->config->getEmbedPayment()),
                'domain'   => $this->getDomainUrl(),
                'theme'    => $this->getTheme(),
                'redirect' => [
                    'success' => true,
                    'failure' => false,
                ],
                'platform' => $this->getPlatform(),
            ],
        ], $quote);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.mobbex.com/p/checkout',
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($body),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($this->config->getDebugMode())
            Data::log('Quote Checkout Creation Body:' . print_r($body, true), 'mobbex_debug_' . date('m_Y') . '.log');

        if ($error)
            return Data::log("Checkout Error:" . print_r($error, true), "mobbex_error_" . date('m_Y') . ".log");

        $result = json_decode($response, true);
        $result['data']['return_url'] = $body['return_url']; 
        Data::log("Checkout Response:" . print_r($result, true), "mobbex_" . date('m_Y') . ".log");

        return $result['data'];
    }

    /**
     * Return domain url without "https://" or "http://".
     * 
     * @return string
     */
    private function getDomainUrl()
    {
        $url = $this->urlBuilder->getUrl();

        // Remove scheme from URL
        $url = str_replace(['http://', 'https://'], '', $url);

        // Remove empty path
        if (strpos(substr($url,-1),'/') !== 'false')
            $url = substr($url,0,-1);

        return $url;
    }

    public function getEndpointUrl($controller, $data = [])
    {
        return $this->urlBuilder->getUrl("webpay/payment/$controller", [
            '_secure'      => true,
            '_current'     => true,
            '_use_rewrite' => true,
            '_query'       => $data,
        ]);
    }

    /**
     * @return array
     */
    private function getTheme()
    {
        return [
            "type" => $this->config->getThemeType(),
            "background" => $this->config->getBackgroundColor(),
            "colors" => [
                "primary" => $this->config->getPrimaryColor(),
            ],
        ];
    }

    /**
     * @return array
     */
    private function getPlatform()
    {
        return [
            'name'      => 'magento_2',
            'version'   => Mobbex::VERSION,
            'ecommerce' => [
                'magento' => $this->productMetadata->getVersion(),
                'php'     => phpversion(),
            ],
        ];
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return [
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $this->config->getApiKey(),
            'x-access-token: ' . $this->config->getAccessToken(),
            'x-ecommerce-agent: Magento/' . $this->productMetadata->getVersion() . ' Plugin/' . $this::VERSION . ' PHP/' . phpversion(),
        ];
    }

    /**
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public function getInactivePlans($productId)
    {
        $product = $this->productRepository->getById($productId);

        $inactivePlans = unserialize($this->customFields->getCustomField($productId, 'product', 'common_plans')) ?: [];

        foreach ($product->getCategoryIds() as $categoryId)
            $inactivePlans = array_merge($inactivePlans, unserialize($this->customFields->getCustomField($categoryId, 'category', 'common_plans')) ?: []);

        // Remove duplicated and return
        return array_unique($inactivePlans);
    }

    /**
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public function getActivePlans($productId)
    {
        $product = $this->productRepository->getById($productId);

        // Get plans from product and product categories
        $activePlans = unserialize($this->customFields->getCustomField($productId, 'product', 'advanced_plans')) ?: [];

        foreach ($product->getCategoryIds() as $categoryId)
            $activePlans = array_merge($activePlans, unserialize($this->customFields->getCustomField($categoryId, 'category', 'advanced_plans')) ?: []);

        // Remove duplicated and return
        return array_unique($activePlans);
    }

    /**
     * Retrieve installments checked on plans filter of each item.
     * 
     * @param array $items
     * 
     * @return array
     */
    public function getInstallments($items)
    {
        $installments = $inactivePlans = $activePlans = [];

        // Get plans from order products
        foreach ($items as $item) {
            $id = is_string($item) ? $item : $item->getProductId();

            $inactivePlans = array_merge($inactivePlans, $this->getInactivePlans($id));
            $activePlans   = array_merge($activePlans, $this->getActivePlans($id));
        }

        // Add inactive (common) plans to installments
        foreach ($inactivePlans as $plan)
            $installments[] = '-' . $plan;

        // Add active (advanced) plans to installments only if the plan is active on all products
        foreach (array_count_values($activePlans) as $plan => $reps) {
            if ($reps == count($items))
                $installments[] = '+uid:' . $plan;
        }

        // Remove duplicated plans and return
        return array_values(array_unique($installments));
    }


    /**
     * @return string
     */
    public function getReference($orderId)
    {
        return 'mag2_order_'.$orderId;
	}
  
    /**
     * Get yhe entity of a product
     * @param object $product
     * @return string $entity
     */
    public function getEntity($product)
    {
        if($this->customFields->getCustomField($product->getId(), 'product', 'entity'))
            return $this->customFields->getCustomField($product->getId(), 'product', 'entity');

        $categories = $product->getCategoryIds();
        foreach ($categories as $category) {
            if($this->customFields->getCustomField($category, 'category', 'entity'))
                return $this->customFields->getCustomField($category, 'category', 'entity'); 
        }

        return '';
	}

    /**
     * Get the merchants from item list.
     * @param array
     * @return array
     */
    public function getMerchants($items)
    {
        $merchants = [];

        //Get the merchants from items list
        foreach ($items as $item) {
            if (!empty($item['entity']))
                $merchants[] = ['uid' => $item['entity']];
        }

        return $merchants;
	}

    /**
     * Check if plugin is configured.
     */
    public function isReady()
    {
        return (!empty($this->config->getApiKey()) && !empty($this->config->getAccessToken()));
    }

    /**
     * Get DNI configured by quote or current user if logged in.
     * 
     * @param object $object
     * @param int|string $quoteId
     * 
     * @return string $dni 
     */
    public function getDni($order, $quoteId)
    {

        if(!empty($this->config->getDniColumn()) && isset($order->getBillingAddress()->getData()[$this->config->getDniColumn()]))
            return $order->getBillingAddress()->getData()[$this->config->getDniColumn()];

        $customField = $this->_customFieldFactory->create();

        // Get dni custom field from quote or current user if logged in
        $customerId = $this->customerSession->getCustomer()->getId();
        $order     = $customerId ? 'customer' : 'quote';
        $rowId      = $customerId ? $customerId : $quoteId;

        return $customField->getCustomField($rowId, $order, 'dni') ?: '';
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
            Data::log('Mobbex Hook Error: ' . $e->getMessage(), 'mobbex_error.log');
        }
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
        $is_subscription  = $this->customFields->getCustomField($id, 'product', 'is_subscription') ?: false;
        $subscription_uid = $this->customFields->getCustomField($id, 'product', 'subscription_uid') ?: '';

        return ['enable' => $is_subscription, 'uid' => $subscription_uid];
    }
}
