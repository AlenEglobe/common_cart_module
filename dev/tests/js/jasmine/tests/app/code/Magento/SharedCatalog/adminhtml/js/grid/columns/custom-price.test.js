/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire',
    'uiElement'
], function (Squire, Element) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_Ui/js/grid/columns/column': Element,
            'Magento_SharedCatalog/js/utils/validator/event_key': {
                isDigits: jasmine.createSpy().and.returnValue(true)
            }
        },
        obj,
        record,
        input,
        e;

    describe('Magento_SharedCatalog/js/grid/columns/custom-price', function () {
        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_SharedCatalog/js/grid/columns/custom-price'], function (CustomPrice) {
                obj = new CustomPrice({
                    indexField: 'entity_id',
                    priceTypeName: 'priceType',
                    originPriceName: 'originPrice',
                    source: jasmine.createSpy().and.returnValue({
                        set: jasmine.createSpy(),
                        get: jasmine.createSpy().and.returnValue(2)
                    })
                });
                obj.priceStorage = jasmine.createSpy().and.returnValue({
                    setCustomPrice: jasmine.createSpy()
                });
                done();
            });
            record = {
                '_rowIndex': 1,
                'entity_id': 3,
                'priceType': 'fixed',
                'originPrice': '25'
            };
            input = {
                value: '20'
            };
            e = {
                key: '%'
            };
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (ex) {}
        });

        describe('"onChangePrice" method', function () {
            it('Check for defined', function () {
                expect(obj.onChangePrice).toBeDefined();
                expect(obj.onChangePrice).toEqual(jasmine.any(Function));
            });

            it('Check if setCustomPrice has been called', function () {
                spyOn(obj, 'setCustomPrice');
                obj.onChangePrice(record, input);
                expect(obj.setCustomPrice).toHaveBeenCalledWith(1, '20');
                expect(obj.priceStorage().setCustomPrice).toHaveBeenCalledWith({
                    'product_id': 3,
                    'custom_price': '20',
                    'price_type': 'fixed',
                    'website_id': 2
                });
            });

            it('Check if setCustomPrice has not been called', function () {
                record._rowIndex = -1;
                spyOn(obj, 'setCustomPrice');
                obj.onChangePrice(record, input);
                expect(obj.setCustomPrice).not.toHaveBeenCalled();
            });

            it('Check if _preparePrice has been called', function () {
                spyOn(obj, '_preparePrice');
                obj.onChangePrice(record, input);
                expect(obj._preparePrice).toHaveBeenCalledWith(3, '20', 'fixed', 2);
            });
        });

        describe('"onPriceInputKeyDown" method', function () {
            it('Check for defined', function () {
                expect(obj.onPriceInputKeyDown).toBeDefined();
                expect(obj.onPriceInputKeyDown).toEqual(jasmine.any(Function));
            });

            it('Check if recalculatePriceByDiscount has been called', function () {
                spyOn(obj, 'recalculatePriceByDiscount');
                obj.onPriceInputKeyDown(record, input, e);
                expect(obj.recalculatePriceByDiscount).toHaveBeenCalledWith(record, input);
                expect(obj.onPriceInputKeyDown(record, input, e)).toBeFalsy();
            });

            it('Check if isPriceInputEventKeyValid has been called', function () {
                e.key = '#';
                spyOn(obj, 'isPriceInputEventKeyValid');
                obj.onPriceInputKeyDown(record, input, e);
                expect(obj.isPriceInputEventKeyValid).toHaveBeenCalledWith(e);
            });
        });

        describe('"isPriceInputEventKeyValid" method', function () {
            it('Check for defined', function () {
                expect(obj.isPriceInputEventKeyValid).toBeDefined();
                expect(obj.isPriceInputEventKeyValid).toEqual(jasmine.any(Function));
            });

            it('Check if EventValidator has been called', function () {
                expect(obj.isPriceInputEventKeyValid(e)).toBeTruthy();
            });
        });

        describe('"recalculatePriceByDiscount" method', function () {
            it('Check for defined', function () {
                expect(obj.recalculatePriceByDiscount).toBeDefined();
                expect(obj.recalculatePriceByDiscount).toEqual(jasmine.any(Function));
            });

            it('Check if setCustomPrice has been called', function () {
                spyOn(obj, 'setCustomPrice');
                obj.recalculatePriceByDiscount(record, input);
                expect(obj.setCustomPrice).toHaveBeenCalledWith(1, '20');
            });

            it('Check if setCustomPrice has not been called', function () {
                record._rowIndex = -1;
                spyOn(obj, 'setCustomPrice');
                obj.recalculatePriceByDiscount(record, input);
                expect(obj.setCustomPrice).not.toHaveBeenCalled();
            });

            it('Check if onChangePrice has been called', function () {
                spyOn(obj, 'onChangePrice');
                obj.recalculatePriceByDiscount(record, input);
                expect(obj.onChangePrice).toHaveBeenCalledWith(record, input);
            });
        });
    });
});
