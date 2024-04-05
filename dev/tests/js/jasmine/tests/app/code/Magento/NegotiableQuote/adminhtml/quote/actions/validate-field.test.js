/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_NegotiableQuote/quote/actions/validate-field',
    'mage/template',
    'text!Magento_NegotiableQuote/template/error.html'
], function ($, validateField, mageTemplate, errorTpl) {
    'use strict';

    describe('Test Magento_NegotiableQuote/quote/actions/validate-field', function () {
        var obj, tplElement, event;

        beforeEach(function () {
            obj = new validateField({});
            tplElement = $('<input id="quote_name" data-role="quote_name" class="input-text" type="text">');
            event = {target: 'input#quote_name.input-text'};
        });

        describe('Test "_validateField" method', function () {
            it('Check clearError and checkVal get called', function () {
                var val = 'testName';

                spyOn($.fn, 'val').and.returnValue(val);
                spyOn(obj, '_clearError');
                spyOn(obj, '_checkVal');
                obj._validateField(event);
                expect(obj._clearError).toHaveBeenCalledWith($(event.target).parent());
                expect(obj._checkVal).toHaveBeenCalledWith(event, val);
            });
        });

        describe('Test "_checkVal" method', function () {
            it('Check _setTextError does not gets called', function () {
                spyOn(obj, '_setTextError');
                obj._checkVal(event, 'testName');
                expect(obj._setTextError).not.toHaveBeenCalled();
            });

            it('Check _setTextError gets called', function () {
                spyOn(obj, '_setTextError');
                obj._checkVal(event, '');
                expect(obj._setTextError).toHaveBeenCalledWith($(event.target), $.mage.__('This is a required field.'));
            });
        });

        describe('Test "_setTextError" method', function () {
            it('Check _renderError gets called', function () {
                spyOn(obj, '_renderError');
                obj._setTextError(tplElement, $.mage.__('This is a required field.'));
                expect(obj.options.errorText.text).toEqual($.mage.__('This is a required field.'));
                expect(obj._renderError).toHaveBeenCalledWith(tplElement, obj.options.errorText);
            });
        });

        describe('Test "_renderError" method', function () {
            it('Check error message gets set after the element', function () {
                var errorBlockTmpl = mageTemplate(errorTpl);

                obj.options.errorText.text = $.mage.__('This is a required field.');
                spyOn($.fn, 'after');
                obj._renderError(tplElement, obj.options.errorText);
                expect(tplElement.after).toHaveBeenCalledWith(
                    $(errorBlockTmpl({
                        data: obj.options.errorText
                    }))
                );
            });
        });

        describe('Test "_clearError" method', function () {
            it('Check error messages gets removed', function () {
                spyOn($.fn, 'find').and.returnValue($(obj.options.labelError));
                spyOn($.fn, 'remove');
                obj._clearError(tplElement);
                expect(tplElement.find).toHaveBeenCalledWith(obj.options.labelError);
                expect($(obj.options.labelError).remove).toHaveBeenCalled();
            });
        });
    });
});
