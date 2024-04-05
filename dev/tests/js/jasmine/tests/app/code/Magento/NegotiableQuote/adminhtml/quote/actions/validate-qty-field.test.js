/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_NegotiableQuote/quote/actions/validate-qty-field',
    'mage/template',
    'text!Magento_NegotiableQuote/template/error.html'
], function ($, validateQtyField, mageTemplate, errorTpl) {
    'use strict';

    describe('Test Magento_NegotiableQuote/quote/actions/validate-qty-field', function () {
        var obj, tplElement, event;

        beforeEach(function () {
            obj = new validateQtyField({});
            tplElement = $('<input data-role="qty-amount" class="input-text item-qty admin__control-text">');
            event = {target: 'input.input-text.item-qty.admin__control-text'};
        });

        describe('Test "_validateField" method', function () {
            it('Check clearError and checkVal get called', function () {
                var val = 1;

                spyOn($.fn, 'val').and.returnValue(1);
                spyOn($.fn, 'trigger');
                spyOn(obj, '_clearError');
                spyOn(obj, '_checkVal');
                obj._validateField(event);
                expect(obj.updateBtn.trigger).toHaveBeenCalledWith(
                    'blockSend',
                    obj.options.allowSend.allow
                );
                expect(obj._clearError).toHaveBeenCalledWith($(event.target).parent());
                expect(obj._checkVal).toHaveBeenCalledWith(event, val);
            });
        });

        describe('Test "_checkVal" method', function () {
            it('Check _setTextError does not gets called', function () {
                spyOn(obj, '_setTextError');
                obj._checkVal(event, 2);
                expect(obj._setTextError).not.toHaveBeenCalled();
            });

            it('Check _setTextError gets called with "This is a required field."', function () {
                spyOn(obj, '_setTextError');
                obj._checkVal(event, '');
                expect(obj._setTextError).toHaveBeenCalledWith($(event.target), $.mage.__('This is a required field.'));
            });

            it('Check _setTextError gets called with "Please enter a number greater than 0 in this field"',
                function () {
                    spyOn(obj, '_setTextError');
                    obj._checkVal(event, 0);
                    expect(obj._setTextError).toHaveBeenCalledWith(
                        $(event.target),
                        $.mage.__('Please enter a number greater than 0 in this field')
                    );
                });

            it('Check _setTextError gets called with "Please enter a non-decimal number please"', function () {
                spyOn(obj, '_setTextError');
                obj._checkVal(event, 1.2);
                expect(obj._setTextError).toHaveBeenCalledWith(
                    $(event.target),
                    $.mage.__('Please enter a non-decimal number please')
                );
            });
        });

        describe('Test "_isNonDecimal" method', function () {
            it('Check non decimal number', function () {
                var result = obj._isNonDecimal(1);

                expect(result).toBeTrue();
            });

            it('Check non decimal number as string', function () {
                var result = obj._isNonDecimal('1');

                expect(result).toBeTrue();
            });

            it('Check decimal number', function () {
                var result = obj._isNonDecimal(1.2);

                expect(result).toBeFalse();
            });

            it('Check string', function () {
                var result = obj._isNonDecimal('string');

                expect(result).toBeFalse();
            });

            it('Check empty string', function () {
                var result = obj._isNonDecimal('');

                expect(result).toBeFalse();
            });
        });

        describe('Test "_setTextError" method', function () {
            it('Check _renderError gets called', function () {
                spyOn($.fn, 'trigger');
                spyOn(obj, '_renderError');
                obj._setTextError(tplElement, $.mage.__('This is a required field.'));
                expect(obj.updateBtn.trigger).toHaveBeenCalledWith(
                    'blockSend',
                    obj.options.allowSend.block
                );
                expect(obj.options.errorText.text).toEqual($.mage.__('This is a required field.'));
                expect(obj._renderError).toHaveBeenCalledWith(tplElement, obj.options.errorText);
            });
        });

        describe('Test "_enableBtn" method', function () {
            it('Check button is enabled', function () {
                spyOn($.fn, 'val').and.returnValues(1, 2);
                spyOn($.fn, 'toggleClass').and.returnValue(obj.updateBtn);
                spyOn($.fn, 'attr');
                obj._enableBtn();
                expect(obj.updateBtn.toggleClass).toHaveBeenCalledWith('enabled', true);
                expect(obj.updateBtn.attr).toHaveBeenCalledWith('disabled', false);
            });

            it('Check button is disabled', function () {
                spyOn($.fn, 'val').and.returnValues(1, 1);
                spyOn($.fn, 'toggleClass').and.returnValue(obj.updateBtn);
                spyOn($.fn, 'attr');
                obj._enableBtn();
                expect(obj.updateBtn.toggleClass).toHaveBeenCalledWith('enabled', false);
                expect(obj.updateBtn.attr).toHaveBeenCalledWith('disabled', true);
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
