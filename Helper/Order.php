<?php

namespace Mobbex\Webpay\Helper;

class Order
{
    /** @var \Mobbex\Webpay\Helper\Config */
    private $config;

    /** @var \Mobbex\Webpay\Model\EventManager */
    private $eventManager;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    private $cf;

    /** @var \Magento\Catalog\Helper\Image */
    private $imageHelper;
    
    /** @var \Magento\Customer\Model\Session */
    private $customerSession;

    /** @var \Magento\Directory\Model\RegionFactory */
    private $regionFactory;

    /** @var \Magento\Framework\UrlInterface */
    private $urlBuilder;

    /** @var \Magento\Framework\App\ResourceConnection */
    private $resourceConnection;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Model\EventManager $eventManager,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Framework\App\ResourceConnection $connection
    ) {
        $this->config             = $config;
        $this->eventManager       = $eventManager;
        $this->cf                 = $customFieldFactory;
        $this->urlBuilder         = $urlBuilder;
        $this->imageHelper        = $imageHelper;
        $this->customerSession    = $customerSession;
        $this->regionFactory      = $regionFactory;
        $this->resourceConnection = $connection;
    }

    /**
     * Build Mobebx Checkout data from order.
     * 
     * @param \Magento\Sales\Model\Order $order
     * 
     * @return array
     */
    public function buildCheckoutData($order)
    {
        $items = $products = [];
        $orderCustomer = $order->getCustomer();

        // Format line items
        foreach ($order->getAllVisibleItems() as $item) {
            $products[]   = $item->getProduct();
            $subscription = $this->config->getCatalogSetting($item->getProduct()->getId(), 'subscription_uid');

            if ($subscription) {
                $items[] = [
                    'type' => 'subscription',
                    'total' => round($item->getPrice(), 2),
                    'reference' => $subscription,
                ];
            } else {
                $items[] = [
                    'total' => round($item->getRowTotalInclTax() ?: $item->getProduct()->getFinalPrice(), 2),
                    'image' => $this->imageHelper->init($item->getProduct(), 'product_small_image')->getUrl(),
                    'entity' => $this->getEntity($item),
                    'quantity' => $item->getQtyOrdered(),
                    'description' => $item->getProduct()->getName(),
                ];
            }
        }

        // Add shipping as item
        if (!empty($order->getShippingDescription()))
            $items[] = [
                'description' => __('Shipping') . ': ' . $order->getShippingDescription(),
                'total' => $order->getShippingInclTax(),
            ];

        return [
            'id'           => (int) $order->getId(),
            'total'        => (float) $order->getGrandTotal(),
            'currency'     => (string) $order->getOrderCurrencyCode(),
            'incrementId'  => (string) $order->getIncrementId(),
            'reference'    =>  $this->config->get('custom_reference') ? $this->getCustomReference($order->getId()) : null,
            'description'  => 'Pedido #' . $order->getIncrementId(),
            'return_url'   => $this->getPaymentUrl('paymentreturn'),
            'webhook'      => $this->getPaymentUrl('webhook', ['order_id' => $order->getId(), 'mbbx_token' => $this->config->generateToken()]),
            'items'        => $items,
            'addresses'    => $this->getAddresses($order),
            'installments' => \Mobbex\Repository::getInstallments(
                $order->getAllVisibleItems(),
                [],
                $this->config->getProductPlans(...$products)
            ),
            'customer' => [
                'name'           => $order->getCustomerName(),
                'email'          => $order->getCustomerEmail(),
                'uid'            => $order->getCustomerId(),
                'createdAt'      => $orderCustomer ? \Mobbex\dateToTime($orderCustomer->getCreatedAt()) : null,
                'phone'          => $order->getBillingAddress() ? $order->getBillingAddress()->getTelephone() : '',
                'identification' => $this->getDni($order),
            ],
        ];
    }

    /**
     * Get the entity from item
     * 
     * @param object $item
     * 
     * @return string|null
     */
    public function getEntity($item)
    {
        // Only if multivendor is active
        if (!in_array($this->config->get('multivendor'), ['active', 'unified']))
            return;

        $product = $item->getProduct();
        $entity = $this->config->getCatalogSetting($product->getId(), 'entity');

        // Try to get entity from product
        if ($entity && is_string($entity))
            return $entity;

        // Try to get entity from vnecoms vendor
        $entity = $this->eventManager->dispatch('mobbexGetVendorEntity', false, $item);

        if ($entity && is_string($entity))
            return $entity;

        $categories = $product->getCategoryIds();

        // Try to get entity from their categories
        foreach ($categories as $category) {
            $entity = $this->config->getCatalogSetting($category, 'entity', 'category');

            if ($entity && is_string($entity))
                return $entity;
        }
	}

    /**
     * Get DNI configured by quote or current user if logged in.
     * 
     * @param object $order
     * 
     * @return string 
     */
    public function getDni($order)
    {
        $address = $order->getBillingAddress()->getData();
        $dniColumn = $this->config->get('dni_column');

        // Try to get the identification from column configured
        if ($dniColumn && isset($address[$dniColumn]))
            return isset($address[$dniColumn]) ? (string) $address[$dniColumn] : '';

        $customField = $this->cf->create();
        $customerId = $this->customerSession->getCustomer()->getId();

        // Get dni custom field from quote or current user depending if logged in
        return (string) $customField->getCustomField(
            $customerId ? $customerId : $order->getQuoteId(),
            $customerId ? 'customer' : 'quote',
            'dni'
        ) ?: '';
    }

    /**
     * Get Addresses data for Mobebx Checkout.
     * 
     * @param \Magento\Sales\Model\Order $order
     * 
     * @return array
     */
    public function getAddresses($order)
    {
        $addresses = $addressesData = array();

        //Check if there are billing address
        if($order->getBillingAddress())
            $addressesData[] = $order->getBillingAddress()->getData();
        
        //Check if there are shipping address
        if($order->getShippingAddress())
            $addressesData[] = $order->getShippingAddress()->getData();

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
     * 
     * @param int $id Order id.
     * 
     * @return string
     */
    public function getCustomReference($id)
    {
        $format = (string) $this->config->get('custom_reference');
        $connection = $this->resourceConnection->getConnection();

        // Get columns
        preg_match_all('/\{([^}]+)\}/', $format, $matches);

        // Bad format
        if (empty($matches[0]))
            return null; // Maybe log error?

        foreach ($matches[0] as $index => $placeholder) {
            // Bad format
            if (!isset($matches[1][$index]))
                continue;

            // Get table and column name
            $column = $matches[1][$index];

            // Get query
            $query = $connection->select()
                ->from($connection->getTableName('sales_order'), [$column])
                ->where('entity_id = :entity_id');

            // Update reference
            $result = $connection->fetchOne($query, ['entity_id' => (int)$id]);
            $format = str_replace($placeholder, $result ?: '', $format);
        }

        return $format;
    }

    /**
     * Get endpoint url for payment requests.
     * 
     * @param string $controller
     * @param array $data
     * 
     * @return string
     */
    public function getPaymentUrl($controller, $data = [])
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
}
