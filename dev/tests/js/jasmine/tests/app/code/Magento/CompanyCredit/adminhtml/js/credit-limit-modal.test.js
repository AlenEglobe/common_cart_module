/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'ko',
    'Magento_CompanyCredit/js/credit-limit-modal'
], function (ko, CreditLimitModal) {
    'use strict';

    describe('Magento_CompanyCredit/js/credit-limit-modal', function () {
        var obj, params;

        beforeEach(function () {
            params = {
                modules: {
                    creditLimit: jasmine.createSpy().and.returnValue({
                        initialValue: ''
                    }),
                    currencyRate: jasmine.createSpy().and.returnValue({
                        initialValue: ''
                    }),
                    currencyCode: jasmine.createSpy(),
                    modalHtmlContent: jasmine.createSpy().and.returnValue({
                        initialValue: ''
                    })
                },
                currencySymbol: '€',
                initialCurrencyCodeName: 'RUB'
            };

            obj = new CreditLimitModal(params);

            obj.source = {
                set: jasmine.createSpy(),
                get: jasmine.createSpy().and.returnValue('100,00')
            };
        });

        describe('"openModal" method', function () {
            it('Check for defined', function () {
                expect(obj.openModal).toBeDefined();
                expect(obj.openModal).toEqual(jasmine.any(Function));
            });

            it('Check if getInitialCurrencySymbol has been called', function () {
                obj.creditLimit().addbefore = jasmine.createSpy().and.returnValue('$');
                spyOn(obj, 'getInitialCurrencySymbol');
                obj.openModal();
                expect(obj.getInitialCurrencySymbol).toHaveBeenCalled();
            });

            it('Check if values have been saved', function () {
                obj.currencyRate().setCurrencyLabel = jasmine.createSpy();
                obj.currencyRate().enable = jasmine.createSpy().and.returnValue({
                    reset: jasmine.createSpy()
                });
                obj.modalHtmlContent().updateContent = jasmine.createSpy();
                obj.currencyCode().initialValue = 'EUR';
                obj.openModal('USD');
                expect(obj.oldCurrencySymbol).toEqual('$');
                expect(obj.newCurrencyCodeName).toEqual('USD');
                expect(obj.initialCurrencyCodeName).toEqual('EUR');
                expect(obj.oldCreditLimit).toEqual('100,00');
            });

            it('Check if resetCreditLimit has been called', function () {
                obj.currencyCode().initialValue = 'EUR';
                spyOn(obj, 'resetCreditLimit');
                obj.openModal('EUR');
                expect(obj.resetCreditLimit).toHaveBeenCalled();
            });
        });

        describe('"setCreditLimit" method', function () {
            it('Check for defined', function () {
                expect(obj.setCreditLimit).toBeDefined();
                expect(obj.setCreditLimit).toEqual(jasmine.any(Function));
            });

            it('Check update of source and currency code', function () {
                obj.currencyRate().disable = jasmine.createSpy();
                obj.setCreditLimit();
                expect(obj.valid).toEqual(true);
                expect(obj.source.set).toHaveBeenCalled();
                expect(obj.creditLimit().addbefore).toHaveBeenCalled();
            });

            it('Check if closeModal has been called', function () {
                spyOn(obj, 'closeModal');
                obj.setCreditLimit();
                expect(obj.closeModal).toHaveBeenCalled();
            });
        });

        describe('"resetCreditLimit" method', function () {
            it('Check for defined', function () {
                expect(obj.resetCreditLimit).toBeDefined();
                expect(obj.resetCreditLimit).toEqual(jasmine.any(Function));
            });

            it('Check update of source', function () {
                obj.resetCreditLimit();
                expect(obj.source.set).toHaveBeenCalled();
            });

            it('Check if currency values have been reset', function () {
                obj.resetCreditLimit();
                expect(obj.creditLimit().addbefore).toHaveBeenCalled();
                expect(obj.prevCurrencyCodeName).toEqual('RUB');
            });
        });
    });
});
