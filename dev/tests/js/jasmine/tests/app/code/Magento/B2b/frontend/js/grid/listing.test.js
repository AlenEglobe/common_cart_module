/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire',
    'uiCollection'
], function (Squire, Collection) {
    'use strict';

    describe('Magento_B2b/js/grid/listing', function () {
        var injector = new Squire(),
            mocks = {
                'Magento_Ui/js/grid/listing': Collection
            },
            listing;

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_B2b/js/grid/listing'], function (Constr) {
                listing = new Constr({
                    'table_css_class': 'default'
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

        describe('getTableClass method', function () {
            it('Check method call', function () {
                spyOn(listing, 'getTableClass');
                listing.getTableClass();
                expect(listing.getTableClass).toHaveBeenCalled();
            });

            it('Check if class name has been set', function () {
                expect(listing.getTableClass()).toBe('default');
                listing['table_css_class'] = 'default-class';
                expect(listing.getTableClass()).toBe('default-class');
            });
        });
    });
});
