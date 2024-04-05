/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_SharedCatalog/js/wizard/step/pricing/price/storage'
], function ($, Storage) {
    'use strict';

    describe('Magento_SharedCatalog/js/wizard/step/pricing/price/storage', function () {
        var obj, customPrice;

        beforeEach(function () {
            obj = new Storage({
                modules: {
                    client: jasmine.createSpy().and.returnValue({})
                },
                customPrices: jasmine.createSpy().and.returnValue([1, 2])
            });

            customPrice = {
                'product_id': '1',
                'custom_price': '1000',
                'price_type': 'fixed',
                'website_id': 0
            };
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(obj.initialize).toBeDefined();
                expect(obj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check initClients method call', function () {
                spyOn(obj, 'initClients');
                obj.initialize();
                expect(obj.initClients).toHaveBeenCalled();
            });
        });

        describe('"initClients" method', function () {
            it('Check for defined', function () {
                expect(obj.initClients).toBeDefined();
                expect(obj.initClients).toEqual(jasmine.any(Function));
            });
        });

        describe('"setCustomPrice" method', function () {
            it('Check for defined', function () {
                expect(obj.setCustomPrice).toBeDefined();
                expect(obj.setCustomPrice).toEqual(jasmine.any(Function));
            });

            it('Check if custom prices have been saved into storage', function () {
                obj.customPrices.push = jasmine.createSpy();
                obj.customPrices.remove = jasmine.createSpy();
                obj.setCustomPrice(customPrice);
                expect(obj.customPrices.push).toHaveBeenCalled();
            });
        });

        describe('"saveProductsCustomPrice" method', function () {
            it('Check for defined', function () {
                expect(obj.saveProductsCustomPrice).toBeDefined();
                expect(obj.saveProductsCustomPrice).toEqual(jasmine.any(Function));
            });

            it('Check if custom prices have been saved', function () {
                spyOn(obj, 'prepareRequestData');
                obj.client().save = jasmine.createSpy().and.returnValue({
                    done: jasmine.createSpy()
                });
                obj.saveProductsCustomPrice();
                expect(obj.prepareRequestData).toHaveBeenCalled();
            });

            it('Check if priceSavePromise is defined', function () {
                spyOn(obj, 'prepareRequestData');
                obj.priceSavePromise = jasmine.createSpy().and.returnValue($.Deferred().promise());
                obj.saveProductsCustomPrice();
                expect(obj.prepareRequestData).not.toHaveBeenCalled();
            });

            it('Check if custom prices have not been changed', function () {
                spyOn(obj, 'prepareRequestData');
                obj.customPrices = jasmine.createSpy().and.returnValue([]);
                spyOn($, 'when').and.callFake(function () {
                    var d = $.Deferred();

                    d.resolve({
                        'success': true
                    });

                    return d.promise();
                });
                obj.saveProductsCustomPrice();
                expect(obj.customPrices().length).toBe(0);
                expect($.when).toHaveBeenCalled();
                expect(obj.prepareRequestData).not.toHaveBeenCalled();
            });
        });

        describe('"prepareRequestData" method', function () {
            it('Check for defined', function () {
                expect(obj.prepareRequestData).toBeDefined();
                expect(obj.prepareRequestData).toEqual(jasmine.any(Function));
            });

            it('Check returned value', function () {
                var prices = [customPrice];

                expect(obj.prepareRequestData(prices)).toEqual({
                    prices: prices
                });
            });
        });

        describe('"onSaveDone" method', function () {
            it('Check for defined', function () {
                expect(obj.onSaveDone).toBeDefined();
                expect(obj.onSaveDone).toEqual(jasmine.any(Function));
            });

            it('Check if variables have been reset', function () {
                obj.onSaveDone();
                expect(obj.priceSavePromise).toEqual(null);
                expect(obj.customPrices).toHaveBeenCalledWith([]);
            });
        });
    });
});
