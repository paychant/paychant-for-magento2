/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
    ],
    function ($, Component, setPaymentMethodAction, quote) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Storeplugins_Paychant/payment/paychant'
            },
            afterPlaceOrder: function () {
                $.mage.redirect(window.checkoutConfig.payment.paychant.redirectUrl[quote.paymentMethod().method]);
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.paychant.instructions;
            }
        });
    }
);
