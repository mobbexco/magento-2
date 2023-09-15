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
                type: 'sugapay',
                component: 'Mobbex_Webpay/js/view/payment/method-renderer/sugapay'
            }
        );
        return Component.extend({});
    }
);
