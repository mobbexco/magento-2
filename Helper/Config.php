<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** Module configuration paths */
    public $settingPaths = [
        'api_key'                => 'payment/webpay/api_key',
        'access_token'           => 'payment/webpay/access_token',
        'test'                   => 'payment/webpay/test_mode',
        'debug_mode'             => 'payment/webpay/debug_mode',
        'financial_active'       => 'payment/webpay/financial_active',
        'finance_widget_on_cart' => 'payment/webpay/finance_widget_on_cart',
        'theme'                  => 'payment/webpay/appearance/theme_type',
        'background'             => 'payment/webpay/appearance/background_color',
        'color'                  => 'payment/webpay/appearance/primary_color',
        'checkout_title'         => 'payment/webpay/appearance/checkout_title',
        'checkout_banner'        => 'payment/webpay/appearance/checkout_banner',
        'widget_style'           => 'payment/webpay/appearance/widget_style',
        'button_logo'            => 'payment/webpay/appearance/button_logo',
        'button_text'            => 'payment/webpay/appearance/button_text',
        'embed'                  => 'payment/webpay/checkout/embed_payment',
        'wallet'                 => 'payment/webpay/checkout/wallet_active',
        'multicard'              => 'payment/webpay/checkout/multicard',
        'multivendor'            => 'payment/webpay/checkout/multivendor',
        'payment_mode'           => 'payment/webpay/checkout/payment_mode',
        'site_id'                => 'payment/webpay/advanced/site_id',
        'own_dni_field'          => 'payment/webpay/checkout/own_dni_field',
        'dni_column'             => 'payment/webpay/checkout/dni_column',
        'create_order_email'     => 'payment/webpay/checkout/email_settings/create_order_email',
        'update_order_email'     => 'payment/webpay/checkout/email_settings/update_order_email',
        'create_invoice_email'   => 'payment/webpay/checkout/email_settings/create_invoice_email',
        'order_status_approved'  => 'payment/webpay/checkout/order_status_settings/order_status_approved',
        'order_status_in_process'=> 'payment/webpay/checkout/order_status_settings/order_status_in_process',
        'order_status_authorized'=> 'payment/webpay/checkout/order_status_settings/order_status_authorized',
        'order_status_refunded'  => 'payment/webpay/checkout/order_status_settings/order_status_refunded',
        'order_status_revision'  => 'payment/webpay/checkout/order_status_settings/order_status_revision',
        'order_status_rejected'  => 'payment/webpay/checkout/order_status_settings/order_status_rejected',
        'order_status_cancelled' => 'payment/webpay/checkout/order_status_settings/order_status_cancelled',
        'disable_invoices'       => 'payment/webpay/checkout/order_status_settings/disable_invoices',
    ];

    /** Mobbex Catalog Settings */
    public $catalogSettings = [ 'common_plans', 'advanced_plans', 'entity', 'is_subscription', 'subscription_uid'];

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->customField = $customFieldFactory->create();
    }

    /** MODULE SETTINGS */

    /**
     * Save a config value to db.
     * 
     * @param string $path Config identifier.
     * @param mixed $value Value to set.
     */
    public function save($path, $value)
    {
        $this->configWriter->save($path, $value);
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
     * @param string $object
     * @param string $catalogType
     * 
     * @return array|string
     */
    public function getCatalogSetting($id, $object, $catalogType = 'product')
    {
        if (strpos($object, '_plans'))
            return unserialize($this->customField->getCustomField($id, $catalogType, $object)) ?: [];

        return $this->customField->getCustomField($id, $catalogType, $object) ?: '';
    }

    /**
     * Get active plans for a given products.
     * @param array $products
     * @return array $array
     */
    public function getProductPlans($products)
    {
        $common_plans = $advanced_plans = [];

        foreach ($products as $product) {
            foreach (['common_plans', 'advanced_plans'] as $value) {
                //Get product active plans
                ${$value} = array_merge($this->getCatalogSetting($product->getId(), $value), ${$value});
                //Get product category active plans
                foreach ($product->getCategoryIds() as $categoryId)
                    ${$value} = array_unique(array_merge(${$value}, $this->getCatalogSetting($categoryId, $value, 'category')));
            }
        }

        return compact('common_plans', 'advanced_plans');
    }
}