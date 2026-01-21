<?php

namespace Mobbex\Webpay\Helper;

/**
 * Class Mobbex
 * @package Mobbex\Webpay\Helper
 */
class Mobbex extends \Magento\Framework\App\Helper\AbstractHelper
{
    const VERSION = '5.0.0';

    /** @var Image */
    protected $imageHelper;
    
    /** @var Session */
    protected $customerSession;

    /** @var \Magento\Directory\Model\RegionFactory */
    public $regionFactory;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $cf;

    /** @var \Magento\Framework\UrlInterface */
    public $urlBuilder;

    /** @var \Magento\Checkout\Model\Session */
    public $checkoutSession;

    /** @var \Magento\Framework\App\ResourceConnection */
    public $resourceConnection;

    /** @var \Mobbex\Webpay\Model\EventManager */
    public $eventManager;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Framework\App\ResourceConnection $connection,
        \Mobbex\Webpay\Model\EventManager $eventManager
    ) {
        $this->config             = $config;
        $this->logger             = $logger;
        $this->cf                 = $customFieldFactory;
        $this->checkoutSession    = $checkoutSession;
        $this->urlBuilder         = $urlBuilder;
        $this->imageHelper        = $imageHelper;
        $this->customerSession    = $customerSession;
        $this->regionFactory      = $regionFactory;
        $this->resourceConnection = $connection;
        $this->eventManager       = $eventManager;
    }

    /**
     * Create a Mobebx Checkout from current order.
     * 
     * @return array $checkout
     */
    public function getCheckout()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderCustomer = $order->getCustomer();

        // Get customer data
        if ($order->getBillingAddress()){
            if (!empty($order->getBillingAddress()->getTelephone())) {
                $phone = $order->getBillingAddress()->getTelephone();
            }
        }

        //Get Items
        $items = $products = [];
        $orderedItems = $order->getAllVisibleItems();
        
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
        
        if (!empty($order->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $order->getShippingDescription(),
                'total' => $order->getShippingInclTax(),
            ];
        }

        $mobbexCheckout = new \Mobbex\Modules\Checkout(
            $order->getId(),
            (float) $order->getGrandTotal(),
            $this->getEndpointUrl('paymentreturn'),
            $this->getEndpointUrl('webhook', ['order_id' => $order->getId(), 'mbbx_token' => $this->config->generateToken()]),
            $order->getOrderCurrencyCode(),
            $items,
            \Mobbex\Repository::getInstallments($orderedItems, [], $this->config->getProductPlans(...$products)),
            [
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'uid' => $order->getCustomerId(),
                'createdAt' => $orderCustomer ? \Mobbex\dateToTime($orderCustomer->getCreatedAt()) : null,
                'phone' => isset($phone) ? $phone : '',
                'identification' => $this->getDni($order),
            ],
            $this->getAddresses($order),
            'all',
            'mobbexCheckoutRequest',
            'Pedido #' . $order->getIncrementId(),
            $this->config->get('custom_reference') ? $this->getCustomReference($order->getId()) : null
        );

        $this->logger->log('debug', "Helper Mobbex > getCheckout | Checkout Response: ", $mobbexCheckout->response);

        // Save checkout uid to use later in payment failed page
        if (isset($mobbexCheckout->response['id']))
            $this->cf->create()->saveCustomField(
                $order->getId(),
                'order',
                'checkout_uid',
                $mobbexCheckout->response['id']
            );

        return $mobbexCheckout->response;
    }

    public function getEndpointUrl($controller, $data = [])
    {
        if ($this->config->get('debug_mode'))
            $data['XDEBUG_SESSION_START'] = 'PHPSTORM';
            
        return $this->urlBuilder->getUrl("sugapay/payment/$controller", [
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
        $entity = $this->eventManager->dispatch('mobbexGetVendorEntity', false, $item);

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
        if (!$order->getBillingAddress())
            return '';

        $address   = $order->getBillingAddress()->getData();
        $dniColumn = $this->config->get('dni_column');

        if ($dniColumn && isset($address[$dniColumn]))
            return $address[$dniColumn];

        $customField = $this->cf->create();

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
     * filterFeaturedPlans get the featured plans configured for a product
     * 
     * @param array $sources
     * @param array $featured_plans
     * 
     * @return string best plan
     */
    public function filterFeaturedPlans($sources, $featured_plans)
    {
        $featuredPlans = [];

        foreach ($sources as $source) {
            if (empty($source['installments']) || empty($source['installments']['enabled'])) {
                continue;
            }

            $installment_list = isset($source['installments']['list']) && is_array($source['installments']['list'])
                ? $source['installments']['list']
                : [];

            foreach ($installment_list as $i) {
                if (empty($i['uid'])) {
                    continue;
                }

                if (in_array($i['uid'], $featured_plans, true) || in_array($i['reference'], $featured_plans, true)) {
                    $featuredPlans[] = [
                        'count'      => isset($i['totals']['installment']['count']) ? $i['totals']['installment']['count'] : null,
                        'amount'     => isset($i['totals']['installment']['amount']) ? $i['totals']['installment']['amount'] : null,
                        'source'     => isset($source['source']['name']) ? $source['source']['name'] : 'Unknown',
                        'percentage' => isset($i['totals']['financial']['percentage']) ? $i['totals']['financial']['percentage'] : 0,
                    ];
                }
            }
        }

        if (empty($featuredPlans))
            return null;

        return $this->setBestPlan($featuredPlans);
    }


    /**
     * setBestPlan evaluates between featured installments to get the best one
     * 
     * @param array $featuredPlans
     * 
     * @return string $bestPlan
     */
    private function setBestPlan($featuredPlans = [])
    {
        $bestPlan = null;

        foreach ($featuredPlans as $plan) {
            if ($bestPlan === null) {
                $bestPlan = $plan;
                continue;
            }

            $currentDiscount = isset($plan['percentage']) ? $plan['percentage'] : 0;
            $bestDiscount    = isset($bestPlan['percentage']) ? $bestPlan['percentage'] : 0;

            if ($currentDiscount < $bestDiscount)
                $bestPlan = $plan;
            elseif ($currentDiscount == $bestDiscount && $plan['count'] > $bestPlan['count'])
                $bestPlan = $plan;
        }

        return json_encode($bestPlan);
    }
}
