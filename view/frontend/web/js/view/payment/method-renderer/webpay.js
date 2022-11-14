let embed     = window.checkoutConfig.payment.webpay.config.embed && window.checkoutConfig.payment.webpay.config.embed != '0';
let wallet    = window.checkoutConfig.payment.webpay.config.wallet && window.checkoutConfig.payment.webpay.config.wallet != '0';
let returnUrl = '';

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
        $('#mbbx-banner').hide()
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
            if($(this).closest(".payment-method").has('#mbbx-banner'))
                $('#mbbx-banner').show()
            $(this).closest(".payment-method").after($('#mbbx-place-order'));
        }
    });
});


/**
 * Create checkout and call a callback
 * @param {string} url 
 * @param {callback} callback 
 **/
 function createCheckout(url, callback) {

    jQuery.ajax({
        dataType: 'json',
        method: 'GET',
        url: url,
        success: function (response) {
            callback(response.data);
        },
        error: function () {
            displayAlert('Error', 'No se ha podido obtener la información del pago.', returnUrl + '&status=500');
        }
    });

}

/** MOBBEX REDIRECT */

/**
 * Redirect to Mobbex Checkout
 * @param checkoutUrl 
 */
 function mbbxRedirect(checkoutUrl) {

    let mobbexForm = document.createElement('FORM');
    document.querySelector('#redirectForm').appendChild(mobbexForm);
    mobbexForm.setAttribute('action', checkoutUrl);
    mobbexForm.setAttribute('method', 'GET');

    if(mbbxCurrentMehtod)
        mobbexForm.innerHTML = `<input type='hidden' name='paymentMethod' value='${mbbxCurrentMehtod}'/>`;

    mobbexForm.submit();
}

/** MOBBEX EMBED */
function embedPayment(response){
    var options = {
        id: response.id,
        type: 'checkout',

        onResult: (data) => {
            location.href = returnUrl + '&status=' + data.status.code 
        },

        onClose: () => {
            jQuery("body").trigger('processStop');
            location.href = returnUrl + '&status=500'
        },

        onError: (error) => {
            jQuery("body").trigger('processStop');
            location.href = returnUrl + '&status=500'
        }
    }

    if(mbbxCurrentMehtod)
        options.paymentMethod = mbbxCurrentMehtod;

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
            window.top.location = returnUrl + '&status=' + data.data.status.code;
        })
        .catch(error => {
            displayAlert('Error', 'No se pudo completar el pago.', returnUrl + '&status=500');
        })
}

/** DISPLAY & EXECUTE FUNCTIONS */

/**
 * Display Magento alert when payment fails & redirect to cart. 
 * @param {string} alertTitle 
 * @param {string} message 
 * @param {string} failUrl 
 */
 function displayAlert(alertTitle, message, failUrl) {
     let $ = jQuery
     let alert = document.createElement('P');
     alert.textContent = message;

     $("body").trigger('processStop');
     $(alert).alert({
         title: $.mage.__(alertTitle),
         content: $.mage.__(message),
         actions: {
             always: function () {
                 jQuery("body").trigger('processStart');
                 jQuery.ajax({
                     dataType: 'json',
                     method: 'GET',
                     url: failUrl,
                     success: function () {},
                     error: function () {
                         location.reload()
                     }
                 });
             }
         }
     });
}

define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Ui/js/modal/alert'
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
                $("body").trigger('processStart');
                returnUrl = urlBuilder.build('webpay/payment/paymentreturn/') + `?order_id=${window.checkoutConfig.payment.webpay.orderId}`;
                createCheckout(urlBuilder.build('webpay/payment/checkout/'), response => {
                    $("body").trigger('processStop');
                    if(!response || !response.id){
                        displayAlert('Error', 'Error al obtener la información del pedido.', returnUrl + '&status=500');
                    }
                    if(wallet && mbbxCurrentCard){
                        executeWallet(response)
                    }else if(embed){
                        embedPayment(response)
                    } else {
                        mbbxRedirect(response.url);
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