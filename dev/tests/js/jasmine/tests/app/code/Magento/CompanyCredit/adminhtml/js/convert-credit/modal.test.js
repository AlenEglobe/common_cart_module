/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire',
    'uiCollection'
], function (Squire, Collection) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_Ui/js/modal/modal-component': Collection,
            'Magento_CompanyCredit/js/grid/massaction/selections-converter': {
                convert: jasmine.createSpy().and.returnValue({
                    selected: [1]
                })
            },
            'mage/utils/main': {
                submit: jasmine.createSpy().and.returnValue({})
            }
        },
        obj,
        component,
        data;

    describe('Magento_CompanyCredit/js/convert-credit/modal', function () {
        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_CompanyCredit/js/convert-credit/modal'], function (Modal) {
                obj = new Modal({
                    modules: {
                        currencyCode: jasmine.createSpy().and.returnValue({}),
                        ratesFields: jasmine.createSpy().and.returnValue({})
                    },
                    validate: jasmine.createSpy(),
                    massConvertUrl: 'url'
                });
                obj._super = jasmine.createSpy();
                done();
            });
            component = {
                component: 'uiComponent',
                label: 'Convert Credit'
            };
            data = {
                excludeMode: false,
                selected: [1]
            };
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"openModal" method', function () {
            it('Check for defined', function () {
                expect(obj.openModal).toBeDefined();
                expect(obj.openModal).toEqual(jasmine.any(Function));
            });

            it('Check if actionSelections has been called', function () {
                spyOn(obj, 'actionSelections');
                obj.openModal(component, data);
                expect(mocks['Magento_CompanyCredit/js/grid/massaction/selections-converter'].convert)
                    .toHaveBeenCalledWith(data);
                expect(obj.actionSelections).toHaveBeenCalledWith({
                    selected: [1]
                });
            });
        });

        describe('"convertCredit" method', function () {
            it('Check for defined', function () {
                expect(obj.convertCredit).toBeDefined();
                expect(obj.convertCredit).toEqual(jasmine.any(Function));
            });

            it('Check if mass update converting was started', function () {
                obj.currencyCode().value = jasmine.createSpy().and.returnValue('EUR');
                obj.currencyCode().reset = jasmine.createSpy();
                obj.ratesFields().getUpdatedRates = jasmine.createSpy().and.returnValue({
                    'USD': '1.1300'
                });
                obj.ratesFields().rates = jasmine.createSpy();
                obj.convertCredit();
                expect(mocks['mage/utils/main'].submit).toHaveBeenCalledWith({
                    url: 'url',
                    data: {
                        'currency_to': 'EUR',
                        'currency_rates': {
                            'USD': '1.1300'
                        }
                    }
                });
            });

            it('Check if actionSelections has been called', function () {
                spyOn(obj, 'actionSelections');
                obj.convertCredit();
                expect(obj.actionSelections).toHaveBeenCalled();
            });

            it('Check if closeModal has been called', function () {
                spyOn(obj, 'closeModal');
                obj.convertCredit();
                expect(obj.closeModal).toHaveBeenCalled();
            });
        });
    });
});
