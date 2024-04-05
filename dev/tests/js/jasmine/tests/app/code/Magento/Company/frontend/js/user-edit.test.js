/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_Company/js/user-edit'
], function ($, UserEdit) {
    'use strict';

    describe('Magento_Company/js/user-edit', function () {
        var obj, tplFieldCreate, tplFieldEdit;

        beforeEach(function () {
            obj = new UserEdit();
            obj.options.popup = $('<div data-role="add-customer-dialog" class="modal-container">' +
                '<input type="text" value="" class="input-text">' +
                '</div>');
            tplFieldCreate = $('<div data-role="create-additional-fields" class="additional-fields"></div>');
            tplFieldEdit = $('<div data-role="edit-additional-fields" class="additional-fields">' +
                '<input type="text" name="new-field" value="" class="input-text">' +
                '</div>');
            tplFieldCreate.appendTo(document.body);
            tplFieldEdit.appendTo(document.body);
            obj.options.popup.appendTo(document.body);
        });

        describe('"_populateForm" method', function () {
            it('Check for defined', function () {
                expect(obj._populateForm).toBeDefined();
                expect(obj._populateForm).toEqual(jasmine.any(Function));
            });

            it('Check showAdditionalFields has been called', function () {
                spyOn(obj, 'showAdditionalFields');
                obj._populateForm();
                expect(obj.showAdditionalFields).toHaveBeenCalledWith(true);
            });
        });

        describe('"showAdditionalFields" method', function () {
            it('Check for defined', function () {
                expect(obj.showAdditionalFields).toBeDefined();
                expect(obj.showAdditionalFields).toEqual(jasmine.any(Function));
            });

            it('Check if classes and attributes change', function () {
                var input = tplFieldEdit.find('[name]');

                obj.showAdditionalFields(true);
                expect(tplFieldCreate.hasClass('_hidden')).toBe(true);
                expect(tplFieldEdit.hasClass('_hidden')).toBe(false);
                expect(input.prop('disabled')).toBe(false);

                obj.showAdditionalFields(false);
                expect(tplFieldCreate.hasClass('_hidden')).toBe(false);
                expect(tplFieldEdit.hasClass('_hidden')).toBe(true);
                expect(input.prop('disabled')).toBe(true);
            });
        });
    });
});
