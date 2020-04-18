define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url'
    ],
    function ($, Component, urlBuilder) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Mobbex_Webpay/payment/webpay',
                redirectAfterPlaceOrder: false
            },
            afterPlaceOrder: function (url) {
                window.location.replace(
                    urlBuilder.build('webpay/payment/redirect/')
                );
            },
            getData: function () {
                console.info(this.item);

                return {
                    'method': this.item.method,
                    'additional_data': {}
                };
            }
        });
    }
);
