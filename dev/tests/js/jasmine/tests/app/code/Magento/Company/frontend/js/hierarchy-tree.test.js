/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_Company/js/hierarchy-tree'
], function ($, HierarchyTree) {
    'use strict';

    describe('Magento_Company/js/hierarchy-tree', function () {
        var obj, params, popup, tplElement, originalJQueryAjax;

        beforeEach(function () {
            originalJQueryAjax = $.ajax;

            obj = new HierarchyTree();
            obj.options.isAjax = false;
            params = {
                id: 1,
                type: 0
            };
            popup = $('<div data-role="add-customer-dialog" class="modal-container">' +
                '<input type="text" value="" class="input-text">' +
                '</div>');
            tplElement = $('<button id="edit-selected" type="button" data-action="edit-selected-node" ' +
                'data-edit-customer-url="customerUrl">Edit</button>');
            popup.appendTo(document.body);
            tplElement.appendTo(document.body);
        });

        afterEach(function () {
            $.ajax = originalJQueryAjax;
        });

        describe('"_populateForm" method', function () {
            it('Check for defined', function () {
                expect(obj._populateForm).toBeDefined();
                expect(obj._populateForm).toEqual(jasmine.any(Function));
            });

            it('Check sending of ajax request', function () {
                var options = {
                    url: 'customerUrl?customer_id=1',
                    type: 'get',
                    showLoader: true,
                    success: jasmine.any(Function),
                    complete: jasmine.any(Function)
                };

                $.ajax = jasmine.createSpy();
                obj._populateForm(params, popup);
                expect($.ajax).toHaveBeenCalledWith(options);
            });

            it('Check if form is unpopulated before ajax request', function () {
                var input = popup.find('input');

                obj._populateForm(params, popup);
                expect(popup.hasClass('unpopulated')).toBe(true);
                expect(input.val()).toBe('');
                expect(input.prop('readonly')).toBe(true);
            });
        });
    });
});
