define([
  'Magento_Checkout/js/view/payment/default',
  'ko',
  'jquery',
  'mage/url',
  'Magento_Checkout/js/model/quote',
], function (Component, ko, $, urlBuilder, quote) {
  'use strict';

  return Component.extend({
    config: window.checkoutConfig.payment.sugapay,
    availablePOS: ko.observableArray(self.config?.terminals || []),
    selectedOption: ko.observable(null),
    orderPlaced: ko.observable(false),
    processResult: ko.observable(null),
    pollInterval: ko.observable(null),
    defaults: {
      template: 'Mobbex_Webpay/payment/pos',
      redirectAfterPlaceOrder: false,
    },

    // Approved status types
    approvedTypes: [
      'order_status_approved',
      'order_status_in_process',
      'order_status_revision',
      'order_status_authorized',
    ],

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

    cancelPayment: async function () {
      $('body').trigger('processStart');

      const res = await fetch(
        urlBuilder.build(`sugapay/payment/pos?uid=${this.selectedOption()}`),
        {
          method: 'DELETE',
        }
      );

      if (!res.ok)
        return this.error(
          'Error cancelling POS intent:',
          res,
          'No se pudo cancelar el pago. Intenta nuevamente.'
        );

      const json = await res.json();

      if (!json || json?.result !== 'success')
        return this.error(
          'Invalid cancellation response:',
          json,
          'No se pudo cancelar el pago. Intenta nuevamente.'
        );

      $('body').trigger('processStop');
      this.retryPayment();
    },

    retryPayment: function () {
      this.stopPolling();
      this.processResult(null);
      this.selectedOption(null);
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

      this.pollStatus(this.selectedOption());
    },

    pollStatus: function (posUid) {
      // Clear any existing interval
      this.stopPolling();

      const interval = setInterval(async () => {
        const res = await fetch(
          urlBuilder.build('sugapay/payment/pos?uid=' + posUid)
        );

        if (!res.ok) return this.error('Error polling payment status:', res);

        const json = await res.json();

        if (!json || json?.result !== 'success')
          return this.error('Invalid payment status response:', json);

        // Ignore it, webhook not impacted
        if (json.data.code === 'new') return;

        this.processResult(json.data);

        // Redirect on approved status, after 3 seconds
        if (this.approvedTypes.includes(json.data.type))
          setTimeout(this.goToReturnUrl.bind(this), 3000);

        this.stopPolling();
      }, 3000);

      this.pollInterval(interval);
    },

    stopPolling: function () {
      const interval = this.pollInterval();

      if (interval) {
        clearInterval(interval);
        this.pollInterval(null);
      }
    },

    goToReturnUrl: function () {
      window.top.location.href = this.returnUrl + '&status=200';
    },

    selectPaymentMethod: function () {
      selectPaymentMethodAction({ method: this.getCode() });
      quote.paymentMethod({ method: this.getCode() });

      return true;
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
