define([
  'jquery',
  'ko',
  'Magento_Checkout/js/view/payment/default',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/action/select-payment-method',
  'mage/url',
  'mage/translate',
  'Magento_Ui/js/model/messageList',
], function (
  $,
  ko,
  Component,
  quote,
  selectPaymentMethodAction,
  urlBuilder,
  i18n,
  messageContainer
) {
  'use strict';

  return Component.extend({
    defaults: {
      template: 'Mobbex_Webpay/payment/sugapay',
      redirectAfterPlaceOrder: false,
    },
    config: window.checkoutConfig.payment.sugapay,
    availableMethods: ko.observableArray([]),
    availableCards: ko.observableArray([]),
    selectedOption: ko.observable(null),
    selectedOptionData: null,
    returnUrl: '',

    initialize: function () {
      this._super();
      this.loadPaymentOptions();
      this.loadScript('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js');
      this.loadScript('https://res.mobbex.com/js/embed/mobbex.embed@1.0.23.js');

      // Subscribe to method select changes
      this.selectedOption.subscribe(this.selectPaymentMethod.bind(this));

      // Select the first method by default
      if (
        quote?.paymentMethod()?.method === this.getCode() &&
        this.availableMethods().length > 0
      )
        this.selectedOption(this.availableMethods()[0]?.subgroup);

      // Set returnUrl to use later
      this.returnUrl = urlBuilder.build(
        `sugapay/payment/paymentreturn/?quote_id=${this.config.quoteId}`
      );
    },

    loadPaymentOptions: function () {
      var self = this;

      self.config?.paymentMethods?.forEach(function (method) {
        self.availableMethods.push(method);
      });

      self.config?.wallet?.forEach(function (card) {
        self.availableCards.push(card);
      });
    },

    loadScript: function (src, async = true) {
      const script = document.createElement('script');
      script.src = src;
      script.async = async;

      document.body.appendChild(script);
    },

    selectPaymentMethod: function () {
      selectPaymentMethodAction({ method: this.getCode() });
      quote.paymentMethod({ method: this.getCode() });

      this.selectedOptionData =
        this.availableMethods().find(
          (m) => m.subgroup === this.selectedOption()
        ) ||
        this.availableCards().find(
          (c) => c.card.card_number === this.selectedOption()
        );

      return true;
    },

    getCode: function () {
      return 'sugapay';
    },

    isActive: function () {
      return true;
    },

    afterPlaceOrder: function () {
      $('body').trigger('processStart');

      this.createCheckout(
        urlBuilder.build('sugapay/payment/checkout/'),
        (res) => {
          $('body').trigger('processStop');

          if (!res?.id)
            this.displayAlert(
              'Error',
              'Error al obtener la información del pedido.',
              this.returnUrl + '&status=500'
            );

          if (this.selectedOptionData?.installments) {
            this.executeWallet(res);
          } else if (this.config.embed == 1) {
            this.embedPayment(res);
          } else {
            var paymentMethod =
              this.selectedOptionData?.group +
              ':' +
              this.selectedOptionData?.subgroup;

            window.top.location.href =
              res.url +
              (paymentMethod != ':' && `?paymentMethod=${paymentMethod}`);
          }
        }
      );
    },

    createCheckout: function (url, callback) {
      $.ajax({
        dataType: 'json',
        method: 'GET',
        url: url,
        success: function (response) {
          callback(response.data);
        },
        error: function (e) {
          this.displayAlert(
            'Error',
            'No se ha podido obtener la información del pago.',
            this.returnUrl + '&status=500'
          );
        },
      });
    },

    embedPayment: function (response) {
      var payment = {};
      var options = {
        id: response.id,
        type: 'checkout',

        onResult: (data) => {
          location.href =
            this.returnUrl +
            '&order_id=' +
            response.orderId +
            '&status=' +
            data.status.code;
        },

        onPayment: (data) => {
          payment = data.data;
        },

        onClose: (cancelled) => {
          jQuery('body').trigger('processStop');
          location.href =
            this.returnUrl +
            '&order_id=' +
            response.orderId +
            '&status=' +
            (payment ? payment.status.code : '500');
        },

        onError: (error) => {
          jQuery('body').trigger('processStop');
          location.href =
            this.returnUrl + '&order_id=' + response.orderId + '&status=500';
        },
      };

      if (this.selectedOption() && this.selectedOptionData?.subgroup)
        options.paymentMethod =
          this.selectedOptionData?.group +
          ':' +
          this.selectedOptionData?.subgroup;

      var mbbxButton = window.MobbexEmbed.init(options);
      mbbxButton.open();
    },

    executeWallet: function (response) {
      let updatedCard = response.wallet.find(
        (c) => c.card.card_number == this.selectedOption()
      );

      window.MobbexJS.operation
        .process({
          intentToken: updatedCard.it,
          installment: $('#card_form select').val(),
          securityCode: $('#card_form input').val(),
        })
        .then((data) => {
          window.top.location =
            this.returnUrl +
            `&order_id=${response.orderId}&status=${data.data.status.code}`;
        })
        .catch((e) => {
          console.error(e);

          this.displayAlert(
            'Error',
            'No se pudo completar el pago.',
            this.returnUrl + '&order_id=' + response.orderId + '&status=500'
          );
        });
    },

    displayAlert(alertTitle, message, failUrl) {
      let alert = document.createElement('P');
      alert.textContent = message;

      $('body').trigger('processStop');
      $(alert).alert({
        title: $.mage.__(alertTitle),
        content: $.mage.__(message),
        actions: {
          always: function () {
            jQuery('body').trigger('processStart');
            jQuery.ajax({
              dataType: 'json',
              method: 'GET',
              url: failUrl,
              success: function () {},
              error: function () {
                location.reload();
              },
            });
          },
        },
      });
    },
  });
});
