/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_SharedCatalog/js/wizard/step/pricing/grid/provider'
], function ($, Provider) {
    'use strict';

    describe('Magento_SharedCatalog/js/wizard/step/pricing/grid/provider', function () {
        var obj;

        beforeEach(function () {
            obj = new Provider({
                modules: {
                    priceStorage: jasmine.createSpy().and.returnValue({})
                }
            });
        });

        describe('"reload" method', function () {
            it('Check for defined', function () {
                expect(obj.reload).toBeDefined();
                expect(obj.reload).toEqual(jasmine.any(Function));
            });

            it('Check if saveTempData callback has been called', function () {
                var callback = jasmine.any(Function);

                obj.saveTempData = jasmine.createSpy().and.returnValue({
                    then: jasmine.createSpy()
                });
                obj.reload(callback);
                expect(obj.saveTempData().then).toHaveBeenCalledWith(callback);
            });
        });

        describe('"saveTempData" method', function () {
            it('Check for defined', function () {
                expect(obj.saveTempData).toBeDefined();
                expect(obj.saveTempData).toEqual(jasmine.any(Function));
            });

            it('Check if saveProductsCustomPrice callback has been called', function () {
                obj.priceStorage().saveProductsCustomPrice =
                    jasmine.createSpy().and.returnValue($.Deferred().promise());
                obj.saveTempData();
                expect(obj.priceStorage().saveProductsCustomPrice).toHaveBeenCalled();
            });
        });
    });
});
