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
    },
    config: window.checkoutConfig.payment.sugapay,
    availableMethods: ko.observableArray([]),
    availableCards: ko.observableArray([]),
    selectedOption: ko.observable(null),
    selectedOptionData: null,

    initialize: function () {
      this._super();
      this.loadPaymentOptions();

      // Subscribe to method select changes
      this.selectedOption.subscribe(this.selectPaymentMethod.bind(this));

      // Select the first method by default
      if (
        quote?.paymentMethod()?.method === this.getCode() &&
        this.availableMethods().length > 0
      )
        this.selectedOption(this.availableMethods()[0]?.subgroup);
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

    selectPaymentMethod: function () {
      var paymentData = {
        method: this.getCode(),
      };

      selectPaymentMethodAction(paymentData);
      quote.paymentMethod(paymentData);

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

      returnUrl = urlBuilder.build(
        `sugapay/payment/paymentreturn/?quote_id=${this.config.quoteId}`
      );

      createCheckout(
        urlBuilder.build('sugapay/payment/checkout/'),
        function (res) {
          $('body').trigger('processStop');

          if (!res?.id)
            this.displayAlert(
              'Error',
              'Error al obtener la información del pedido.',
              returnUrl + '&status=500'
            );

          if (this.selectedOptionData.installments) {
            this.executeWallet(res);
          } else if (this.config.embed) {
            this.embedPayment(res);
          } else {
            window.top.location.href =
              res.url +
              (this.selectedOption()
                ? '?paymentMethod=' + this.selectedOption()
                : '');
          }
        }
      );
    },

    createCheckout: async function (url, callback) {
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
            returnUrl + '&status=500'
          );
        },
      });
    },

    embedPayment: function (response) {
      var options = {
        id: response.id,
        type: 'checkout',

        onResult: (data) => {
          location.href =
            returnUrl +
            '&order_id=' +
            response.orderId +
            '&status=' +
            data.status.code;
        },

        onPayment: (data) => {
          mbbxPaymentData = data.data;
        },

        onClose: (cancelled) => {
          jQuery('body').trigger('processStop');
          location.href =
            returnUrl +
            '&order_id=' +
            response.orderId +
            '&status=' +
            (mbbxPaymentData ? mbbxPaymentData.status.code : '500');
        },

        onError: (error) => {
          jQuery('body').trigger('processStop');
          location.href =
            returnUrl + '&order_id=' + response.orderId + '&status=500';
        },
      };

      if (this.selectedOption())
        options.paymentMethod = this.selectedOption();

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
          installment: $(`#card_form_${this.selectedOption()} select`).val(),
          securityCode: $(`#card_form_${this.selectedOption()} input`).val(),
        })
        .then((data) => {
          window.top.location =
            returnUrl +
            `&order_id=${response.orderId}&status=${data.data.status.code}`;
        })
        .catch((e) => {
          console.error(e);

          this.displayAlert(
            'Error',
            'No se pudo completar el pago.',
            returnUrl + '&order_id=' + response.orderId + '&status=500'
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
