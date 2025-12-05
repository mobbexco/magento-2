<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** Module configuration paths */
    public $settingPaths = [
        'api_key'                            => 'payment/sugapay/api_key',
        'access_token'                       => 'payment/sugapay/access_token',
        'test'                               => 'payment/sugapay/test_mode',
        'debug_mode'                         => 'payment/sugapay/debug_mode',
        'financial_active'                   => 'payment/sugapay/financial_active',
        'finance_widget_on_cart'             => 'payment/sugapay/finance_widget_on_cart',
        'theme'                              => 'payment/sugapay/appearance/theme',
        'background'                         => 'payment/sugapay/appearance/background_color',
        'color'                              => 'payment/sugapay/appearance/primary_color',
        'checkout_title'                     => 'payment/sugapay/appearance/checkout_title',
        'checkout_banner'                    => 'payment/sugapay/appearance/checkout_banner',
        'widget_style'                       => 'payment/sugapay/appearance/widget_style',
        'button_logo'                        => 'payment/sugapay/appearance/button_logo',
        'button_text'                        => 'payment/sugapay/appearance/button_text',
        'method_icon'                        => 'payment/sugapay/appearance/method_icon',
        'show_method_icons'                  => 'payment/sugapay/appearance/show_method_icons',
        'sources_priority'                   => 'payment/sugapay/appearance/sources_priority',
        'show_featured_plans_on_cart'        => 'payment/sugapay/appearance/show_featured_plans_on_cart',
        'offsite'                            => 'payment/sugapay/active',
        'transparent'                        => 'payment/sugapay_transparent/active',
        'pos'                                => 'payment/sugapay_pos/active',
        'embed'                              => 'payment/sugapay/checkout/embed_payment',
        'wallet'                             => 'payment/sugapay/checkout/wallet_active',
        'multicard'                          => 'payment/sugapay/checkout/multicard',
        'multivendor'                        => 'payment/sugapay/checkout/multivendor',
        'payment_mode'                       => 'payment/sugapay/checkout/payment_mode',
        'timeout'                            => 'payment/sugapay/checkout/timeout',
        'return_timeout'                     => 'payment/sugapay/checkout/return_timeout',
        'custom_reference'                   => 'payment/sugapay/checkout/custom_reference',
        'show_no_interest_labels'            => 'payment/sugapay/checkout/show_no_interest_labels',
        'site_id'                            => 'payment/sugapay/advanced/site_id',
        'payment_methods'                    => 'payment/sugapay/advanced/payment_methods',
        'online_refund'                      => 'payment/sugapay/advanced/online_refund',
        'advanced_plans_exclusivity'         => 'payment/sugapay/advanced/advanced_plans_exclusivity',
        'final_currency'                     => 'payment/sugapay/advanced/final_currency',
        'creditmemo_on_refund'               => 'payment/sugapay/advanced/creditmemo_on_refund',
        'restore_cart'                       => 'payment/sugapay/advanced/restore_cart',
        'own_dni_field'                      => 'payment/sugapay/checkout/own_dni_field',
        'dni_column'                         => 'payment/sugapay/checkout/dni_column',
        'create_order_email'                 => 'payment/sugapay/checkout/email_settings/create_order_email',
        'email_before_payment'               => 'payment/sugapay/checkout/email_settings/email_before_payment',
        'update_order_email'                 => 'payment/sugapay/checkout/email_settings/update_order_email',
        'create_invoice_email'               => 'payment/sugapay/checkout/email_settings/create_invoice_email',
        'order_status_approved'              => 'payment/sugapay/checkout/order_status_settings/order_status_approved',
        'order_status_in_process'            => 'payment/sugapay/checkout/order_status_settings/order_status_in_process',
        'order_status_authorized'            => 'payment/sugapay/checkout/order_status_settings/order_status_authorized',
        'order_status_refunded'              => 'payment/sugapay/checkout/order_status_settings/order_status_refunded',
        'order_status_revision'              => 'payment/sugapay/checkout/order_status_settings/order_status_revision',
        'order_status_rejected'              => 'payment/sugapay/checkout/order_status_settings/order_status_rejected',
        'order_status_cancelled'             => 'payment/sugapay/checkout/order_status_settings/order_status_cancelled',
        'disable_invoices'                   => 'payment/sugapay/checkout/order_status_settings/disable_invoices',
        'memo_stock'                         => 'payment/sugapay/checkout/order_status_settings/memo_stock',
        'emit_notifications'                 => 'payment/sugapay/notifications/emit_notifications',
        'emit_customer_success_notification' => 'payment/sugapay/notifications/emit_customer_success_notification',
        'emit_customer_failure_notification' => 'payment/sugapay/notifications/emit_customer_failure_notification',
        'emit_customer_waiting_notification' => 'payment/sugapay/notifications/emit_customer_waiting_notification',
    ];

    /** Mobbex Catalog Settings */
    public $catalogSettings = [
        'advanced_plans',
        'entity',
        'subscription_uid',
        'show_featured',
        'featured_plans',
        'manual_config'
    ];
    
    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    /** @var \Magento\Framework\App\Config\Storage\WriterInterface */
    public $configWriter;

    /** @var \Magento\Framework\Serialize\Serializer\Serialize */
    public $serializer;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\Serialize\Serializer\Serialize $serializer
    ) {
        parent::__construct($context);
        
        $this->configWriter = $configWriter;
        $this->customField  = $customFieldFactory->create();
        $this->serializer   = $serializer;
    }

    /** MODULE SETTINGS */

    /**
     * Save a config value to db.
     * 
     * @param string $path Config identifier. @see $this::$configurationPaths
     * @param mixed $value Value to set.
     */
    public function save($name, $value)
    {
        if (empty($this->settingPaths[$name]))
            throw new \Exception("The configuration path $name does not exist.");

        $this->configWriter->save($this->settingPaths[$name], $value);
    }

    /**
     * Get a config value from db.
     * 
     * @param string $path Config identifier. @see $this::$configurationPaths
     * @param string $store Store code.
     * 
     * @return mixed
     */
    public function get($name, $store = null)
    {
        return empty($this->settingPaths[$name]) ? null : $this->scopeConfig->getValue(
            $this->settingPaths[$name],
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get all module configuration values from db.
     * 
     * @return array
     */
    public function getAll()
    {
        $settings = [];
        foreach ($this->settingPaths as $name => $value)
            $settings[$name] = $this->get($name);

        return $settings;
    }

    /** CATALOG SETTINGS */

    /**
     * Retrieve the given product/category option.
     * 
     * @param int|string $id
     * @param string $field
     * @param string $catalogType
     * 
     * @return array|string
     * 
     */
    public function getCatalogSetting($id, $field, $catalogType = 'product')
    {
        $value = $this->customField->getCustomField($id, $catalogType, $field);

        if (strpos($field, '_plans') !== false)
            return $value ? json_decode($value, true) : [];

        return $value;
    }
    /**
     * Get all active plans from given products and their categories.
     * 
     * @param object ...$products
     * 
     * @return array Only active plans with externalMatch rule.
     */
    public function getProductPlans(...$products)
    {
        $advanced_plans = [];

        foreach ($products as $product) {
            // Merge product plans
            $advanced_plans = array_merge($advanced_plans,
                $this->getCatalogSetting($product->getId(), 'advanced_plans') ?: []
            );

            // Merge categories plans
            foreach ($product->getCategoryIds() as $categoryId)
                $advanced_plans = array_merge($advanced_plans,
                    $this->getCatalogSetting($categoryId, 'advanced_plans', 'category') ?: []
                );
        }

       return array_unique($advanced_plans);
    }

    /**
     * Validate a token generated from credentials configured.
     * 
     * @param mixed $token
     * 
     * @return bool True if token is valid.
     * 
     */
    public function validateToken($token)
    {
        return password_verify(
            "{$this->get('api_key')}|{$this->get('access_token')}",
            $token
        );
    }

    /**
     * Generate a token using current credentials configured.
     * 
     * @return string 
     * 
     */
    public function generateToken()
    {
        return password_hash(
            "{$this->get('api_key')}|{$this->get('access_token')}",
            PASSWORD_DEFAULT
        );
    }

    /**
     * handleFeaturedPlans handles the product particular featured plans configuration
     * "[]" value is used to show automatic featured plans
     * 
     * @param object $product
     * 
     * @return null|string setting value
     */
    public function handleFeaturedPlans($product = null)
    {
        if (!$product)
            return null;

        $showFeatured = $this->getAllPlansConfiguratorSettings($product, "show_featured");
        if (!$showFeatured)
            return null;

        $manualConfig = $this->getAllPlansConfiguratorSettings($product, "manual_config");
        if (!$manualConfig)
            return "[]";

        return $this->getAllPlansConfiguratorSettings($product, "featured_plans");
    }

    /**
     * Get specific field values from product categories
     * 
     * @param object     $product
     * @param string     $fieldName
     * 
     * @return string|bool
     */
    private function getAllPlansConfiguratorSettings($product, $fieldName) 
    {
        $id = $product->getId();
        // gets product settings
        $productFieldValue = $this->getCatalogSetting($id, $fieldName, 'product') ?: [];

        // gets categories settings
        // merge in array value case
        if (is_array($productFieldValue)) {
            $productFieldValue = $this->displayFeaturedPlans($id) 
                ? $productFieldValue 
                : [];

            foreach ($product->getCategoryIds() as $categoryId) {
                $categoryFieldValue = $this->displayFeaturedPlans($categoryId, 'category') 
                    ? $this->getCatalogSetting($categoryId, $fieldName, 'category')
                    : [];

                $productFieldValue  = array_merge(
                    $productFieldValue, 
                    $categoryFieldValue ?: []
                );
            }

            return json_encode($productFieldValue);
        }

        // check flags in string value case
        $categoriesFieldValues = [];
        foreach ($product->getCategoryIds() as $categoryId)
            array_push(
                $categoriesFieldValues,
                $this->getCatalogSetting($categoryId, $fieldName, 'category')
            );

        return empty($categoriesFieldValues) 
            ? $productFieldValue == "yes"
            : (in_array("yes", $categoriesFieldValues) || $productFieldValue == "yes");
    }


    /**
     * Get product display featured plans settings
     * 
     * @param string|int $id
     * 
     * @return bool
     */
    private function displayFeaturedPlans($id, $catalogType="product") 
    {
        return (
            $this->getCatalogSetting($id, "show_featured", $catalogType) == "yes"
            && $this->getCatalogSetting($id, "manual_config", $catalogType) == "yes"
        );
    }
}