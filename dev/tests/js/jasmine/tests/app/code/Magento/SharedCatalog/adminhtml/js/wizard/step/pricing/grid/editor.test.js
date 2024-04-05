/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_SharedCatalog/js/wizard/step/pricing/grid/editor'
], function (Editor) {
    'use strict';

    describe('Magento_SharedCatalog/js/wizard/step/pricing/grid/editor', function () {
        var obj;

        beforeEach(function () {
            obj = new Editor({
                modules: {
                    priceStorage: jasmine.createSpy().and.returnValue({})
                }
            });
        });

        describe('"onEditComplete" method', function () {
            it('Check for defined', function () {
                expect(obj.onEditComplete).toBeDefined();
                expect(obj.onEditComplete).toEqual(jasmine.any(Function));
            });

            it('Check if saveProductsCustomPrice callback has been called', function () {
                var callback = jasmine.any(Function);

                obj.priceStorage().saveProductsCustomPrice = jasmine.createSpy().and.returnValue({
                    then: jasmine.createSpy()
                });
                obj.onEditComplete(callback);
                expect(obj.priceStorage().saveProductsCustomPrice().then).toHaveBeenCalledWith(callback);
            });
        });
    });
});
