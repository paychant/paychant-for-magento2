/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader) {
        'use strict';

        return function (messageContainer) {
            var serviceUrl,
                payload,
                billingAddress,
                method = 'put',
                paymentData = quote.paymentMethod();

            /**
             * Checkout for guest and registered customer.
             */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/selected-payment-method', {
                    cartId: quote.getQuoteId()
                });
                var storageData = JSON.parse(localStorage.getItem('mage-cache-storage'))['checkout-data'];
                //paymentData.email = storageData.validatedEmailValue;
                payload = {
                    cartId: quote.getQuoteId(),
                    method: paymentData,
                    billingAddress: quote.billingAddress(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData
                };
                method = 'post';
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/selected-payment-method', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    method: paymentData,
                    billingAddress: quote.billingAddress()
                };
            }
            fullScreenLoader.startLoader();

            return storage.put(
                serviceUrl, JSON.stringify(payload)
            ).done(
                function () {
                    //$.mage.redirect(window.checkoutConfig.payment.paypalExpress.redirectUrl[quote.paymentMethod().method]);
                    $.mage.redirect(window.checkoutConfig.payment.paychant.redirectUrl[quote.paymentMethod().method]);
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
