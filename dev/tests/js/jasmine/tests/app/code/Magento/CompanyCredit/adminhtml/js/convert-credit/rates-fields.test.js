/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_CompanyCredit/js/convert-credit/rates-fields'
], function ($, RatesFields) {
    'use strict';

    describe('Magento_CompanyCredit/js/convert-credit/rates-fields', function () {
        var obj, originalJQueryAjax;

        beforeEach(function () {
            originalJQueryAjax = $.ajax;
            obj = new RatesFields({
                dataScope: ''
            });
        });

        afterEach(function () {
            $.ajax = originalJQueryAjax;
        });

        describe('"getConversionRates" method', function () {
            it('Check for defined', function () {
                expect(obj.getConversionRates).toBeDefined();
                expect(obj.getConversionRates).toEqual(jasmine.any(Function));
            });

            it('Check if conversion rate was not selected', function () {
                spyOn(obj, 'actionSelections');
                obj.getConversionRates();
                expect(obj.actionSelections).not.toHaveBeenCalled();
                expect(obj.currentCurrency).toEqual('');
            });

            it('Check if conversion rate was selected and ajax has been called', function () {
                spyOn(obj, 'actionSelections');
                $.ajax = jasmine.createSpy().and.callFake(function () {
                    var d = $.Deferred();

                    d.resolve({});

                    return d.promise();
                });
                obj.getConversionRates('USD');
                expect(obj.currentCurrency).toEqual('USD');
                expect(obj.actionSelections).toHaveBeenCalled();
                expect($.ajax).toHaveBeenCalled();
            });
        });
    });
});
