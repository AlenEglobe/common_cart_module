/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_Company/js/form/actions/auto-complete'
], function (AutoComplete) {
    'use strict';

    describe('Magento_Company/js/form/actions/auto-complete', function () {
        var obj, items;

        beforeEach(function () {
            obj = new AutoComplete();
            items = [{
                group: '1',
                id: '1',
                name: 'Company',
                country: 'US',
                region: 'California'
            }];
        });

        describe('"prepareItems" method', function () {
            it('Check for defined', function () {
                expect(obj.prepareItems).toBeDefined();
                expect(obj.prepareItems).toEqual(jasmine.any(Function));
            });

            it('Check if additional field has been added', function () {
                expect(obj.prepareItems(items)).toEqual([{
                    group: '1',
                    id: '1',
                    name: 'Company',
                    country: 'US',
                    region: 'California',
                    address: 'US, California'
                }]);
            });

            it('Check if renderAddress has been called', function () {
                spyOn(obj, 'renderAddress');
                obj.prepareItems(items);
                expect(obj.renderAddress).toHaveBeenCalled();
            });
        });

        describe('"renderAddress" method', function () {
            it('Check for defined', function () {
                expect(obj.renderAddress).toBeDefined();
                expect(obj.renderAddress).toEqual(jasmine.any(Function));
            });

            it('Check if additional field has been added', function () {
                var company = {
                    group: '1',
                    id: '1',
                    name: 'Company',
                    country: 'US',
                    region: 'California'
                };

                expect(obj.renderAddress(company)).toEqual('US, California');
            });
        });
    });
});
