/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
      
        rendererList.push(
            {
                type: 'storepluginspaychant',
                component: 'Storeplugins_Paychant/js/view/payment/method-renderer/paychant-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
