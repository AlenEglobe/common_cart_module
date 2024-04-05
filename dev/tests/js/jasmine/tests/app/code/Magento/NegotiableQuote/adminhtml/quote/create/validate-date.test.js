/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_NegotiableQuote/quote/create/validate-date'
], function ($, ValidateDate) {
    'use strict';

    describe('Magento_NegotiableQuote/quote/create/validate-date', function () {
        var obj, tplElement;

        beforeEach(function () {
            obj = new ValidateDate({});
            tplElement = $('<input data-role="expiration-date" type="text" value="09/30/17">');
            obj.options.oldDate = '04/04/2017';
            tplElement.appendTo(document.body);
        });

        describe('"_validateDate" method', function () {
            it('Check for defined', function () {
                expect(obj._validateDate).toBeDefined();
                expect(obj._validateDate).toEqual(jasmine.any(Function));
            });

            it('Check if expiration date is valid', function () {
                var expDate = new Date('2117','09','05');

                spyOn($.fn, 'datepicker').and.returnValue(expDate);
                spyOn(obj, '_checkToday');
                spyOn(obj, 'isValidDateFormat').and.returnValue(true);
                obj._validateDate();
                expect(obj._checkToday).not.toHaveBeenCalled();
                expect(obj.isValidDateFormat).toHaveBeenCalled();
                expect(tplElement.datepicker).toHaveBeenCalledWith('setDate', expDate);
                expect(obj.options.oldDate).toEqual('09/30/17');
            });

            it('Check if expiration date is not valid (already expired)', function () {
                var expDate = new Date('2017','05','05');

                spyOn($.fn, 'datepicker').and.returnValue(expDate);
                spyOn(obj, '_checkToday');
                obj._validateDate();
                expect(obj._checkToday).toHaveBeenCalled();
                expect(tplElement.val()).toEqual('04/04/2017');
            });
        });

        describe('"isValidDateFormat" method', function () {
            it('Check for defined', function () {
                expect(obj.isValidDateFormat).toBeDefined();
                expect(obj.isValidDateFormat).toEqual(jasmine.any(Function));
            });

            it('Check if date format is valid', function () {
                var expDate = new Date('2017','05','05'),
                    dateFormat = 'mm/d/y';

                spyOn($.fn, 'datepicker').and.returnValue(dateFormat);
                spyOn($.datepicker, 'parseDate');
                expect(obj.isValidDateFormat(expDate)).toEqual(true);
                expect(tplElement.datepicker).toHaveBeenCalledWith('option', 'dateFormat');
                expect($.datepicker.parseDate).toHaveBeenCalledWith(dateFormat, expDate);
            });
        });
    });
});
