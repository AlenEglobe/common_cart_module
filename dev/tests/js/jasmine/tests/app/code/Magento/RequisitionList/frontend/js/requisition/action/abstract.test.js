/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'squire'
], function ($, Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'mage/dataPost': jasmine.createSpy('dataPost').and.returnValue({
                /** @inheritdoc */
                postData: function (arg) {
                    return arg;
                }
            })
        },
        RequisitionComponent;

    describe('Magento_RequisitionList/js/requisition/action/abstract', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_RequisitionList/js/requisition/action/abstract'], function (Constr) {
                RequisitionComponent = new Constr({
                    title: 'title',
                    action: 'action',
                    editModule: '',
                    'action_data': {}
                });
                done();
            });
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"getTitle" method', function () {
            it('Check getTitle method return correct params', function () {
                expect(RequisitionComponent.getTitle()).toBe('title');
                RequisitionComponent.title = 'newTitle';
                expect(RequisitionComponent.getTitle()).toBe('newTitle');
            });
        });

        describe('"getMobileLabel" method', function () {
            it('Check getMobileLabel method return correct params', function () {
                RequisitionComponent.mobileLabel = false;
                expect(RequisitionComponent.getMobileLabel()).toBe('title');
                RequisitionComponent.mobileLabel = 'mobileTitle';
                expect(RequisitionComponent.getMobileLabel()).toBe('mobileTitle');
            });
        });

        describe('"isListVisible" method', function () {
            it('Check isListVisible method return true', function () {
                expect(RequisitionComponent.isListVisible()).toBeTruthy();
            });
        });

        describe('"performListAction" method', function () {
            it('Check if valid is false', function () {
                spyOn(RequisitionComponent, '_isActionValid').and.returnValue(false);
                spyOn(RequisitionComponent, '_getActionData');
                RequisitionComponent.performListAction();
                expect(RequisitionComponent._getActionData).not.toHaveBeenCalled();
            });

            it('Check if valid is true', function () {
                spyOn(RequisitionComponent, '_isActionValid').and.returnValue(true);
                spyOn(RequisitionComponent, '_getActionData');
                RequisitionComponent.performListAction();
                expect(RequisitionComponent._getActionData).toHaveBeenCalled();
            });
        });

        describe('"performNewListAction" method', function () {
            it('Check performNewListAction method return correct params', function () {
                spyOn(RequisitionComponent, '_createList').and.returnValue({
                    /** @inheritdoc */
                    then: function () {
                        return true;
                    }
                });
                expect(RequisitionComponent.performNewListAction()).toBeTruthy();
            });
        });

        describe('"_createList" method', function () {
            it('Check _createList method return correct params', function () {
                spyOn(RequisitionComponent, 'editModule').and.returnValue({
                    /** @inheritdoc */
                    edit: function (arg) {
                        return arg;
                    }
                });
                expect(RequisitionComponent._createList()).toEqual({});
            });
        });

        describe('"_isActionValid" method', function () {
            it('Check _isActionValid method return true', function () {
                expect(RequisitionComponent._isActionValid()).toBeTruthy();
            });
        });

        describe('"_getActionData" method', function () {
            it('Check _getActionData method return correct params', function () {
                expect(RequisitionComponent._getActionData({
                    id: 1,
                    name: 'Requisition List Name'
                })).toEqual({
                    'list_id': 1,
                    'list_name': 'Requisition List Name'
                });
            });
        });
    });
});
