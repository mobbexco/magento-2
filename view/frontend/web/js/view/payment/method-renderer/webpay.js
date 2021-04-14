var embed = window.checkoutConfig.payment.webpay.config.embed && window.checkoutConfig.payment.webpay.config.embed != '0';
var wallet = window.checkoutConfig.payment.webpay.config.wallet && window.checkoutConfig.payment.webpay.config.wallet != '0';



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
        
        console.log(customerData);
        console.log(orderData);
        console.log(itemsData);
        jQuery.ajax({
            context: '#ajaxresponse',
            url: url,
            type: "POST",
            data: {customer: customerData , quote: orderData, items:itemsData, totals: totalAmount},
        }).done(function (data) {
            //use data['wallet'][0] to retrieve the first card data in case it exist
            return data;
        });
    }
}


/*
function executeWallet(checkoutUrl) {
    lockForm()
    var securityCode;
    var installment;
    var intentToken;
    var cards = document.getElementsByName("walletCard")
    for (var i = 0; i < cards.length; i++) {
      if (cards[i].checked) {
        var cardIndex = cards[i].value
        var cardDiv = document.getElementById(`card_${cardIndex}_form`)
        securityCode = cardDiv.getElementsByTagName("input")[0].value
        maxlength = cardDiv.getElementsByTagName("input")[0].getAttribute('maxlength')
        if (securityCode.length < parseInt(maxlength)) {
          cardDiv.getElementsByTagName("input")[0].style.borderColor = '#dc3545'
          unlockForm()
          return alert("Código de seguridad incompleto")
        }
        installment = cardDiv.getElementsByTagName("select")[0].value
        intentToken = cardDiv.getElementsByTagName("input")[1].value
      }
    }
    window.MobbexJS.operation.process({
      intentToken: intentToken,
      installment: installment,
      securityCode: securityCode
    })
      .then(data => {
        if (data.result) {
          var status = data.data.status.code;
          var link = checkoutUrl + '&status=' + status + '&type=card' + '&transactionId=' + data.data.id;
          setTimeout(function(){window.top.location.href = link}, 5000)
        }
        else {
          alert("Error procesando el pago")
          unlockForm()
        }
      })
      .catch(error => {
        alert("Error: " + error)
        unlockForm()
      })
  }
*/


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
                //When Mobbex is selected as paymen method creates a checkout usin quote data
                if(wallet){
                    $("body").trigger('processStart');
                    var response = createCheckoutWallet(urlBuilder.build('webpay/payment/walletpayment/'));
                    console.info(response);
                }
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
