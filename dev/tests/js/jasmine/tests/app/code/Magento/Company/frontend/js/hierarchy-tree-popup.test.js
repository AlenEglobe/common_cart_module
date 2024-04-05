/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_Company/js/hierarchy-tree-popup'
], function ($, HierarchyTreePopup) {
    'use strict';

    describe('Magento_Company/js/hierarchy-tree-popup', function () {
        var obj, tplFieldCreate, tplFieldEdit;

        beforeEach(function () {
            obj = new HierarchyTreePopup();
            obj.options.popupForm = $('<form></form><div data-role="add-customer-dialog" class="modal-container">' +
                '<input type="text" value="" class="input-text">' +
                '</div></form>');
            tplFieldCreate = $('<div data-role="create-additional-fields" class="additional-fields"></div>');
            tplFieldEdit = $('<div data-role="edit-additional-fields" class="additional-fields">' +
                '<input type="text" name="new-field" value="" class="input-text">' +
                '</div>');
            tplFieldCreate.appendTo(document.body);
            tplFieldEdit.appendTo(document.body);
            obj.options.popupForm.appendTo(document.body);
        });

        describe('"_onShow" method', function () {
            it('Check for defined', function () {
                expect(obj._onShow).toBeDefined();
                expect(obj._onShow).toEqual(jasmine.any(Function));
            });
        });

        describe('"_showAdditionalFields" method', function () {
            it('Check for defined', function () {
                expect(obj._showAdditionalFields).toBeDefined();
                expect(obj._showAdditionalFields).toEqual(jasmine.any(Function));
            });

            it('Check if classes and attributes change', function () {
                var input = tplFieldEdit.find('[name]');

                obj._showAdditionalFields(true);
                expect(tplFieldCreate.hasClass('_hidden')).toBe(true);
                expect(tplFieldEdit.hasClass('_hidden')).toBe(false);
                expect(input.prop('disabled')).toBe(false);

                obj._showAdditionalFields(false);
                expect(tplFieldCreate.hasClass('_hidden')).toBe(false);
                expect(tplFieldEdit.hasClass('_hidden')).toBe(true);
                expect(input.prop('disabled')).toBe(true);
            });
        });
    });
});
