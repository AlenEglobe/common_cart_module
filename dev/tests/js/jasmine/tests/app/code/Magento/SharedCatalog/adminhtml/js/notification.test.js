/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'ko',
    'jquery',
    'squire'
], function (ko, $, Squire) {
    'use strict';

    var injector = new Squire(),
        modalStub = {
            openModal: jasmine.createSpy(),
            closeModal: jasmine.createSpy()
        },
        mocks = {
            'Magento_Ui/js/modal/confirm': jasmine.createSpy('confirm').and.returnValue(modalStub)
        },
        notification, event, templateData;

    describe('Magento_SharedCatalog/js/notification', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_SharedCatalog/js/notification'], function (Constr) {
                notification = new Constr({
                    provider: 'provName',
                    isNotificationEnabled: false,
                    fieldName: 'isChanged',
                    sourceStructure: {
                        _rowIndex: 0,
                        'attribute_set_id': '1'
                    },
                    sourcePricing: {
                        _rowIndex: 0,
                        'attribute_set_id': '1',
                        isChanged: true
                    }
                });
                done();
            });

            event = {
                /** Stub */
                stopImmediatePropagation: function () {},

                /** Stub */
                preventDefault: function () {}
            };
            templateData = {
                message: 'message'
            };
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(notification.hasOwnProperty('initialize')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof notification.initialize;

                expect(type).toEqual('function');
            });
        });

        describe('setModal method', function () {
            it('Check method call', function () {
                spyOn(notification, 'setModal');
                notification.setModal();
                expect(notification.setModal).toHaveBeenCalled();
            });

            it('Check if options for popup have been set', function () {
                notification.setModal();
                expect(notification.popupOptions).toBeDefined();
            });
        });

        describe('showModal method', function () {
            it('Check method call', function () {
                spyOn(notification, 'showModal');
                notification.showModal();
                expect(notification.showModal).toHaveBeenCalled();
            });

            it('Check if popup has been opened', function () {
                notification.isNotificationEnabled = true;
                notification.showModal(event);
                expect(mocks['Magento_Ui/js/modal/confirm']).toHaveBeenCalled();
            });
        });

        describe('_getConfirmationContent method', function () {
            it('Check method call', function () {
                spyOn(notification, '_getConfirmationContent');
                notification._getConfirmationContent(templateData);
                expect(notification._getConfirmationContent).toHaveBeenCalled();
            });

            it('Check if template for popup has been rendered', function () {
                expect(notification._getConfirmationContent(templateData)).toBe('<p>message</p>');
            });
        });

        describe('_onStructureDataChanged method', function () {
            it('Check method call', function () {
                spyOn(notification, '_onStructureDataChanged');
                notification._onStructureDataChanged();
                expect(notification._onStructureDataChanged).toHaveBeenCalled();
            });

            it('Check if isNotificationEnabled has been changed', function () {
                var source = {
                    _rowIndex: 0,
                    'attribute_set_id': '2'
                };

                notification._onStructureDataChanged(source);
                expect(notification.isNotificationEnabled).toBe(true);
            });
        });

        describe('_onPricingDataChanged method', function () {
            it('Check method call', function () {
                spyOn(notification, '_onPricingDataChanged');
                notification._onPricingDataChanged();
                expect(notification._onPricingDataChanged).toHaveBeenCalled();
            });

            it('Check if notification is enabled after page reloading', function () {
                var source = {
                    _rowIndex: 0,
                    'attribute_set_id': '1',
                    isChanged: true
                };

                notification._onPricingDataChanged(source);
                expect(notification.isNotificationEnabled).toBe(true);
            });

            it('Check if notification is enabled without page reloading', function () {
                var source = {
                    _rowIndex: 0,
                    'attribute_set_id': '1',
                    isChanged: false
                };

                notification._onPricingDataChanged(source);
                expect(notification.isNotificationEnabled).toBe(true);
            });
        });
    });
});
