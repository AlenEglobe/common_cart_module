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
        tplElement = $('<div data-role="new-block"></div>'),
        obj;

    describe('Magento_QuickOrder/js/item-table/mass-add-rows', function () {

        beforeEach(function (done) {
            injector.require(['Magento_QuickOrder/js/item-table/mass-add-rows'], function (MassAddRows) {
                obj = MassAddRows;
                done();
            });
            tplElement.appendTo(document.body);
            tplElement.trigger = jasmine.createSpy().and.returnValue(true);
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"_add" method', function () {
            it('Check for defined', function () {
                expect(obj.addNewRows).toBeDefined();
                expect(obj.addNewRows).toEqual(jasmine.any(Function));
            });

            it('Check if event is triggered', function () {
                obj.addNewRows(tplElement, 1);
                expect(tplElement.trigger).toHaveBeenCalledWith('addNewRow', {
                    callback: jasmine.any(Function)
                });
            });

            it('Check if event is not triggered', function () {
                obj.addNewRows(tplElement, 0);
                expect(tplElement.trigger).not.toHaveBeenCalled();
            });
        });
    });
});
