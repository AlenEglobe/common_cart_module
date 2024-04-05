/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_SharedCatalog/js/grid/columns/multiselect'
], function (Multiselect) {
    'use strict';

    describe('Magento_SharedCatalog/js/grid/columns/multiselect', function () {
        var multiselect, record;

        beforeEach(function () {
            multiselect = new Multiselect();

            record = {
                'price': '1.00'
            };
        });

        describe('getValue method', function () {
            it('Check method call', function () {
                spyOn(multiselect, 'getValue');
                multiselect.getValue();
                expect(multiselect.getValue).toHaveBeenCalled();
            });

            it('Check for returned value', function () {
                expect(multiselect.getValue(record, 'price')).toBe('1.00');
            });
        });
    });
});
