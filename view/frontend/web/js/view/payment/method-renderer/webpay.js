let embed = window.checkoutConfig.payment.webpay.config.embed && window.checkoutConfig.payment.webpay.config.embed != '0';
let wallet = window.checkoutConfig.payment.webpay.config.wallet && window.checkoutConfig.payment.webpay.config.wallet != '0';
let dni = window.checkoutConfig.payment.webpay.config.dni && window.checkoutConfig.payment.webpay.config.dni != '0';

// for wallet usage
let walletEmpty = true;
let wallet_checkout_used = false;
let wallet_response = null;
let wallet_url_payment = null;
let wallet_checkout_id = null;
// define global array for credit cards
let creditCards = [];
// define global boolean variable to check if cards where alredy rendered
let rendered = false;
let walletReturnUrl;


/**
 * Show dni input field
 * @return boolean
 */
function renderDni()
{
    let $ = jQuery
    // get .mobbex-content && get the parent to inject HTML after div.checkout-messages
    let mobbexContainer = $(".mobbex-content").parent()
    // add id to parent, with this we can inject html AFTER checkout-messages
    mobbexContainer.attr("id", "mobbex-container")
    // aftrer messages inject dni div
    $("#mobbex-container div:eq(0)").after(`
    <div id="dni-container" style="border:0,5px solid grey;">   
        <label>DNI:</label> 
        <input type="text" id="dni-input" name="dni" minlength="7" size="2" style=" width:60%">
    </div>`);

    //Hide loader (?)
    $("body").trigger('processStop');

    return true
}

/**
 * Creates a custom Mobbex checkout using 
 * a quote and customer data when the wallet is active
 * and the customer is logged in
 * @param {*} url
 * @return array
 */
function createCheckoutWallet(url)
{
    //wallet work if the customer is logged in
    if(window.isCustomerLoggedIn){
        var customerData = JSON.stringify(window.customerData);
        var orderData = JSON.stringify(window.checkoutConfig.quoteData);
        var itemsData = JSON.stringify(window.checkoutConfig.quoteItemData);
        var totalAmount = JSON.stringify(window.checkoutConfig.totalsData);

        jQuery.ajax({
            context: '#ajaxresponse',
            url: url,
            type: "POST",
            data: {customer: customerData , quote: orderData, items:itemsData, totals: totalAmount, type: 'wallet'},
        }).done(function (data) {
            // if no wallets, do not execute rendering
            if (data.length < 1) return
            //use data['wallet'][0] to retrieve the first card data in case it exist
            creditCards = data.wallet
            //in case wallet is undefined 
            if(creditCards)
            {
                walletEmpty = false;
            }else
            {
                creditCards = [];
            }

            walletReturnUrl = data.returnUrl;
            // for each card add onClick function to show form (only if wasn't rendered yet)
            if (!rendered) {
                rendered = true
                renderWallet()
            }
            //if wallet checkout is created
            wallet_checkout_used = true;
            wallet_url_payment = data.paymentUrl;
            wallet_checkout_id = data.checkoutId;
            return JSON.stringify(data);
        });
    }
    return false;
}

/**
 * Another option: pass the wallet throw function arguments instead of global var
 *  */ 
