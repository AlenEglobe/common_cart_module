/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire',
    'jquery'
], function (Squire, $) {
    'use strict';

    var injector = new Squire(),
        action;

    describe('Magento_RequisitionList/js/requisition/action/product/add', function () {
        beforeEach(function (done) {
            injector.require(
                ['Magento_RequisitionList/js/requisition/action/product/add'],
                function (ProductAddAction) {
                    action = new ProductAddAction({
                        sku: 'product_sku'
                    });
                    done();
                }
            );
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"performNewListAction" method', function () {
            it('calls "_isActionValid" method', function () {
                spyOn(action, '_isActionValid').and.returnValue(false);

                action.performNewListAction();

                expect(action._isActionValid)
                    .toHaveBeenCalled();
            });

            it('calls parent method if action is valid', function () {
                spyOn(action, '_isActionValid').and.returnValue(true);
                spyOn(action, '_createList').and.returnValue($.Deferred().resolve().promise());
                spyOn(action, 'performListAction').and.returnValue($.Deferred().resolve().promise());

                action.performNewListAction().done(function () {
                        expect(action.performListAction)
                            .toHaveBeenCalled();
                        expect(action._createList)
                            .toHaveBeenCalled();
                    }
                );
            });
        });

        describe('"_getActionData" method', function () {
            it('calls "_getProductData"', function () {
                spyOn(action, '_getProductData');

                action._getActionData({
                    id: 7
                });

                expect(action._getProductData)
                    .toHaveBeenCalled();
            });

            it('returns actual data object', function () {
                var list = {
                        id: 7,
                        name: 'Requisition List Name'
                    },
                    expectedData = {
                        'list_id': list.id,
                        'list_name': list.name,
                        'product_data': '{"sku":"product_sku"}'
                    };

                expect(action._getActionData(list))
                    .toEqual(expectedData);
            });
        });

        describe('"_getProductData" method', function () {
            it('returns current product data', function () {
                expect(action._getProductData())
                    .toEqual({
                        sku: 'product_sku'
                    });
            });
        });
    });
});
