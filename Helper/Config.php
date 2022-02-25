<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PATH_API_KEY = 'payment/webpay/api_key';
    const PATH_ACCESS_TOKEN = 'payment/webpay/access_token';

    const PATH_ENTITY_DATA = 'payment/webpay/entity';
    const PATH_FINANCIAL_ACTIVE = 'payment/webpay/financial_active';
    const PATH_FINANCE_WIDGET_ON_CART = 'payment/webpay/finance_widget_on_cart';

    const PATH_TEST_MODE = 'payment/webpay/test_mode';
    const PATH_DEBUG_MODE = 'payment/webpay/debug_mode';
    const PATH_EMBED_PAYMENT = 'payment/webpay/checkout/embed_payment';
    const PATH_MULTICARD = 'payment/webpay/checkout/multicard';
    const PATH_MULTIVENDOR = 'payment/webpay/checkout/multivendor';

    const PATH_THEME_TYPE             = 'payment/webpay/appearance/theme';
    const PATH_BACKGROUND_COLOR       = 'payment/webpay/appearance/background_color';
    const PATH_PRIMARY_COLOR          = 'payment/webpay/appearance/primary_color';
    const PATH_BANNER_CHECKOUT        = 'payment/webpay/appearance/checkout_banner';
    const PATH_WIDGET_STYLE           = 'payment/webpay/appearance/widget_style';
    const PATH_WIDGET_BUTTON_LOGO     = 'payment/webpay/appearance/button_logo';
    const PATH_WIDGET_BUTTON_TEXT     = 'payment/webpay/appearance/button_text';

    const PATH_CREATE_ORDER_EMAIL   = 'payment/webpay/checkout/email_settings/create_order_email';
    const PATH_UPDATE_ORDER_EMAIL   = 'payment/webpay/checkout/email_settings/update_order_email';
    const PATH_CREATE_INVOICE_EMAIL = 'payment/webpay/checkout/email_settings/create_invoice_email';

    const PATH_ORDER_STATUS_APPROVED = 'payment/webpay/checkout/order_status_settings/order_status_approved';
    const PATH_ORDER_STATUS_IN_PROCESS = 'payment/webpay/checkout/order_status_settings/order_status_in_process';
    const PATH_ORDER_STATUS_CANCELLED = 'payment/webpay/checkout/order_status_settings/order_status_cancelled';
    const PATH_ORDER_STATUS_REFUNDED = 'payment/webpay/checkout/order_status_settings/order_status_refunded';
    const PATH_DISABLE_INVOICES = 'payment/webpay/checkout/order_status_settings/disable_invoices';

    const PATH_WALLET_ACTIVE = 'payment/webpay/checkout/wallet_active';

    const PATH_OWN_DNI_FIELD = 'payment/webpay/checkout/own_dni_field';

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
    }

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

    public function getBannerCheckout($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_BANNER_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getCreateOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_CREATE_ORDER_EMAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getUpdateOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_UPDATE_ORDER_EMAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getCreateInvoiceEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_CREATE_INVOICE_EMAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getOrderStatusApproved($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ORDER_STATUS_APPROVED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getOrderStatusInProcess($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ORDER_STATUS_IN_PROCESS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getOrderStatusCancelled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ORDER_STATUS_CANCELLED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getOrderStatusRefunded($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ORDER_STATUS_REFUNDED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getThemeType($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_THEME_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getBackgroundColor($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_BACKGROUND_COLOR,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getPrimaryColor($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_PRIMARY_COLOR,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getEmbedPayment($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_EMBED_PAYMENT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getMulticard($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_MULTICARD,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getMultivendor($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_MULTIVENDOR,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getApiKey($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_API_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getAccessToken($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ACCESS_TOKEN,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getTestMode($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_TEST_MODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getDebugMode($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_DEBUG_MODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getEntityData($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_ENTITY_DATA,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getFinancialActive($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_FINANCIAL_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }


    public function getWalletActive($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_WALLET_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getOwnDniField($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_OWN_DNI_FIELD,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getDisableInvoices($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_DISABLE_INVOICES,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getFinanceWidgetOnCart($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_FINANCE_WIDGET_ON_CART,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getWidgetStyle($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_WIDGET_STYLE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getButtonLogo($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_WIDGET_BUTTON_LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }
    public function getButtonText($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_WIDGET_BUTTON_TEXT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}