/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'ko',
    'Magento_CompanyCredit/js/fields-with-currency'
], function (ko, FieldWithCurrency) {
    'use strict';

    describe('Magento_CompanyCredit/js/fields-with-currency', function () {
        var field;

        beforeEach(function () {
            field = new FieldWithCurrency({
                dataScope: '',
                provider: 'provName',
                currencyLabel: ko.observable('')
            });
        });

        describe('"initObservable" method', function () {
            it('Check for defined', function () {
                expect(field.initObservable).toBeDefined();
                expect(field.initObservable).toEqual(jasmine.any(Function));
            });

            it('Check called "this.observe" method', function () {
                field.observe = jasmine.createSpy().and.callFake(function () {
                    return field;
                });
                field.initObservable();
                expect(field.observe).toHaveBeenCalled();
            });
        });

        describe('"setCurrencyLabel" method', function () {
            it('Check for defined', function () {
                expect(field.setCurrencyLabel).toBeDefined();
                expect(field.setCurrencyLabel).toEqual(jasmine.any(Function));
            });

            it('Check if new currency has been set', function () {
                field.setCurrencyLabel('USD/EUR');
                expect(field.currencyLabel()).toBe('USD/EUR');
            });
        });
    });
});
