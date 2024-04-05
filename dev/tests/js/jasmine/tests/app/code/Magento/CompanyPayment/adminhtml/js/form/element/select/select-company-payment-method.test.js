/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'underscore',
    'ko',
    'Magento_CompanyPayment/js/form/element/select/select-company-payment-method'
], function (_, ko, SelectMethod) {
    'use strict';

    describe('CompanyPayment/js/form/element/select/select-company-payment-method', function () {
        var optionsMocks,
            moduleStub,
            params,
            selectMethodObj;

        optionsMocks = [
            {
                value: 'Method 1'
            },
            {
                value: 'Method 2'
            }
        ];

        beforeEach(function () {
            moduleStub = jasmine.createSpyObj(['disable', 'enable']);
            params = {
                dataScope: '',
                paymentsField: jasmine.createSpy().and.returnValue(_.extend(moduleStub, {
                    value: ko.observableArray([]),
                    disabled: ko.observable(true),
                    initialOptions: optionsMocks
                })),
                applicablePaymentMethods: {
                    b2b: 'b2b',
                    allEnabled: 'allEnabled'
                },
                b2bPaymentMethods: 'Method 1,Method 2',
                value: ko.observable(''),
                disabled: ko.observable(true)
            };
            selectMethodObj = new SelectMethod(params);

            jasmine.clock().install();
        });

        afterEach(function () {
            jasmine.clock().uninstall();
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj.initialize).toBeDefined();
                expect(selectMethodObj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check _selectOptions method call with defer', function () {
                spyOn(selectMethodObj, '_selectOptions');
                selectMethodObj.initialize(params);
                jasmine.clock().tick(1);
                expect(selectMethodObj._selectOptions).toHaveBeenCalled();
            });
        });

        describe('"onUpdate" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj.onUpdate).toBeDefined();
                expect(selectMethodObj.onUpdate).toEqual(jasmine.any(Function));
            });

            it('Check _selectOptions method call into onUpdate', function () {
                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );

                selectMethodObj._selectOptions = jasmine.createSpy().and.callFake(function () {});
                selectMethodObj.onUpdate();
                expect(selectMethodObj._selectOptions).toHaveBeenCalled();
            });
        });

        describe('"_disablePaymentMethodsField" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj._disablePaymentMethodsField).toBeDefined();
                expect(selectMethodObj._disablePaymentMethodsField).toEqual(jasmine.any(Function));
            });

            it('Check of disabling fields', function () {
                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );
                selectMethodObj._disablePaymentMethodsField();
                expect(selectMethodObj.paymentsField().disable).not.toHaveBeenCalled();
                selectMethodObj.paymentsField().disabled(false);
                selectMethodObj._disablePaymentMethodsField();
                expect(selectMethodObj.paymentsField().disable).toHaveBeenCalled();
            });
        });

        describe('"_getInitialOptions" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj._getInitialOptions).toBeDefined();
                expect(selectMethodObj._getInitialOptions).toEqual(jasmine.any(Function));
            });

            it('Check of initial methods', function () {
                var options = optionsMocks.map(function (item) {
                    return item.value;
                });

                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );

                expect(selectMethodObj._getInitialOptions()).toEqual(options);
            });
        });

        describe('"_getSelectedPaymentMethods" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj._getSelectedPaymentMethods).toBeDefined();
                expect(selectMethodObj._getSelectedPaymentMethods).toEqual(jasmine.any(Function));
            });

            it('Check of selected methods', function () {
                var options = optionsMocks.map(function (item) {
                    return item.value;
                });

                expect(selectMethodObj._getSelectedPaymentMethods()).toEqual(options);
            });
        });

        describe('"_selectOptions" method', function () {
            it('Check for defined', function () {
                expect(selectMethodObj._selectOptions).toBeDefined();
                expect(selectMethodObj._selectOptions).toEqual(jasmine.any(Function));
            });

            it('Check of enabling fields', function () {
                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );

                selectMethodObj._selectOptions();
                expect(selectMethodObj.paymentsField().enable).not.toHaveBeenCalled();
                selectMethodObj.disabled(false);
                selectMethodObj._selectOptions();
                expect(selectMethodObj.paymentsField().enable).toHaveBeenCalled();
            });

            it('Check _getSelectedPaymentMethods method call', function () {
                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );

                selectMethodObj._getSelectedPaymentMethods = jasmine.createSpy().and.callFake(function () {});
                selectMethodObj.value('b2b');
                selectMethodObj._selectOptions();
                expect(selectMethodObj._getSelectedPaymentMethods).toHaveBeenCalled();
            });

            it('Check _getInitialOptions method call', function () {
                selectMethodObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observableArray([]),
                        disabled: ko.observable(true),
                        initialOptions: optionsMocks
                    })
                );
                selectMethodObj._getInitialOptions = jasmine.createSpy().and.callFake(function () {});
                selectMethodObj.value('allEnabled');
                selectMethodObj._selectOptions();
                expect(selectMethodObj._getInitialOptions).toHaveBeenCalled();
            });
        });
    });
});
