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
        mocks = {},
        element;

    describe('Magento_RequisitionList/js/requisition/action/items/massaction', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_RequisitionList/js/requisition/action/items/massaction'], function (Constr) {
                element = Constr({
                    currentListId: '1',
                    gridMassaction: {},
                    editModule: jasmine.createSpy().and.returnValue({
                        edit: jasmine.createSpy()
                    }),
                    _createList: jasmine.createSpy().and.returnValue({
                        then: jasmine.createSpy().and.returnValue(true)
                    })
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

        describe('"isListVisible" method', function () {
            it('Check method call', function () {
                spyOn(element, 'isListVisible');
                element.isListVisible();
                expect(element.isListVisible).toHaveBeenCalled();
            });

            it('Check if list is visible', function () {
                var list = {
                    id: '11'
                };

                expect(element.isListVisible(list)).toBe(true);
            });
        });

        describe('"performNewListAction" method', function () {
            it('Check method call', function () {
                spyOn(element, 'performNewListAction');
                element.performNewListAction();
                expect(element.performNewListAction).toHaveBeenCalled();
            });

            it('Check if _isActionValid has been called', function () {
                element.gridMassaction = jasmine.createSpy().and.returnValue({
                    applyAction: jasmine.createSpy().and.returnValue(true),
                    validate: jasmine.createSpy().and.returnValue(true)
                });

                expect(element._isActionValid()).toBe(true);
            });

            it('Check if list is visible', function () {
                element.gridMassaction = jasmine.createSpy().and.returnValue({
                    applyAction: jasmine.createSpy().and.returnValue(true),
                    validate: jasmine.createSpy().and.returnValue(true)
                });

                expect(element.performNewListAction()).toBe(true);
            });
        });

        describe('"performListAction" method', function () {
            it('Check method call', function () {
                spyOn(element, 'performListAction');
                element.performListAction();
                expect(element.performListAction).toHaveBeenCalled();
            });

            it('Check method call', function () {
                var list = {
                        id: '11'
                    };

                element.gridMassaction = jasmine.createSpy().and.returnValue({
                    applyAction: jasmine.createSpy().and.returnValue(true),
                    validate: jasmine.createSpy().and.returnValue(true)
                });
                element.performListAction(list);
                expect(element.gridMassaction().applyAction).toHaveBeenCalledWith({
                    'list_id': '11',
                    'source_list_id': '1'
                });
            });
        });
    });
});
