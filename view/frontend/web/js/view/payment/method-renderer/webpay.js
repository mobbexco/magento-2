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
    $(document).on('click', '[name="payment[method]"]', function (e) {
        $(".mobbex-wallet-form").hide()
        mbbxCurrentMehtod = '';
        mbbxCurrentCard = '';
        if($(this).hasClass('mbbx-payment-method-input')){
            if ($(this).hasClass("mbbx-card")) {
                mbbxCurrentCard = $(this).attr('value');
                $(`#${mbbxCurrentCard}`).show()
            } else {
                mbbxCurrentMehtod = $(this).attr('value');
            }
            $('#webpay').trigger('click');
            $(this).closest(".payment-method").after($('#mbbx-place-order'));
        }
    });
});


/**
 * Create checkout and call a callback
 *  
 * */
 function createCheckout(url, callback) {

    jQuery.ajax({
        dataType: 'json',
        method: 'POST',
        url: url,
        success: function (response) {
            callback(response);
        },
        error: function () {
            console.log("No se ha podido obtener la información");
            return false;
        }
    });

}

/** MOBBEX EMBED */
function embedPayment(response){
    var options = {
        id: response.checkoutId,
        type: 'checkout',
        paymentMethod: mbbxCurrentMehtod || '',

        onResult: (data) => {
            location.href = response.returnUrl + '&status=' + data.status.code
        },

        onClose: () => {
            jQuery("body").trigger('processStop');
            location.href = response.returnUrl
        },

        onError: (error) => {
            jQuery("body").trigger('processStop');
            location.href = response.returnUrl
        }
    }

    // Init Mobbex Embed
    var mbbxButton = window.MobbexEmbed.init(options);
    mbbxButton.open();

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
function executeWallet(response) {
    let $ = jQuery

    let updatedCard = response.wallet.find(card => card.card.card_number == $(`#${mbbxCurrentCard} input[name=card-number]`).val());
    
    var options = {
        intentToken: updatedCard.it,
        installment: $(`#${mbbxCurrentCard} select`).val(),
        securityCode: $(`#${mbbxCurrentCard} input[name=security-code]`).val()
    };
    
    window.MobbexJS.operation.process(options)
        .then(data => {
            window.top.location = response.returnUrl + '&status=' + data.data.status.code;
        })
        .catch(error => {
            $("body").trigger('processStop');
            location.href = response.returnUrl;
        })
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

                createCheckout(urlBuilder.build('webpay/payment/embedpayment/'), response => {
                    if(wallet && mbbxCurrentCard){
                        executeWallet(response)
                    }else if(embed){
                        $("body").trigger('processStart');
                        embedPayment(response)
                    } else {
                        window.location.replace(
                            urlBuilder.build('webpay/payment/redirect?paymentMethod=' + mbbxCurrentMehtod)
                        );
                    }
                })
            },
            getBanner: function () {
                if (window.checkoutConfig.payment.webpay['banner'] !== undefined) 
                    return window.checkoutConfig.payment.webpay['banner'];

                return false;
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