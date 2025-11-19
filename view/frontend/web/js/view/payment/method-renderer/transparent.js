define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
    'mage/url',
    'Magento_Checkout/js/model/quote'
], function (
    Component,
    ko,
    urlBuilder,
    quote
) {
    'use strict';

    return Component.extend({
        config: window.checkoutConfig.payment.sugapay,
        defaults: {
            template: 'Mobbex_Webpay/payment/cc',
            ccForm: 'Mobbex_Webpay/payment/cc-form',
            redirectAfterPlaceOrder: false,
        },

        // Observables para los campos del formulario
        cardHolderDocument: ko.observable(''),
        cardNumber: ko.observable(''),
        cardExpiration: ko.observable(''),
        cardCvv: ko.observable(''),
        cardHolderName: ko.observable(''),
        cardType: ko.observable(''),
        cardInstallment: ko.observable(1),

        // Loader observable
        isLoading: ko.observable(false),

        // Form initialization
        initFormElement: function() {
            return true;
        },

        // Tarjetas disponibles y seleccionada
        availableCardTypes: ko.observableArray([]),
        selectedCardType: ko.observable(''),

        cardListInstallments: ko.observableArray([
            { reference: 1, name: "1 pago" }
        ]),

        installmentTextInfo: ko.observable(false),
        installmentTextCFT: ko.observable(null),
        installmentTextTNA: ko.observable(null),
        installmentTextTEA: ko.observable(null),

        isActive: function() {
            return this.getCode() === this.isChecked();
        },

        getCode: function() { return 'sugapay_transparent'; },

        // Formatear el número de tarjeta con espacios cada 4 dígitos
        formatCardNumber: function(value) {
            var digits = value.replace(/\D/g, '');
            digits = digits.slice(0, 19);
            var formatted = digits.replace(/(.{4})/g, '$1 ').trim();
            return formatted;
        },

        // Formatear la fecha de expiración como "MM / AA" automáticamente
        formatCardExpiration: function(value) {
            // Elimina todo lo que no sea dígito
            var digits = value.replace(/\D/g, '');
            // Limita a 4 dígitos
            digits = digits.slice(0, 4);

            if (digits.length === 0) {
                return '';
            }
            if (digits.length <= 2) {
                return digits;
            }
            // Inserta " / " después de los primeros 2 dígitos
            var formatted = digits.slice(0, 2) + ' / ' + digits.slice(2);
            // Limita a 7 caracteres ("MM / AA")
            return formatted.slice(0, 7);
        },

        // Limitar valores en tiempo real para los campos DNI, nombre y CVV
        limitFieldLength: function(obs, maxLength) {
            obs.subscribe(function(newValue) {
                if (typeof newValue === 'string' && newValue.length > maxLength) {
                    obs(newValue.slice(0, maxLength));
                }
            });
        },

        fetchSources: async function() {
            const res = await fetch(urlBuilder.build(
                'sugapay/payment/sources?total=' + (quote?.totals()?.base_grand_total || 0)
            ));

            if (!res.ok) {
                console.error('Error fetching payment sources:', res);
                return;
            }

            const data = await res.json();

            if (!data || !Array.isArray(data?.sources)) {
                console.error('Invalid data format for payment sources:', data);
                return;
            }

            // Filtrar solo tarjetas que permiten cuotas (priorizando crédito)
            const filtered = data.sources.filter(
                source => source?.view?.group === 'card' && source?.installments?.enabled
            );

            // Solo permitir hasta 7 fotos de tarjeta
            if (filtered.length > 7) {
                filtered.splice(7);
            }

            // Actualizar observable con los tipos de tarjeta permitidos
            this.availableCardTypes(
                filtered.map(source => source.source)
            );
        },

        fetchInstallments: async function() {
            // Obtener el número de tarjeta sin espacios
            const cardNumber = this.cardNumber().replace(/\s/g, '');
            if (cardNumber.length < 6) {
                // No hay suficientes dígitos para identificar la tarjeta
                this.selectedCardType('');
                this.cardListInstallments([{ value: 1, label: "1 cuota" }]);
                this.cardInstallment(1);
                return;
            }

            const bin = cardNumber.slice(0, 6); // Primeros 6 dígitos
            
            this.isLoading(true);

            const res = await fetch(urlBuilder.build('sugapay/payment/detect'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    bin: bin,
                    token: this.config.intentToken
                })
            });

            if (!res.ok) {
                console.error('Error fetching installment options:', res);
                this.isLoading(false);
                return;
            }

            const data = await res.json();

            if (!data)
                console.error('Invalid data format for installments:', data);
            
            this.isLoading(false);
            this.selectedCardType(data?.source?.reference || '');
            this.cardListInstallments(data?.installments || [{ reference: 1, name: "1 pago" }]);
            this.cardInstallment(data?.installments?.[0]?.reference || 1);
        },


        /**
         * Suscriptor para formatear el número de tarjeta y la fecha de expiración en tiempo real,
         * y limitar los campos DNI, nombre y CVV a sus máximos permitidos.
         */
        initialize: function() {
            this._super();

            var self = this;

            this.fetchSources();

            // Formatear el número de tarjeta en tiempo real
            this.cardNumber.subscribe(function(newValue) {
                var formatted = self.formatCardNumber(newValue);
                if (newValue !== formatted) {
                    self.cardNumber(formatted);

                    self.fetchInstallments();
                }
            });

            // Formatear la fecha de expiración en tiempo real
            this.cardExpiration.subscribe(function(newValue) {
                var formatted = self.formatCardExpiration(newValue);
                if (newValue !== formatted) {
                    self.cardExpiration(formatted);
                }
            });

            // Limitar DNI a 9 caracteres
            this.limitFieldLength(this.cardHolderDocument, 9);

            // Limitar nombre a 26 caracteres y filtra números
            this.cardHolderName.subscribe(function(newValue) {
                var strValue = typeof newValue === 'string' ? newValue : String(newValue);

                var cleanValue = strValue.replace(/\d/g, '').slice(0, 26);
                if (strValue !== cleanValue) {
                    self.cardHolderName(cleanValue);
                }
            });

            // Limitar CVV a 4 caracteres
            this.cardCvv.subscribe(function(newValue) {
                var strValue = typeof newValue === 'string' ? newValue : String(newValue);
                // Elimina cualquier caracter no numérico y limita a 4 dígitos
                var cleanValue = strValue.replace(/\D/g, '').slice(0, 4);
                if (strValue !== cleanValue) {
                    self.cardCvv(cleanValue);
                }
            });

            return this;
        },

        validate: function() {
            var isValid = this._super();

            // Validaciones manuales de longitud
            if (!this.cardNumber() || this.cardNumber().replace(/\s/g, '').length > 19) {
                isValid = false;
            }
            if (!this.cardCvv() || this.cardCvv().toString().length > 4) {
                isValid = false;
            }
            // cardExpiration debe tener máximo 7 caracteres ("MM / AA")
            if (!this.cardExpiration() || this.cardExpiration().length > 7) {
                isValid = false;
            }
            if (!this.cardHolderDocument() || this.cardHolderDocument().toString().length > 9) {
                isValid = false;
            }
            if (!this.cardHolderName() || this.cardHolderName().toString().length > 26) {
                isValid = false;
            }
            return isValid;
        },

        // Validaciones de formulario y colocación de pedido
        beforePlaceOrder: function(data, event) {
            if (event)
                event.preventDefault();

            if (!this.validate()) {
                this.messageContainer.addErrorMessage({ message: 'Por favor, revisa los datos ingresados.' });
                this.isLoading(false);
                return false;
            }
            this.isLoading(true);
            this.placeOrder();
            return true;
        },

        // Procesar pago
        afterPlaceOrder: async function() {
            this.isLoading(true);

            const res = await fetch(urlBuilder.build('sugapay/payment/process'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    number: this.cardNumber().replace(/\s/g, ''),
                    expiry: this.cardExpiration().replace(/\s/g, ''),
                    cvv: String(this.cardCvv()),
                    name: this.cardHolderName(),
                    identification: String(this.cardHolderDocument()),
                    installments: String(this.cardInstallment())
                })
            });

            if (!res.ok) {
                this.isLoading(false);
                this.messageContainer.addErrorMessage({ message: 'Error en el procesamiento del pago. Intenta nuevamente.' });
                console.error('Error processing payment:', res, await res.text());
                return false;
            }

            const json = await res.json();
            this.isLoading(false);

            if (!json || json?.result !== 'success') {
                this.messageContainer.addErrorMessage({ message: 'Error en el procesamiento del pago. Intenta nuevamente.' });
                console.error('Invalid payment process response:', json);
                return;
            }

            // Redirigir a la URL de retorno con estado de éxito
            window.top.location.href = urlBuilder.build(
                `sugapay/payment/paymentreturn/?quote_id=${this.config.quoteId}&status=${json?.code}`
            );
            return;
        }
    });
});