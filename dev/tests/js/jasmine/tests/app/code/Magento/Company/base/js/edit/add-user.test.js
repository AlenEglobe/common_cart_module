/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire'
], function (Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_Ui/js/lib/spinner': {
                get: jasmine.createSpy().and.returnValue({
                    show: jasmine.createSpy(),
                    hide: jasmine.createSpy()
                })
            }
        },
        params,
        addUserObj;

    describe('Company/js/edit/add-user', function () {
        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_Company/js/edit/add-user'], function (AddUser) {
                params = {
                    companyId: '',
                    companyAdmin: {},
                    dataScope: ''
                };
                addUserObj = new AddUser(params);
                done();
            });
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.initialize).toBeDefined();
                expect(addUserObj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check setCompanyId method call', function () {
                spyOn(addUserObj, 'setCompanyId');
                addUserObj.initialize(params);
                expect(addUserObj.setCompanyId).toHaveBeenCalled();
            });
        });

        describe('"setCompanyId" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.setCompanyId).toBeDefined();
                expect(addUserObj.setCompanyId).toEqual(jasmine.any(Function));
            });

            it('Check setting of right Company ID', function () {
                addUserObj.source = {
                    get: jasmine.createSpy().and.returnValue('123')
                };
                addUserObj.setCompanyId();
                expect(addUserObj.companyId).toEqual('123');
            });
        });

        describe('"userChanges" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.userChanges).toBeDefined();
                expect(addUserObj.userChanges).toEqual(jasmine.any(Function));
            });

            it('Check changed value', function () {
                var event = {
                    target: {
                        value: '1'
                    }
                },
                    customerDataLoaded = false;

                /** Stub */
                addUserObj.getCustomerData = function () {
                    customerDataLoaded = true;
                };

                /** Stub */
                addUserObj.isValid = function () {
                    return false;
                };

                spyOn(addUserObj, 'setInitialValue');

                addUserObj.userChanges(null, event);
                expect(addUserObj.value()).toEqual('1');
                expect(mocks['Magento_Ui/js/lib/spinner'].get).not.toHaveBeenCalled();
                expect(customerDataLoaded).toEqual(false);
                expect(addUserObj.setInitialValue).not.toHaveBeenCalled();

                /** Stub */
                addUserObj.isValid = function () {
                    return true;
                };

                addUserObj.userChanges(null, event);
                expect(mocks['Magento_Ui/js/lib/spinner'].get).toHaveBeenCalled();
                expect(customerDataLoaded).toEqual(true);
            });
        });

        describe('"getCustomerData" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.getCustomerData).toBeDefined();
                expect(addUserObj.getCustomerData).toEqual(jasmine.any(Function));
            });
        });

        describe('"onGetCustomerData" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.onGetCustomerData).toBeDefined();
                expect(addUserObj.onGetCustomerData).toEqual(jasmine.any(Function));
            });

            it('Check updateSource method call', function () {
                addUserObj.modalProvider = jasmine.createSpy().and.returnValue({
                    openModal: jasmine.createSpy()
                });

                spyOn(addUserObj, 'updateSource');
                addUserObj.onGetCustomerData({});
                expect(addUserObj.updateSource).toHaveBeenCalled();
                expect(addUserObj.modalProvider().openModal).not.toHaveBeenCalled();
                addUserObj.companyId = '123';
                addUserObj.onGetCustomerData({});
                expect(addUserObj.modalProvider().openModal).toHaveBeenCalled();
            });
        });

        describe('"clearValidationParams" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.clearValidationParams).toBeDefined();
                expect(addUserObj.clearValidationParams).toEqual(jasmine.any(Function));
            });

            it('Check clear params', function () {
                addUserObj.validationParams = {
                    required: true
                };
                addUserObj.clearValidationParams();
                expect(addUserObj.validationParams).toEqual({});
            });
        });

        describe('"updateSource" method', function () {
            it('Check for defined', function () {
                expect(addUserObj.updateSource).toBeDefined();
                expect(addUserObj.updateSource).toEqual(jasmine.any(Function));
            });

            it('Check update of source', function () {
                addUserObj.source = {
                    set: jasmine.createSpy(),
                    get: jasmine.createSpy()
                };
                addUserObj.prevCustomerId = 1;
                addUserObj.updateSource();
                expect(addUserObj.source.get).toHaveBeenCalledWith('data.company_admin.website_id');
                expect(addUserObj.source.set).toHaveBeenCalled();
            });
        });
    });
});
