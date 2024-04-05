/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'squire'
], function ($, Squire) {
    'use strict';

    var injector = new Squire(),
        tplElement = $('<input type="text" data-role="product-sku"/>'),
        skuSubmit = $('<button type="submit" title="Add to Cart" class="action tocart primary" ' +
            'data-action="submit-sku"/>'),
        params,
        obj;

    describe('Magento_QuickOrder/js/product-sku-item', function () {
        beforeEach(function (done) {
            injector.require(['Magento_QuickOrder/js/product-sku-item'], function (ProductSkuItem) {
                params = {
                    options: {
                        urlSku: '',
                        rowIndex: null,
                        tableWigetSelector: '',
                        addSelector: '[data-role="product-block"]',
                        skuSelector: '[data-role="product-sku"]',
                        qtySelector: '[data-role="product-qty"]',
                        formSelector: '[data-role="send-sku"]',
                        showError: '[data-role="show-errors"]',
                        removeSelector: '[data-role="delete"]',
                        submitBtn: '[data-action="submit-sku"]',
                        formSKU: '[data-role="send-sku"]',
                        dataError: {
                            text: null
                        }
                    }
                };
                obj = new ProductSkuItem(params);
                done();
            });

            tplElement.appendTo(document.body);
            skuSubmit.appendTo(document.body);

            jQuery.post = jasmine.createSpy().and.callFake(function () {
                var d = $.Deferred();

                d.resolve([
                    {
                        items: {
                            sku: 'value1',
                            qty: 1
                        }
                    },
                    'success'
                ]);

                return d.promise();
            });

        });

        describe('"_reloadError" method', function () {
            it('Check for defined', function () {
                expect(obj._reloadError).toBeDefined();
                expect(obj._reloadError).toEqual(jasmine.any(Function));
            });

            it('Check for reloading error method', function () {
                obj._reloadError();
                expect(skuSubmit.is(':disabled')).toBeTruthy();
            });
        });

        describe('"_addByAjax" method', function () {
            it('Check for defined', function () {
                expect(obj._addByAjax).toBeDefined();
                expect(obj._addByAjax).toEqual(jasmine.any(Function));
            });

            it('Check post request sending', function () {
                var qtyElement = obj.element.find(obj.options.qtySelector);

                obj._addByAjax();
                expect(jQuery.post).toHaveBeenCalledWith('', jasmine.any(Object), jasmine.any(Function));
                expect(qtyElement.is('[readonly]')).toBeFalsy();
            });
        });

        describe('"_deleteByAjax" method', function () {
            it('Check for defined', function () {
                expect(obj._deleteByAjax).toBeDefined();
                expect(obj._deleteByAjax).toEqual(jasmine.any(Function));
            });

            it('Check delete post request sending', function () {
                var skuElement = obj.element.find(obj.options.qtySelector);

                tplElement.val('customVal');
                obj._deleteByAjax();
                expect(jQuery.post).toHaveBeenCalledWith('', jasmine.any(Object), jasmine.any(Function));
                expect(skuElement.is(':disabled')).toBeFalsy();
            });
        });
    });
});
