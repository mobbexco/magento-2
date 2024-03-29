define(
    [
        'jquery',
        'mage/utils/wrapper',
        'Magento_Checkout/js/model/quote'
    ], 
    function ($, wrapper, quote) {
        'use strict';
        return function (setShippingInformationAction) {

            return wrapper.wrap(setShippingInformationAction, function (originalAction) {
                var shippingAddress = quote.shippingAddress();

                // Exit if dni option is disabled
                if (typeof shippingAddress.customAttributes === 'undefined' || !Array.isArray(shippingAddress.customAttributes))
                    return originalAction();

                if (typeof shippingAddress['extension_attributes'] === 'undefined')
                    shippingAddress['extension_attributes'] = {};

                // Get DNI field
                var dniField = shippingAddress.customAttributes.find(field => field.attribute_code === 'mbbx_dni');

                // Add to shipping adress
                if (dniField)
                    shippingAddress['extension_attributes']['mbbx_dni'] = dniField.value;

                return originalAction();
            });
        };
    }
);