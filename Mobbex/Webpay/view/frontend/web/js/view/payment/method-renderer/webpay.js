var embed = window.checkoutConfig.payment.webpay.config.embed;

if (embed) {
    
    // Add Mobbex script
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.8.js?t=${Date.now()}`;
    script.async = true;
    document.body.appendChild(script);
    
    // Remove HTML entities 
    function htmlDecode(input) 
    {
      var doc = new DOMParser().parseFromString(input, "text/html");
      return doc.documentElement.textContent;
    }
    
    // Get type from status 
    function getType(status)
    {
      if(status < 2 || status > 400) {
        return "none";
      } else if (status == 2) {
        return "cash";
      } else if (status == 3 || status == 4 || status >= 200 && status < 400) {
        return "card";
      }
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
                        var status = data.status.code;
                        jQuery("body").trigger('processStop');
                        
                        // Redirect using POST form
                        var form = jQuery('<form action="' + returnUrl + '" method="post">' +
                        '<input type="text" name="order_id" value="' + data.id + '" />' +
                        '<input type="text" name="status" value="' + status + '" />' +
                        '<input type="text" name="type" value="' + getType(status) + '" />' +
                        '</form>');
                        jQuery('body').append(form);
                        form.submit();
                    },
                    onPayment: (data) => {
                        var status = data.data.status.code;
                        
                        // Redirect using POST form
                        var form = jQuery('<form action="' + returnUrl + '" method="post">' +
                        '<input type="text" name="order_id" value="' + data.data.id + '" />' +
                        '<input type="text" name="status" value="' + status + '" />' +
                        '<input type="text" name="type" value="' + getType(status) + '" />' +
                        '</form>');
                        jQuery('body').append(form);
                        
                        // If order status is not recoverable
                        if (! ((status >= 400 && status <= 500 && status != 401 && status != 402) || status == 0) ) {
                            // Redirect
                            setTimeout(function () {
                                jQuery("body").trigger('processStop');
                                form.submit();
                            }, 5000)
                        }
                    },
                    onOpen: () => {
                        // Do nothing
                    },
                    onClose: (cancelled) => {
                        // Only if cancelled
                        if (cancelled === true) {
                            jQuery("body").trigger('processStop');
                            location.reload();
                        }
                    },
                    onError: (error) => {
                        // Do nothing
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
            }
        });
    }
);
