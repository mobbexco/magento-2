<?php

namespace Mobbex\Webpay\Plugin;

class CsrfValidatorSkip {
    public function aroundValidate(
        \Magento\Framework\App\Request\CsrfValidator $subject,
        \Closure $proceed,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\ActionInterface $action
    ) {
         // Skip CSRF on Magento 2.1+ for Mobbex endpoints
        if ($request->getModuleName() == 'Mobbex_Webpay')
            return;

        // On Magento 2.3+
        if (strpos($request->getOriginalPathInfo(), 'sugapay') !== false)
            return;

        // Proceed Magento 2 core functionalities
        $proceed($request, $action); 
    }
}