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
        mocks = {},
        tplElement = $('<div data-template="add-block"></div>'),
        params,
        obj;

    describe('Magento_QuickOrder/js/item-table', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_QuickOrder/js/item-table'], function (Item) {
                params = {
                    addBlockTmpl: jasmine.createSpy(),
                    options: {
                        addBlock: '[data-template="add-block"]'
                    }
                };
                obj = new Item(params);
                done();
            });
            tplElement.appendTo(document.body);
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"_add" method', function () {
            it('Check for defined', function () {
                expect(obj._add).toBeDefined();
                expect(obj._add).toEqual(jasmine.any(Function));
            });

            it('Check if callback is added', function () {
                obj._add();
                expect(obj.rowIndex).toEqual(1);
                expect(obj.options.itemsRenderCallbacks[obj.rowIndex]).toEqual(jasmine.any(Function));
            });
        });
    });
});
