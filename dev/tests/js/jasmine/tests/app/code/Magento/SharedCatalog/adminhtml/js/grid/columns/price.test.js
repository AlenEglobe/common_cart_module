/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_SharedCatalog/js/grid/columns/price'
], function (Price) {
    'use strict';

    describe('Magento_SharedCatalog/js/grid/columns/price', function () {
        var price, record;

        beforeEach(function () {
            price = new Price({
                priceView: 'price_view',
                productType: 'type_id',
                specialProductTypes: {
                    bundle: 'Magento_BundleSharedCatalog/grid/cells/price/bundle',
                    configurable: 'Magento_ConfigurableSharedCatalog/grid/cells/price/configurable',
                    simple: 'Magento_SharedCatalog/grid/cells/price/simple'
                }
            });

            record = {
                'price': '1.00',
                'max_price': '10.00',
                'type_id': 'simple',
                'price_view': 0
            };
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(price.hasOwnProperty('initialize')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof price.initialize;

                expect(type).toEqual('function');
            });
        });

        describe('getValue method', function () {
            it('Check method call', function () {
                spyOn(price, 'getValue');
                price.getValue();
                expect(price.getValue).toHaveBeenCalled();
            });

            it('Check for returned value', function () {
                expect(price.getValue(record, 'price')).toBe('1.00');
            });
        });

        describe('getMaxValue method', function () {
            it('Check method call', function () {
                spyOn(price, 'getMaxValue');
                price.getMaxValue();
                expect(price.getMaxValue).toHaveBeenCalled();
            });

            it('Check for returned value', function () {
                expect(price.getMaxValue(record, 'price')).toBe('10.00');
            });
        });

        describe('hasPriceView method', function () {
            it('Check method call', function () {
                spyOn(price, 'hasPriceView');
                price.hasPriceView();
                expect(price.hasPriceView).toHaveBeenCalled();
            });

            it('Check if record price view is not set', function () {
                record['price_view'] = 1;
                expect(price.hasPriceView(record)).toBeTruthy();
            });

            it('Check if record price view is set', function () {
                expect(price.hasPriceView(record)).toBeFalsy();
            });
        });

        describe('hasPriceTemplate method', function () {
            it('Check method call', function () {
                spyOn(price, 'hasPriceTemplate');
                price.hasPriceTemplate();
                expect(price.hasPriceTemplate).toHaveBeenCalled();
            });

            it('Check if price template doesn\'t exist for a record', function () {
                record['type_id'] = 'virtual';
                expect(price.hasPriceTemplate(record)).toBeFalsy();
            });

            it('Check if price template exists for a record', function () {
                expect(price.hasPriceTemplate(record)).toBeTruthy();
            });
        });

        describe('getPriceTemplate method', function () {
            it('Check method call', function () {
                spyOn(price, 'getPriceTemplate');
                price.getPriceTemplate();
                expect(price.getPriceTemplate).toHaveBeenCalled();
            });

            it('Check if price template gets for a record', function () {
                expect(price.getPriceTemplate(record)).toBe('Magento_SharedCatalog/grid/cells/price/simple');
            });
        });
    });
});
