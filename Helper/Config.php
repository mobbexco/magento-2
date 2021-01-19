<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
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
}