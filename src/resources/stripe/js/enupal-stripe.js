/**
 * Stripe Payments plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2018 Enupal LLC
 */

var enupalStripe = {};

(function($) {
    'use strict';
    enupalStripe = {
        // All Stripe Payment Forms
        paymentFormsList: {},
        finalData: {},
        zeroDecimals: {},

        init: function() {
            this.paymentFormsList = $('.enupal-stripe-form');

            this.zeroDecimals = ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF'];

            this.paymentFormsList.each(function() {
                var enupalButtonElement = $(this);
                enupalStripe.initializeForm(enupalButtonElement);
            });
        },

        initializeForm: function(enupalButtonElement) {
            if (typeof $(enupalButtonElement).find('[name="enupalStripe[stripeData]"]').val() === 'undefined'){
                return false;
            }
            // get the form ID
            var enupalStripeData = $.parseJSON($(enupalButtonElement).find('[name="enupalStripe[stripeData]"]').val());

            //  Firefox is cached for some reason when we empty the hidden input.
            if (navigator.userAgent.indexOf("Firefox") < 0) {
                // reset our values
                $(enupalButtonElement).find('[name="enupalStripe[stripeData]"]').val('');
            }

            this.finalData.finalAmount = enupalStripeData.stripe.amount;

            //  Stripe config
            var stripeHandler = null;

            var paymentFormId = 'stripe-payments-submit-button-'+enupalStripeData.paymentFormId;

            // Stripe Checkout handler configuration.
            // Docs: https://stripe.com/docs/checkout#integration-custom
            stripeHandler = StripeCheckout.configure({
                key: enupalStripeData.pbk,
                token: processStripeToken,
                opened: function() {
                },
                closed: function() {
                }
            });

            // Callback function to handle StripeCheckout.configure
            function processStripeToken(token, args) {
                // At this point the Stripe Checkout overlay is validated and submitted.
                // Set values to hidden elements to pass via POST when submitting the form for payment.
                enupalButtonElement.find('[name="enupalStripe[token]"]').val(token.id);
                enupalButtonElement.find('[name="enupalStripe[email]"]').val(token.email);

                // Add others values to form
                enupalStripe.addValuesToForm(enupalButtonElement, args, enupalStripeData);

                // Disable pay button and show a nice UI message
                enupalButtonElement.find('#'+paymentFormId)
                    .prop('disabled', true)
                    .find('span')
                    .text(enupalStripeData.paymentButtonProcessingText);

                // Unbind original form submit trigger before calling again to "reset" it and submit normally.
                enupalButtonElement.unbind('submit', [enupalButtonElement, enupalStripeData]);

                enupalButtonElement.submit();
            }

            // Pay Button clicked
            enupalButtonElement.find('#'+paymentFormId).on('click', function(e) {
                var form = enupalButtonElement[0];
                if (!form.checkValidity()) {
                    if (form.reportValidity) {
                        form.reportValidity();
                    } else {
                        //warn IE users somehow
                    }
                }else{
                    e.preventDefault();
                    enupalStripe.submitPayment(enupalButtonElement, enupalStripeData, stripeHandler);
                }
            });
        },

        addValuesToForm: function(enupalButtonElement, args, enupalStripeData) {
            if (enupalStripeData.stripe.shippingAddress){
                var suffix = 'shipping';
                var namespace = 'address';
                this.setAddressToHiddenValues(suffix, namespace, args, enupalButtonElement)
            }

            if (enupalStripeData.stripe.billingAddress){
                var suffix = 'billing';
                var namespace = 'billingAddress';
                this.setAddressToHiddenValues(suffix, namespace, args, enupalButtonElement)
            }
        },

        setAddressToHiddenValues(suffix, namespace, args, enupalButtonElement)
        {
            if (args[suffix+'_name']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][name]"]').val(args[suffix+'_name']);
            }

            if (args[suffix+'_address_country']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][country]"]').val(args[suffix+'_address_country_code']);
            }

            if (args[suffix+'_address_zip']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][zip]"]').val(args[suffix+'_address_zip']);
            }

            if (args[suffix+'_address_state']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][state]"]').val(args[suffix+'_address_state']);
            }

            if (args[suffix+'_address_line1']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][line1]"]').val(args[suffix+'_address_line1']);
            }

            if (args[suffix+'_address_city']) {
                enupalButtonElement.find('[name="enupalStripe['+namespace+'][city]"]').val(args[suffix+'_address_city']);
            }
        },

        submitPayment: function(enupalButtonElement, enupalStripeData, stripeHandler) {
            var enupalStripeDataSubmission = $.extend(true,{},enupalStripeData);
            var stripeConfig = enupalStripeDataSubmission.stripe;
            stripeConfig.amount = this.convertToCents(this.getFinalAmount(enupalButtonElement, enupalStripeDataSubmission), stripeConfig.currency);
            enupalButtonElement.find('[name="enupalStripe[amount]"]').val(stripeConfig.amount);
            enupalButtonElement.find('[name="enupalStripe[testMode]"]').val(enupalStripeDataSubmission.testMode);
            // If everything checks out then let's open the form
            stripeHandler.open(stripeConfig);
        },

        getFinalAmount: function(enupalButtonElement, enupalStripeData){
            // We always return a default amount
            var finalAmount = enupalStripeData.stripe.amount;
            var fee = 0;
            var isRecurring = false;

            if (!enupalStripeData.enableSubscriptions){
                // Check if custom amount
                if ( enupalStripeData.amountType == 1) {
                    var customAmount = enupalButtonElement.find( '[name="enupalStripe[customAmount]"]' ).val();
                    isRecurring = enupalButtonElement.find( '[name="enupalStripe[recurringToggle]"]' ).is(":checked");

                    if ( ( 'undefined' !== customAmount ) && ( customAmount > 0 ) ) {
                        finalAmount = customAmount;
                    }
                }
            }else{
                // Subscriptions!
                var subscriptionType = enupalStripeData.subscriptionType;

                if (subscriptionType == 0){

                    if (enupalStripeData.singleSetupFee > 0){
                        fee = enupalStripeData.singleSetupFee;
                    }
                    // single plan
                    if (enupalStripeData.enableCustomPlanAmount){
                        // Custom plan
                        var customPlanAmount = enupalButtonElement.find( '[name="enupalStripe[customPlanAmount]"]' ).val();

                        if ( ( 'undefined' !== customPlanAmount ) && ( customPlanAmount > 0 ) ) {
                            finalAmount = customPlanAmount;
                        }
                    }
                }else{
                    // Custom plan
                    var customPlanAmountId = null;
                    if (enupalStripeData.subscriptionStyle == 'radio'){
                        customPlanAmountId = $('input[name="enupalStripe[enupalMultiPlan]"]:checked').val();
                    }else{
                        customPlanAmountId = enupalButtonElement.find( '[name="enupalStripe[enupalMultiPlan]"]' ).val();
                    }
                    var customPlanAmount = null;

                    if (customPlanAmountId in enupalStripeData.multiplePlansAmounts){
                        customPlanAmount = enupalStripeData.multiplePlansAmounts[customPlanAmountId]['amount'];
                        enupalStripeData.stripe['currency'] =  enupalStripeData.multiplePlansAmounts[customPlanAmountId]['currency'];
                    }

                    if (customPlanAmountId in enupalStripeData.setupFees){
                        var multiplePlanFee = enupalStripeData.setupFees[customPlanAmountId];

                        if (multiplePlanFee > 0){
                            fee = multiplePlanFee;
                        }
                    }

                    // Multi-select plan
                    if ( ( 'undefined' !== customPlanAmount ) && ( customPlanAmount > 0 ) ) {
                        finalAmount = customPlanAmount;
                    }
                }
            }

            if ((enupalStripeData.applyTax || isRecurring) && enupalStripeData.enableTaxes){
                var tax = parseFloat((enupalStripeData.tax / 100) * (parseFloat(finalAmount) + parseFloat(fee))).toFixed(2);
                finalAmount = parseFloat(finalAmount) + parseFloat(tax);
                var taxLabel = enupalStripeData.taxLabel + ': '+enupalStripeData.currencySymbol+tax;

                enupalButtonElement.find('[name="enupalStripe[taxAmount]"]').val(tax);
                enupalButtonElement.find( '[name="tax-amount-label"]' ).empty().append(taxLabel);
            }

            return parseFloat(finalAmount) + parseFloat(fee);
        },

        convertToCents: function(amount, currency) {
            if (this.hasZeroDecimals(currency)){
                return amount;
            }

            return (amount * 100).toFixed(0);
        },

        convertFromCents: function(amount, currency) {
            if (this.hasZeroDecimals(currency)){
                return amount;
            }

            return (amount / 100);
        },

        hasZeroDecimals: function(currency){
            // Adds support for currencies with zero decimals
            for (var i = 0; i < this.zeroDecimals.length; i++) {
                if (this.zeroDecimals[i] === currency.toUpperCase()){
                    return true;
                }
            }

            return false;
        }
    };

    $(document).ready(function($) {
        enupalStripe.init();
    });
})(jQuery);