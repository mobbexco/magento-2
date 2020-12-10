define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'webpay',
                component: 'Mobbex_Webpay/js/view/payment/method-renderer/webpay'
            }
        );
        return Component.extend({});
    }
);
