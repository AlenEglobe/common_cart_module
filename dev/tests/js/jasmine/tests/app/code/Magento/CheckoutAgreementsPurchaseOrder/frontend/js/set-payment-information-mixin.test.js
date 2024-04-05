/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'squire'
], function (Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_PurchaseOrder/js/action/place-po-order': jasmine.createSpy('placeOrder'),
            'Magento_CheckoutAgreements/js/model/agreements-assigner': jasmine.createSpy('agreementsAssigner')
        },
        context = require.s.contexts._,
        mixin,
        placePoOrder;

    beforeEach(function (done) {
        window.checkoutConfig = {
            checkoutAgreements: {
                isEnabled: true
            }
        };
        injector.mock(mocks);
        injector.require([
            'Magento_CheckoutAgreementsPurchaseOrder/js/set-payment-information-mixin',
            'Magento_PurchaseOrder/js/action/place-po-order'
        ], function (Mixin, placeOrder) {
            mixin = Mixin;
            placePoOrder = placeOrder;
            done();
        });
    });

    afterEach(function () {
        try {
            injector.clean();
            injector.remove();
        } catch (e) {}
    });

    describe('Magento_CheckoutAgreementsPurchaseOrder/js/model/place-order-mixin', function () {
        it('mixin is applied to Magento_PurchaseOrder/js/action/place-po-order', function () {
            var placeOrderMixins = context.config.config.mixins['Magento_PurchaseOrder/js/action/place-po-order'];

            expect(
                placeOrderMixins['Magento_CheckoutAgreementsPurchaseOrder/js/set-payment-information-mixin']
            ).toBe(true);
        });

        it('Magento_CheckoutAgreements/js/model/agreements-assigner is called', function () {
            var messageContainer = jasmine.createSpy('messageContainer'),
                paymentData = {};

            mixin(placePoOrder)(paymentData, messageContainer);
            expect(mocks['Magento_CheckoutAgreements/js/model/agreements-assigner'])
                .toHaveBeenCalledWith(paymentData);
            expect(mocks['Magento_PurchaseOrder/js/action/place-po-order'])
                .toHaveBeenCalledWith(paymentData, messageContainer);
        });
    });
});
