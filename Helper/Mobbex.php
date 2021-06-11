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

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Helper
 */
class Mobbex extends AbstractHelper
{
    const VERSION = '2.0.0';

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
        ProductMetadataInterface $productMetadata
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
        $this->productMetadata = $productMetadata;
    }

    /**
     * @return bool
     */
    public function createCheckout()
    {
        $curl = curl_init();

        // get checkout object
        $checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();

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

        // get customer data
        $customer = [
            'email' => $orderData->getCustomerEmail(), 
            'name' => $orderData->getCustomerName(),
            //Customer id added for wallet usage
            'uid' => $orderData->getCustomerId(),
            
        ];
        if ($orderData->getBillingAddress()){
            if (!empty($orderData->getBillingAddress()->getTelephone())) {
                $customer['phone'] = $orderData->getBillingAddress()->getTelephone();
            }
        }

        $items = [];
        $orderedItems = $this->order->getAllVisibleItems();

        foreach ($orderedItems as $item) {
            $product = $item->getProduct();
            $price = $item->getRowTotalInclTax() ? : $product->getFinalPrice();

            $items[] = [
                "image" => $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                "description" => $product->getName(),
                "quantity" => $item->getQtyOrdered(),
                "total" => round($price, 2),
            ];
        }

        if (!empty($this->order->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $this->order->getShippingDescription(),
                'total' => $this->order->getShippingInclTax(),
            ];
        }

        $returnUrl = $this->urlBuilder->getUrl('webpay/payment/paymentreturn', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "order_id" => $orderId
            ],
        ]);

        $webhook = $this->urlBuilder->getUrl('webpay/payment/webhook', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "order_id" => $orderId
            ],
        ]);

        // Create data
        $data = [
            'reference' => $this->getReference($orderId),
            'currency' => 'ARS',
            'description' => $description,
            // Test Mode
            'test' => (bool) ($this->config->getTestMode()),
            'return_url' => $returnUrl,
            'items' => $items,
            'webhook' => $webhook,
            "options" => [
                "button" => (bool) ($this->config->getEmbedPayment()),
                "domain" => $this->urlBuilder->getUrl('/'),
                "theme" => $this->getTheme(),
                "redirect" => [
                    "success" => true,
                    "failure" => false,
                ],
                "platform" => $this->getPlatform(),
            ],
            'total' => (float) $orderAmount,
            'customer' => $customer,
            'installments' => $this->getInstallments(),
            'timeout' => 5,
        ];
        

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
            $res['data']['return_url'] = $returnUrl; 
            return $res['data'];
        }
    }

    /**
     * Create checkout when wallet is active,
     *  using a quote instead of an order.
     *  can't use an order object beacouse there is a duplication problem
     * @return bool
     */
    public function createCheckoutFromQuote($quoteData)
    {
        $curl = curl_init();

        // set quote description as #QUOTEID
        $description = __('Quote #').$quoteData['entity_id'] ;//Quoteid / entityId

        // get order amount
        $orderAmount = round($quoteData['price'], 2);

        // get user session data and check wallet status
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $userSession = $objectManager->get('Magento\Customer\Model\Session');
        $is_wallet_active = ((bool) ($this->config->getWalletActive()) ) && $userSession->isLoggedIn();

        // get customer data
        $customer = [
            'email' => $quoteData['email'], 
            'name' => $quoteData['shipping_address']['firstname'],
            //Customer id added for wallet usage
            'uid' => $quoteData['customer_id'],
        ];
        if ($quoteData['shipping_address']){
            if ($quoteData['shipping_address']['telephone']) {
                $customer['phone'] = $quoteData['shipping_address']['telephone'];
            }
        }

        //get quote to retrieve shipping amount
        $quote = $this->quoteFactory->create()->load($quoteData['entity_id']);
        $quote_grand_total = $quote->getGrandTotal();
        
        $items = [];

        foreach ($quoteData['items'] as $item) {
            $items[] = [
                "description" => $item['name'],
                "quantity" => $item['qty'],
                "total" => round($item['price'], 2),
            ];
        }

        
        if ($quoteData['shipping_total'] > 0) {
            $items[] = [
                'description' => 'Shipping Amount',
                'total' => $quoteData['shipping_total'],
            ];
        }elseif($quote_grand_total > $orderAmount){
            $shipping_amount = $quote_grand_total - $orderAmount;
            $items[] = [
                'description' => 'Shipping Amount',
                'total' => ($shipping_amount),
            ];
            $orderAmount = $orderAmount + $shipping_amount;
        }

        $returnUrl = $this->urlBuilder->getUrl('webpay/payment/paymentreturn', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "quote_id" => $quoteData['entity_id']
            ],
        ]);
        $webhook = $this->urlBuilder->getUrl('webpay/payment/webhook', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "quote_id" => $quoteData['entity_id']
            ],
        ]);

        //get domain format 
        $domain = (string) $this->getDomainUrl();

        // Create data
        $data = [
            'reference' => $this->getReference($quoteData['entity_id']),
            'currency' => 'ARS',
            'description' => $description,
            // Test Mode
            'test' => (bool) ($this->config->getTestMode()),
            'return_url' => $returnUrl,
            'items' => $items,
            'webhook' => $webhook,
            "options" => [
                "button" => (bool) ($this->config->getEmbedPayment()),
                "domain" => $domain,
                "theme" => $this->getTheme(),
                "redirect" => [
                    "success" => true,
                    "failure" => false,
                ],
                "platform" => $this->getPlatform(),
            ],
            'total' => (float) $orderAmount,
            'customer' => $customer,
            'installments' => $this->getInstallments($quoteData['items']),
            'timeout' => 5,
            'wallet' => ($is_wallet_active),
        ];
        

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
            $res['data']['return_url'] = $returnUrl; 
            return $res['data'];
        }

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
        if (str_contains(substr($url,-1),'/'))
            $url = substr($url,0,-1);

        return $url;
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
            "name" => "magento_2",
            "version" => Mobbex::VERSION,
            "platform_version" => $this->productMetadata->getVersion() // TODO: test this
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
        ];
    }

    /**
     * Get Financing plans
     * The common plans stored in the data base are those that will not be show on the checkout
     * The advanced plans stored in the data base are those that will be show on the checkout
     * @return array
     */
    public function getInstallments($items_custom = null)
    {
        $installments = [];
        $total_advanced_plans = [];

        $ahora = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        $categories_ids = [];
        $addedPlans = [];
        //if $items_custom is not null then is called from quote checkout 
        if($items_custom)
        {
            $items = $items_custom;
        }else{
            $items = $this->order->getAllVisibleItems();
        }
        

        foreach ($items as $item) 
        {
            if($items_custom)
            {
                $productId = $item['product_id'];
            }else{
                $productId = $item->getProduct()->getId();
            }

            // Product 'Ahora' Plans
            foreach ($ahora as $key => $value) {
                if ($item->getProduct()->getResource()->getAttributeRawValue($productId, $key, $this->_storeManager->getStore()->getId()) === '1') {
                    $installments[] = '-' . $key;
                    unset($ahora[$key]);
                }
            }
            $customField = $this->_customFieldFactory->create();

            // Product Common Plans
            $checkedCommonPlans = unserialize($customField->getCustomField($productId, 'product', 'common_plans'));
            if (is_array($checkedCommonPlans)) {    
                // Check not selected plans only 
                $checkedCommonPlans = array_diff($checkedCommonPlans, $addedPlans);

                foreach ($checkedCommonPlans as $key => $plan) {
                    $addedPlans[] = $plan;
                    $installments[] = '-' . $plan;
                    unset($checkedCommonPlans[$key]);
                }
            }

            // Product Advanced Plans
            $checkedAdvancedPlans = unserialize($customField->getCustomField($productId, 'product', 'advanced_plans'));
            if (is_array($checkedAdvancedPlans)) {
                // Check not selected plans only 
                $checkedAdvancedPlans = array_diff($checkedAdvancedPlans, $addedPlans);
                $total_advanced_plans = array_merge($total_advanced_plans,$checkedAdvancedPlans);
            }

            // Categories Plans
            // Get categories from product
            $categories_ids = $item->getProduct()->getCategoryIds();
            foreach($categories_ids as $cat_id) {
                $checkedCommonPlansCat = unserialize($customField->getCustomField($cat_id, 'category', 'common_plans'));
                $checkedAdvancedPlansCat = unserialize($customField->getCustomField($cat_id, 'category', 'advanced_plans'));

                // Common Plans
                if (is_array($checkedCommonPlansCat)) {
                    // Check not selected plans only 
                    $checkedCommonPlansCat = array_diff($checkedCommonPlansCat, $addedPlans);
                    foreach ($checkedCommonPlansCat as $key => $plan) {
                        $addedPlans[] = $plan;
                        $installments[] = '-' . $plan;
                        unset($checkedCommonPlansCat[$key]);
                    }
                }

                // Advanced Plans
                if (is_array($checkedAdvancedPlansCat)) {
                    // Check not selected plans only 
                    $checkedAdvancedPlansCat = array_diff($checkedAdvancedPlansCat, $addedPlans);
                    $total_advanced_plans = array_merge($total_advanced_plans,$checkedAdvancedPlansCat);
                }
            }
        }
        // Get all the advanced plans with their number of reps
        $counted_advanced_plans = array_count_values($total_advanced_plans);

        // Advanced plans
        foreach ($counted_advanced_plans as $plan => $reps) {
            // Only if the plan is active on all products
            if ($reps == count($items)) {
                // Add to installments
                $installments[] = '+uid:' . $plan;
            }
        }
        

        return $installments;
    }

    /**
     * @return string
     */
    public function getReference($orderId)
    {
        return 'mag2_order_'.$orderId.'_time_'.time();
	}

    /**
     * Check if plugin is configured.
     */
    public function isReady()
    {
        return (!empty($this->config->getApiKey()) && !empty($this->config->getAccessToken()));
    }
}
