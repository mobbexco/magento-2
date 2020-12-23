<?php

namespace Mobbex\Webpay\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PATH_BANNER_CHECKOUT = 'payment/webpay/checkout_banner';


    public function getBannerCheckout($store = null)
    {
        return $this->scopeConfig->getValue(
            self::PATH_BANNER_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

}
