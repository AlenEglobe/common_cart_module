/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire'
], function (Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_SalesRule/js/action/set-coupon-code': jasmine.createSpy(),
            'Magento_SalesRule/js/action/cancel-coupon': jasmine.createSpy()
        },
        params,
        discount;

    describe('NegotiableQuote/js/view/payment/discount', function () {

        beforeEach(function (done) {
            window.checkoutConfig = {
                isDiscountFieldLocked: true,
                isNegotiableQuote: true
            };

            injector.mock(mocks);
            injector.require(['Magento_NegotiableQuote/js/view/payment/discount'], function (Discount) {
                params = {
                    validate: jasmine.createSpy().and.returnValue(true)
                };
                discount = new Discount(params);
                done();
            });
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"apply" method', function () {
            it('Check for defined', function () {
                expect(discount.apply).toBeDefined();
                expect(discount.apply).toEqual(jasmine.any(Function));
            });

            it('Check apply procedure', function () {
                discount.apply();
                expect(mocks['Magento_SalesRule/js/action/set-coupon-code']).not.toHaveBeenCalled();
                discount.isDisable = false;
                discount.apply();
                expect(mocks['Magento_SalesRule/js/action/set-coupon-code']).toHaveBeenCalled();
            });
        });

        describe('"cancel" method', function () {
            it('Check for defined', function () {
                expect(discount.cancel).toBeDefined();
                expect(discount.cancel).toEqual(jasmine.any(Function));
            });

            it('Check cancel procedure', function () {
                discount.cancel();
                expect(mocks['Magento_SalesRule/js/action/cancel-coupon'].calls.count()).toEqual(1);
                discount.validate = jasmine.createSpy().and.returnValue(false);
                discount.cancel();
                expect(discount.couponCode()).toEqual('');
                expect(mocks['Magento_SalesRule/js/action/cancel-coupon'].calls.count()).toEqual(1);
            });
        });
    });
});
