define([
  'Magento_Checkout/js/view/payment/default',
  'ko',
  'jquery',
  'mage/url',
], function (Component, ko, $, urlBuilder) {
  'use strict';

  return Component.extend({
    config: window.checkoutConfig.payment.sugapay,
    availablePOS: ko.observableArray(self.config?.terminals || []),
    selectedOption: ko.observable(null),
    orderPlaced: ko.observable(false),
    defaults: {
      template: 'Mobbex_Webpay/payment/pos',
      redirectAfterPlaceOrder: false,
    },

    initialize: function () {
      this._super();

      // Set returnUrl to use later
      this.returnUrl = urlBuilder.build(
        `sugapay/payment/paymentreturn/?quote_id=${this.config.quoteId}`
      );
    },

    isActive: function () {
      return this.getCode() === this.isChecked();
    },

    getCode: function () {
      return 'sugapay_pos';
    },

    processPayment: function () {
      $('body').trigger('processStart');

      // Only place the order once
      if (!this.orderPlaced()) {
        const orderRes = this.placeOrder();

        if (!orderRes)
          return this.error(
            'Error placing the order.',
            orderRes,
            'No se pudo procesar el pedido. Por favor, revisa los datos ingresados.'
          );

        this.orderPlaced(true);
      }

      this.createPosIntent();
    },

    createPosIntent: async function () {
      const res = await fetch(
        urlBuilder.build('sugapay/payment/pos?uid=' + this.selectedOption()),
        {
          method: 'POST',
        }
      );

      $('body').trigger('processStop');

      if (!res.ok) return this.error('Error creating POS intent:', res);

      const json = await res.json();

      if (!json || json?.result !== 'success')
        return this.error('Invalid payment process response:', json);

      window.location.href = this.returnUrl + `&status=1`;
    },

    error: function (message, data = null, customerMessage = null) {
      $('body').trigger('processStop');

      this.messageContainer.addErrorMessage({
        message:
          customerMessage ||
          'Error en el procesamiento del pago. Intenta nuevamente.',
      });

      console.error(message, data);
    },
  });
});
