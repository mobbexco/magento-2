let embed     = window.checkoutConfig.payment.webpay.config.embed && window.checkoutConfig.payment.webpay.config.embed != '0';
let wallet    = window.checkoutConfig.payment.webpay.config.wallet && window.checkoutConfig.payment.webpay.config.wallet != '0';
let returnUrl = window.checkoutConfig.payment.webpay.returnUrl;

// Add Mobbex script
var script = document.createElement('script');
script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.20.js`;
script.async = true;
document.body.appendChild(script);

// Remove HTML entities
function htmlDecode(input) {
    var doc = new DOMParser().parseFromString(input, "text/html");
    return doc.documentElement.textContent;
}

/** PAYMENT METHODS SUBDIVISION EVENTS */

//Current payment method & card
let mbbxCurrentMehtod = '';
let mbbxCurrentCard = false;

require(['jquery'], function ($) {
    $(document).on('click', '.mbbx-payment-method-input', function (e) {
        $(".mobbex-wallet-form").hide()
        mbbxCurrentMehtod = $(this).attr('value');
        if ($(this).hasClass("mbbx-card")) {
            mbbxCurrentMehtod = '';
            mbbxCurrentCard = $(this).attr('value');
            $(`#${mbbxCurrentCard}`).show()
        }
    });
});

/** MOBBEX EMBED */

/**
 * Create checkout and init Mobbex Embed
 *  
 * */
 function createCheckoutEmbed(url) {

    jQuery.ajax({
        url: url,
        success: function (response) {
            
            var checkoutId = response.checkoutId;
            returnUrl = response.returnUrl;

            var options = {
                id: checkoutId,
                type: 'checkout',
                paymentMethod: mbbxCurrentMehtod || '',

                onResult: (data) => {
                    location.href = returnUrl + '&status=' + data.status.code
                },

                onClose: () => {
                    jQuery("body").trigger('processStop');
                    location.href = returnUrl
                },

                onError: (error) => {
                    jQuery("body").trigger('processStop');
                    location.href = returnUrl
                }
            }

            // Init Mobbex Embed
            var mbbxButton = window.MobbexEmbed.init(options);
            mbbxButton.open();
        },
        error: function () {
            console.log("No se ha podido obtener la información");
            return false;
        }
    });

}

/** MOBBEX WALLET */

/**
 * Add Mobbex Wallet SDK script 
 */
function insertWalletSdk() {
    // Add mobbex wallet SDK script
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/sdk/mobbex@1.0.0.js`;
    script.async = true;
    document.body.appendChild(script);
}

/**
 * Call Mobbex API using sdk to make the payment
 * with wallet card
 * @param {*} checkoutBuilder 
 */
function executeWallet(url) {
    let $ = jQuery

    if(!returnUrl.includes(url)) {
        returnUrl = url+'?quote_id=' + window.checkoutConfig.quoteData.entity_id;
    }

    if (mbbxCurrentCard) {
        let installment  = $(`#${mbbxCurrentCard} select`).val()
        let securityCode = $(`#${mbbxCurrentCard} input[name=security-code]`).val()
        let intentToken  = $(`#${mbbxCurrentCard} input[name=intent-token]`).val()

        window.MobbexJS.operation.process({
                intentToken: intentToken,
                installment: installment,
                securityCode: securityCode
            })
            .then(data => {
                window.top.location = returnUrl + '&status=' + data.data.status.code;
            })
            .catch(error => {
                $("body").trigger('processStop');
                location.href = returnUrl;
            })

    } else {
        //if new-card or none option is selected, then proced to normal checkout using quote checkout data
        if (window.checkoutConfig.payment.webpay.paymentUrl && embed) {
            var options = {
                id: window.checkoutConfig.payment.webpay.checkoutId,
                type: 'checkout',
                paymentMethod: mbbxCurrentMehtod || '',

                onResult: (data) => {
                    location.href = returnUrl + '&status=' + data.status.code
                },

                onClose: () => {
                    jQuery("body").trigger('processStop');
                    location.href = returnUrl
                },

                onError: (error) => {
                    jQuery("body").trigger('processStop');
                    location.href = returnUrl
                }
            };
            var mbbxButton = window.MobbexEmbed.init(options);
            mbbxButton.open();
        } else if (window.checkoutConfig.payment.webpay.paymentUrl) {
            window.location.href = window.checkoutConfig.payment.webpay.paymentUrl + '?paymentMethod=' + mbbxCurrentMehtod;
        }
    }
}

/** DISPLAY & EXECUTE FUNCTIONS */

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
            onSelect: function () {
                if(wallet) {
                    insertWalletSdk();
                }
                return true;
            },
            afterPlaceOrder: function () {
                if (wallet && window.checkoutConfig.payment.webpay.paymentUrl != null) {
                    //only use wallet payment if there is at least one card stored
                    executeWallet(urlBuilder.build('webpay/payment/paymentreturn'))
                } else if (embed) {
                    $("body").trigger('processStart');
                    createCheckoutEmbed(urlBuilder.build('webpay/payment/embedpayment/'));
                } else {
                    window.location.replace(
                        urlBuilder.build('webpay/payment/redirect?paymentMethod=' + mbbxCurrentMehtod)
                    );
                }
            },
            getData: function () {
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
            },
            getPaymentMethods: function () {
                return window.checkoutConfig.payment.webpay['paymentMethods'];
            },
            getWalletCards: function () {
                return window.checkoutConfig.payment.webpay['walletCreditCards'];
            }
        });
    }
);