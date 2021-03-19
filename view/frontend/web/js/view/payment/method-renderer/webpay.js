var embed = window.checkoutConfig.payment.webpay.config.embed && window.checkoutConfig.payment.webpay.config.embed != '0';

if (embed) {

    // Add Mobbex script
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.17.js`;
    script.async = true;
    document.body.appendChild(script);

    // Remove HTML entities
    function htmlDecode(input)
    {
      var doc = new DOMParser().parseFromString(input, "text/html");
      return doc.documentElement.textContent;
    }

    // Create checkout and init Mobbex Embed
    function createCheckout(url)
    {
        jQuery.ajax({
            url: url,
            success: function(response) {
                var checkoutId = response.checkoutId;
                var returnUrl = response.returnUrl;

                var options = {
                    id: checkoutId,
                    type: 'checkout',

                    onResult: (data) => {
                        location.href = returnUrl + '&status=' + data.status.code
                    },

                    onClose: () => {
                        jQuery("body").trigger('processStop');
                        location.href = returnUrl
                    },

                    onError: (error) => {
                        console.log(error)
                        jQuery("body").trigger('processStop');
                        location.href = returnUrl
                    }
                }

                // Init Mobbex Embed
                var mbbxButton = window.MobbexEmbed.init(options);
                mbbxButton.open();
            },
            error: function() {
                console.log("No se ha podido obtener la información");
                return false;
            }
        });

    }

}

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

                if (embed) {
                    $("body").trigger('processStart');
                    createCheckout(urlBuilder.build('webpay/payment/embedpayment/'));
                } else {
                    window.location.replace(
                        urlBuilder.build('webpay/payment/redirect/')
                    );
                }

            },
            getData: function () {
                console.info(this.item);

                return {
                    'method': this.item.method,
                    'additional_data': {}
                };
            },
            getBanner: function () {
                let mobbexConfig = window.checkoutConfig.payment.webpay;
                if (mobbexConfig !== undefined) {
                    return mobbexConfig['banner'];
                }
                return '';
            }
        });
    }
);
