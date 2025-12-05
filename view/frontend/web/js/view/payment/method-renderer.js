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

        const config = window.checkoutConfig.payment.sugapay;

        if (config.offsite) {
            rendererList.push(
                {
                    type: 'sugapay',
                    component: 'Mobbex_Webpay/js/view/payment/method-renderer/sugapay'
                }
            );
        }

        if (config.transparent) {
            rendererList.push(
                {
                    type: 'sugapay_transparent',
                    component: 'Mobbex_Webpay/js/view/payment/method-renderer/transparent'
                }
            );
        }

        if (config.pos) {
            rendererList.push(
                {
                    type: 'sugapay_pos',
                    component: 'Mobbex_Webpay/js/view/payment/method-renderer/pos'
                }
            );
        }

        return Component.extend({});
    }
);
