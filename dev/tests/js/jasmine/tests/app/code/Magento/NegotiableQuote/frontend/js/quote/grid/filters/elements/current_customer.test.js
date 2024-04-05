/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'Magento_NegotiableQuote/js/quote/grid/filters/elements/current_customer'
], function (CurrentCustomer) {
    'use strict';

    describe('NegotiableQuote/js/quote/grid/filters/elements/current_customer', function () {
        var obj, params;

        beforeEach(function () {
            params = {
                dataScope: 'abstract',

                /** @inheritdoc */
                parent: function () {
                    return {
                        apply: jasmine.createSpy()
                    };
                }
            };
            obj = new CurrentCustomer(params);
        });

        describe('"isApplied" method', function () {
            it('Check for defined', function () {
                expect(obj.isApplied).toBeDefined();
                expect(obj.isApplied).toEqual(jasmine.any(Function));
            });

            it('Check if filter is applied', function () {
                expect(obj.isApplied()).toBe(false);
                obj.value(true);
                expect(obj.isApplied()).toBe(true);
            });
        });

        describe('"apply" method', function () {
            it('Check for defined', function () {
                expect(obj.apply).toBeDefined();
                expect(obj.apply).toEqual(jasmine.any(Function));
            });

            it('Check if parent filter is applied', function () {
                expect(obj.value()).toBe('');
                obj.parent = jasmine.createSpy().and.returnValue({
                    apply: jasmine.createSpy()
                });
                obj.apply();
                expect(obj.value()).toBe(true);
            });
        });

        describe('"clear" method', function () {
            it('Check for defined', function () {
                expect(obj.clear).toBeDefined();
                expect(obj.clear).toEqual(jasmine.any(Function));
            });

            it('Check if parent filter is clear', function () {
                obj.parent = jasmine.createSpy().and.returnValue({
                    apply: jasmine.createSpy()
                });
                obj.clear();
                expect(obj.value()).toBe(null);
            });
        });
    });
});
