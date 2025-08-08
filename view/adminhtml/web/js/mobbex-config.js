require([
    'jquery',
    'domReady!'
], function ($) {

    var showFeatured   = $('#payment_other_sugapay_appearance_show_featured_installments');
    var bestFeatured   = $('#payment_other_sugapay_appearance_best_featured_installments');
    var customFeatured = $('#payment_other_sugapay_appearance_custom_featured_installments');

    function updateFields() {
        if (showFeatured.val() != '1') {
            bestFeatured.prop('disabled', true);
            customFeatured.prop('disabled', true);
            return;
        }

        bestFeatured.prop('disabled', false);
        customFeatured.prop('disabled', bestFeatured.val() == '1');
    }

    updateFields();

    showFeatured.on('change', updateFields);
    bestFeatured.on('change', updateFields);
});