function renderWallet() {
    let $ = jQuery
    // Add mobbex wallet SDK script
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/sdk/mobbex@1.0.0.js`;
    script.async = true;
    document.body.appendChild(script);
    // get .mobbex-content && get the parent to inject HTML after div.checkout-messages
    let mobbexContainer = $(".mobbex-content").parent()
    // add id to parent, with this we can inject html AFTER checkout-messages
    mobbexContainer.attr("id", "mobbex-container")
    // aftrer messages inject div+ul
    $("#mobbex-container div:eq(0)").after(`<div id="wallet-cards-container"><ul id="wallet-cards"></ul></div>`)

    renderCreditCards()
}

function renderCreditCards() {
    let $ = jQuery
    // get ul for credit cards
    let walletContainer = $("#wallet-cards")
    // for each card render form, inside a div with display hidden by default (must have same class and unique id)
    creditCards.forEach((card, i) => {
        let installments = card.installments
        // Add card form
        walletContainer.append(`
        <li>
            <input name="wallet-option" id="wallet-card-${i}" type="radio" value="card-${i}">
            <label for="wallet-card-${i}"><img width="30" style="border-radius: 1rem;margin: 0px 4px 0px 0px;" src="${card.source.card.product.logo}"> ${card.card.card_number}</label>
            <div class="mobbex-wallet-form" id="card-${i}" style="display: none;">
                <select name="installment"></select>
                <input style="margin-top:1rem" type="password" maxlength="${card.source.card.product.code.length}" name="security-code" placeholder="${card.source.card.product.code.name}" required>
                <input type="hidden" name="intent-token" value="${card.it}">
            </div>
        </li>
        `)
        // Add installments options to select
        installments.forEach(installment => {
            $(`#card-${i} select`).append(`<option value="${installment.reference}">${installment.name}</option>`)
        })
    })

    // Add new card method
    walletContainer.append(`
    <li>
        <input name="wallet-option" id="wallet-new-card" type="radio" value="new-card">
        <label for="new-card">Nueva tarjeta / Otro medio de pago</label>
    </li>
    `)

    // Show card form if it is selected and hide it if is not
    $("input[name=wallet-option]").on("click", () => {
        // hide all forms
        $(".mobbex-wallet-form").hide()
        let selectedCard = $("input[name=wallet-option]:checked").val()
        $(`#${selectedCard}`).show()
    })

    //Hide loader (?)
    $("body").trigger('processStop');

}

/**
 * Call Mobbex API using sdk to make the payment
 * with wallet card
 * @param {*} checkoutBuilder 
 */
function executeWallet(checkoutBuilder) {
    let $ = jQuery
    let card = $("input[name=wallet-option]:checked").val()
    if (card && card !== "new-card") {
        let installment = $(`#${card} select`).val()
        let securityCode = $(`#${card} input[name=security-code]`).val()
        let intentToken = $(`#${card} input[name=intent-token]`).val()
        
        window.MobbexJS.operation.process({
            intentToken: intentToken,
            installment: installment,
            securityCode: securityCode
        })
        .then(data => {
            location.href = walletReturnUrl+ '&status=' + data.data.status.code ;
        })
        .catch(error => {
            $("body").trigger('processStop');
            location.href =  walletReturnUrl;
        })
    }else{
        //if new-card or none option is selected, then proced to normal checkout using quote checkout data
        if(wallet_url_payment && embed)
        {
            var options = {
                id: wallet_checkout_id,
                type: 'checkout',
                
                onResult: (data) => {
                    location.href = walletReturnUrl + '&status=' + data.status.code
                },

                onClose: () => {
                    jQuery("body").trigger('processStop');
                    location.href = walletReturnUrl
                },

                onError: (error) => {
                    jQuery("body").trigger('processStop');
                    location.href = walletReturnUrl
                }
            };
            var mbbxButton = window.MobbexEmbed.init(options);
            mbbxButton.open();
        }else if(wallet_url_payment) 
        {
            window.location.href =  wallet_url_payment;
        }
    }   
} 




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

if (embed) {

    /**
     * Create checkout and init Mobbex Embed
     *  
     * */ 
    function createCheckoutEmbed(url)
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
                if  (wallet && wallet_url_payment != null) {
                    //only use wallet payment if there is at least one card stored
                    executeWallet(urlBuilder.build('webpay/payment/walletpayment/'))
                }
                else if(embed) {
                    $("body").trigger('processStart');
                    createCheckoutEmbed(urlBuilder.build('webpay/payment/embedpayment/'));
                }else{
                    window.location.replace(
                        urlBuilder.build('webpay/payment/redirect/')
                    );
                }
            },
            getData: function () {
                //When Mobbex is selected as payment method creates a checkout using quote data
                if(wallet_url_payment == null){
                    if(wallet && window.isCustomerLoggedIn){
                        //Only retrieve wallet cards and show them if the wallet config is set true and the user is logged in
                        $("body").trigger('processStart');
                        wallet_response = createCheckoutWallet(urlBuilder.build('webpay/payment/walletpayment/'));
                    }
                }
                //Mobbex is selected and show_dni is active 
                if(dni){
                    renderDni();
                }

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
