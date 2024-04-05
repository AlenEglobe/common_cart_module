/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'underscore',
    'ko',
    'Magento_CompanyPayment/js/form/element/use-config-settings'
], function (_, ko, Settings) {
    'use strict';

    describe('CompanyPayment/js/form/element/use-config-settings', function () {
        var moduleStub,
            params,
            settingsObj;

        beforeEach(function () {
            moduleStub = jasmine.createSpyObj(['disable', 'enable']);
            params = {
                dataScope: '',
                applicablePaymentsField: jasmine.createSpy().and.returnValue(_.extend(moduleStub, {
                    value: ko.observable('')
                })),
                paymentsField: jasmine.createSpy().and.returnValue(_.extend(moduleStub, {
                    value: ko.observable('')
                })),
                applicablePaymentMethods: {
                    allEnabled: '',
                    specific: ''
                }
            };
            settingsObj = new Settings(params);

            jasmine.clock().install();
        });

        afterEach(function () {
            jasmine.clock().uninstall();
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(settingsObj.initialize).toBeDefined();
                expect(settingsObj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check _checkStatus method call with defer', function () {
                spyOn(settingsObj, '_checkStatus');
                settingsObj.initialize(params);
                jasmine.clock().tick(1);
                expect(settingsObj._checkStatus).toHaveBeenCalled();
            });
        });

        describe('"_checkStatus" method', function () {
            it('Check for defined', function () {
                expect(settingsObj._checkStatus).toBeDefined();
                expect(settingsObj._checkStatus).toEqual(jasmine.any(Function));
            });

            it('Check disableDependencies method call into _checkStatus', function () {
                spyOn(settingsObj, 'disableDependencies');
                settingsObj._checkStatus();
                expect(settingsObj.disableDependencies).not.toHaveBeenCalled();
                settingsObj.value('1');
                settingsObj._checkStatus();
                expect(settingsObj.disableDependencies).toHaveBeenCalled();
            });
        });

        describe('"disableDependencies" method', function () {
            it('Check for defined', function () {
                expect(settingsObj.disableDependencies).toBeDefined();
                expect(settingsObj.disableDependencies).toEqual(jasmine.any(Function));
            });

            it('Check of disabling fields', function () {
                settingsObj.applicablePaymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observable('')
                    })
                );
                settingsObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observable('')
                    })
                );
                settingsObj.disableDependencies();
                expect(settingsObj.applicablePaymentsField().disable).toHaveBeenCalled();
                expect(settingsObj.paymentsField().disable).toHaveBeenCalled();
            });
        });

        describe('"onCheckedChanged" method', function () {
            it('Check for defined', function () {
                expect(settingsObj.onCheckedChanged).toBeDefined();
                expect(settingsObj.onCheckedChanged).toEqual(jasmine.any(Function));
            });

            it('Check of enabling fields', function () {
                settingsObj.applicablePaymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observable('')
                    })
                );
                settingsObj.paymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observable('')
                    })
                );

                settingsObj.onCheckedChanged(false);
                expect(settingsObj.applicablePaymentsField().enable).toHaveBeenCalled();
                expect(settingsObj.paymentsField().enable).toHaveBeenCalled();
            });

            it('Check disableDependencies method call into onCheckedChanged', function () {
                settingsObj.applicablePaymentsField = jasmine.createSpy().and.returnValue(
                    _.extend(moduleStub, {
                        value: ko.observable('')
                    })
                );
                settingsObj.disableDependencies = jasmine.createSpy().and.callFake(function () {});

                settingsObj.onCheckedChanged(true);
                expect(settingsObj.disableDependencies).toHaveBeenCalled();
            });
        });
    });
});
