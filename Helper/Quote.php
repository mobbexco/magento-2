<?php

namespace Mobbex\Webpay\Helper;

class Quote
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Model\EventManager */
    public $eventManager;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $cf;

    /** @var \Magento\Framework\UrlInterface */
    public $urlBuilder;

    /** @var \Magento\Checkout\Model\Session */
    public $checkoutSession;

    /** @var \Magento\Catalog\Helper\Image */
    protected $imageHelper;

    /** @var \Magento\Directory\Model\RegionFactory */
    public $regionFactory;

    /** @var \Magento\Framework\App\ResourceConnection */
    public $connection;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Model\EventManager $eventManager,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Framework\App\ResourceConnection $connection
    ) {
        $this->config          = $config;
        $this->logger          = $logger;
        $this->eventManager    = $eventManager;
        $this->cf              = $customFieldFactory;
        $this->urlBuilder      = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper     = $imageHelper;
        $this->regionFactory   = $regionFactory;
        $this->connection      = $connection;
    }

    /**
     * Create checkout from current quote.
     * 
     * @param bool $draft True if the checkout is a draft.
     * 
     * @return bool
     */
    public function getCheckout($draft = false)
    {
        $quote = $this->checkoutSession->getQuote();

        $items = $products = [];
        $itemCollection = $quote->getItemsCollection();

        foreach ($itemCollection as $item) {
            $products[]   = $item->getProduct();
            $subscription = $this->config->getCatalogSetting($item->getProductId(), 'subscription_uid');

            if ($subscription) {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => $subscription,
                    'total'     => (float) $item->getRowTotalInclTax() ?: $item->getPrice(),
                ];
            } else {
                $items[] = [
                    'image'       => $this->imageHelper->init($item->getProduct(), 'product_small_image')->getUrl(),
                    'description' => $item->getName(),
                    'quantity'    => $item->getQty(),
                    'total'       => (float) $item->getRowTotalInclTax() ?: $item->getPrice(),
                    'entity'      => $this->getEntity($item),
                ];
            }
        }

        $address = $quote->getShippingAddress();

        if (!empty($address->getShippingDescription())) {
            $items[] = [
                'description' => __('Shipping') . ': ' . $address->getShippingDescription(),
                'total' => $address->getShippingInclTax(),
            ];
        }

        // Generate reference
        $reference = $this->config->get('custom_reference')
            ? $this->getCustomReference($quote->getId())
            : \Mobbex\Modules\Checkout::generateReference('q' . $quote->getId());

        // Create checkout
        $mobbexCheckout = new \Mobbex\Modules\Checkout(
            $quote->getId(),
            (float) $quote->getGrandTotal(),
            $draft ? null : $this->getEndpointUrl('paymentreturn', ['quote_id' => $quote->getId()]),
            $draft ? null : $this->getEndpointUrl('webhook', ['quote_id' => $quote->getId(), 'mbbx_token' => $this->config->generateToken()]),
            $quote->getStoreCurrencyCode(),
            $items,
            \Mobbex\Repository::getInstallments($itemCollection, [], $this->config->getProductPlans(...$products)),
            $this->getCustomer($quote),
            $this->getAddresses($quote),
            $draft ? 'none' : 'all',
            $draft ? 'mobbexDraftCheckoutRequest' : 'mobbexCheckoutRequest',
            "Carrito #" . $quote->getId(),
            $reference . ($draft ? '_DRAFT_CHECKOUT' : '')
        );

        $this->logger->log('debug', "Helper Mobbex > getCheckout | Checkout Response: ", $mobbexCheckout->response);

        return $mobbexCheckout->response;
    }

    public function getEndpointUrl($controller, $data = [])
    {   
        if($this->config->get('debug_mode'))
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

    public function getCustomer($quote)
    {
        $address = $quote->getBillingAddress()->getData();
        $customerId = $quote->getCustomerId();

        $idColumn = $this->config->get('dni_column');

        // Try to get the identification from column configured
        if ($idColumn && isset($address[$idColumn])) {
            $identification = $address[$idColumn];
        } else {
            $identification = $customerId
                ? $this->cf->create()->getCustomField($customerId, 'customer', 'dni')
                : $this->cf->create()->getCustomField($quote->getId(), 'quote', 'dni');
        }

        return [
            'name'           => $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname(),
            'email'          => $quote->getCustomerEmail(),
            'uid'            => $customerId ?: null,
            'phone'          => isset($address['telephone']) ? $address['telephone'] : '',
            'identification' => $identification
        ];
    }

    /**
     * Get Addresses data for Mobebx Checkout.
     * 
     * @param class $data
     * 
     * @return array $addresses
     */
    public function getAddresses($quote)
    {
        $addresses = $addressesData = array();

        //Check if there are billing address
        if($quote->getBillingAddress())
            $addressesData[] = $quote->getBillingAddress()->getData();

        //Check if there are shipping address
        if($quote->getShippingAddress())
            $addressesData[] = $quote->getShippingAddress()->getData();

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
        $string = $this->config->get('custom_reference');
        $connection = $this->connection->getConnection();

        // Get columns
        preg_match_all('/\{([^}]+)\}/', $string, $matches);

        foreach ($matches[0] as $index => $placeholder) {
            // Get table and column name
            $column = $matches[1][$index];

            // Get query
            $query = $connection->select()
                ->from($connection->getTableName('quote'), [$column])
                ->where('entity_id = :entity_id');

            // get result
            $result = $connection->fetchOne($query, ['entity_id' => (int)$id]);

            // Update reference
            $string = str_replace($placeholder, $result ?: '', $string);
        }

        return $string;
    }
}
