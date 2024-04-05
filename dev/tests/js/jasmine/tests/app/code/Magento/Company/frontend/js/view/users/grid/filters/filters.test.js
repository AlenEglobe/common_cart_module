/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_Company/js/users/grid/filters/filters'
], function (Filter) {
    'use strict';

    describe('Magento_Company/js/users/grid/filters/filters', function () {
        var filterObj,
            temp;

        beforeEach(function () {
            filterObj = new Filter({
                name: 'filter'
            });
        });

        it('Check "apply" method', function () {
            temp = filterObj.apply();
            expect(temp).toBeDefined();
            expect(temp.get('applied')).toBeDefined();
        });
    });
});
