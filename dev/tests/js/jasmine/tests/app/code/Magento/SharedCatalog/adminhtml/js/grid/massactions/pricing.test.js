/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'squire'
], function ($, Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_Ui/js/modal/prompt': jasmine.createSpy()
        },
        params,
        pricingObj;

    describe('SharedCatalog/js/grid/massactions/pricing', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_SharedCatalog/js/grid/massactions/pricing'], function (Pricing) {
                params = {
                    client: jasmine.createSpy().and.returnValue({
                        save: jasmine.createSpy(),
                        done: jasmine.createSpy()
                    }),
                    columns: jasmine.createSpy(),
                    trigger: jasmine.createSpy()
                };
                pricingObj = new Pricing(params);
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
                expect(pricingObj.initialize).toBeDefined();
                expect(pricingObj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check initClients method call', function () {
                spyOn(pricingObj, 'initClients');
                pricingObj.initialize(params);
                expect(pricingObj.initClients).toHaveBeenCalled();
            });
        });

        describe('"initObservable" method', function () {
            it('Check for defined', function () {
                expect(pricingObj.initObservable).toBeDefined();
                expect(pricingObj.initObservable).toEqual(jasmine.any(Function));
            });
        });

        describe('"initClients" method', function () {
            it('Check for defined', function () {
                expect(pricingObj.initClients).toBeDefined();
                expect(pricingObj.initClients).toEqual(jasmine.any(Function));
            });
        });

        describe('"isEnterKey" method', function () {
            it('Check for defined', function () {
                expect(pricingObj.isEnterKey).toBeDefined();
                expect(pricingObj.isEnterKey).toEqual(jasmine.any(Function));
            });

            it('Check enter key', function () {
                expect(pricingObj.isEnterKey('altKey')).toBe(false);
                expect(pricingObj.isEnterKey('enterKey')).toBe(true);
            });
        });

        describe('"_confirm" method', function () {
            var action = {
                url: '',
                confirm: {
                    promptClass: 'modal'
                }
            };

            it('Check for defined', function () {
                expect(pricingObj._confirm).toBeDefined();
                expect(pricingObj._confirm).toEqual(jasmine.any(Function));
            });

            it('Check requestUrl', function () {
                pricingObj._confirm(action);
                expect(pricingObj.requestUrl()).toEqual(action.url);
            });

            it('Check if popup has been opened', function () {
                pricingObj._confirm(action);
                expect(mocks['Magento_Ui/js/modal/prompt']).toHaveBeenCalled();
            });

            it('Check _setValueValidation method call', function () {
                spyOn(pricingObj, '_setValueValidation');
                pricingObj._confirm(action);
                expect(pricingObj._setValueValidation).toHaveBeenCalledWith(action.confirm.promptClass);
            });
        });

        describe('"_setValueValidation" method', function () {
            it('Check for defined', function () {
                expect(pricingObj._setValueValidation).toBeDefined();
                expect(pricingObj._setValueValidation).toEqual(jasmine.any(Function));
            });
        });

        describe('"_sendRequest" method', function () {
            it('Check for defined', function () {
                expect(pricingObj._sendRequest).toBeDefined();
                expect(pricingObj._sendRequest).toEqual(jasmine.any(Function));
            });

            it('Check sending of ajax request', function () {
                spyOn(pricingObj.client(), 'save').and.callFake(function () {
                    var d = $.Deferred();

                    d.resolve({
                        'success': true
                    });

                    return d.promise();
                });
                spyOn(pricingObj, '_getRequestData');
                spyOn(pricingObj, '_onSaveDone');
                pricingObj._sendRequest();
                expect(pricingObj.client().save).toHaveBeenCalled();
                expect(pricingObj._getRequestData).toHaveBeenCalled();
                expect(pricingObj._onSaveDone).toHaveBeenCalled();
            });
        });

        describe('"_getRequestData" method', function () {
            it('Check for defined', function () {
                expect(pricingObj._getRequestData).toBeDefined();
                expect(pricingObj._getRequestData).toEqual(jasmine.any(Function));
            });

            it('Check of request data', function () {
                var selections = {
                    value: '',
                    selected: false,
                    'website_id': ''
                };

                pricingObj.source = {
                    get: jasmine.createSpy().and.returnValue('')
                };

                expect(pricingObj._getRequestData('', {
                    selected: ''
                })).toEqual(selections);
            });
        });

        describe('"_onSaveDone" method', function () {
            it('Check for defined', function () {
                expect(pricingObj._onSaveDone).toBeDefined();
                expect(pricingObj._onSaveDone).toEqual(jasmine.any(Function));
            });

            it('Check trigger method', function () {
                pricingObj._onSaveDone();
                expect(pricingObj.trigger).toHaveBeenCalled();
            });
        });
    });
});
