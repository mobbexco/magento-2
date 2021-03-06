<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PATH_API_KEY = 'payment/webpay/api_key';
    const PATH_ACCESS_TOKEN = 'payment/webpay/access_token';

    const PATH_TAX_ID = 'payment/webpay/tax_id';
    const PATH_FINANCIAL_ACTIVE = 'payment/webpay/financial_active';

    const PATH_TEST_MODE = 'payment/webpay/test_mode';
    const PATH_DEBUG_MODE = 'payment/webpay/debug_mode';
    const PATH_EMBED_PAYMENT = 'payment/webpay/checkout/embed_payment';

    const PATH_THEME_TYPE = 'payment/webpay/checkout/theme';
    const PATH_BACKGROUND_COLOR = 'payment/webpay/checkout/background_color';
    const PATH_PRIMARY_COLOR = 'payment/webpay/checkout/primary_color';
    const PATH_BANNER_CHECKOUT = 'payment/webpay/checkout/checkout_banner';

    const PATH_CREATE_ORDER_EMAIL = 'payment/webpay/checkout/email_settings/create_order_email';
    const PATH_UPDATE_ORDER_EMAIL = 'payment/webpay/checkout/email_settings/update_order_email';
    const PATH_CREATE_INVOICE_EMAIL = 'payment/webpay/checkout/email_settings/create_invoice_email';

    const PATH_ORDER_STATUS_APPROVED = 'payment/webpay/checkout/order_status_settings/order_status_approved';
    const PATH_ORDER_STATUS_IN_PROCESS = 'payment/webpay/checkout/order_status_settings/order_status_in_process';
    const PATH_ORDER_STATUS_CANCELLED = 'payment/webpay/checkout/order_status_settings/order_status_cancelled';
    const PATH_ORDER_STATUS_REFUNDED = 'payment/webpay/checkout/order_status_settings/order_status_refunded';

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

    public function getCuit($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_TAX_ID,
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
}