/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_B2b/js/grid/paging/paging'
], function (Paging) {
    'use strict';

    describe('Magento_B2b/js/grid/paging/paging', function () {
        var paging;

        beforeEach(function () {
            paging = new Paging();
            paging.pageSize = 3;
        });

        describe('getFirstNum method', function () {
            it('Check method call', function () {
                spyOn(paging, 'getFirstNum');
                paging.getFirstNum();
                expect(paging.getFirstNum).toHaveBeenCalled();
            });

            it('Check a number of the first row on a page', function () {
                paging.current = 2;
                paging.getFirstNum();
                expect(paging.getFirstNum()).toBe(4);
            });
        });

        describe('getLastNum method', function () {
            it('Check method call', function () {
                spyOn(paging, 'getLastNum');
                paging.getLastNum();
                expect(paging.getLastNum).toHaveBeenCalled();
            });

            it('Check a number of the last row on a page', function () {
                paging.current = 2;
                paging.totalRecords = 7;

                /** Stub */
                paging.isLast = function () {
                    return false;
                };

                paging.getLastNum();
                expect(paging.getLastNum()).toBe(6);
            });

            it('Check a number of the last row on the last page', function () {
                paging.current = 2;
                paging.totalRecords = 5;

                /** Stub */
                paging.isLast = function () {
                    return true;
                };

                paging.getLastNum();
                expect(paging.getLastNum()).toBe(5);
            });
        });

        describe('getPages method', function () {
            it('Check method call', function () {
                spyOn(paging, 'getPages');
                paging.getPages();
                expect(paging.getPages).toHaveBeenCalled();
            });

            it('Check an amount of pages', function () {
                paging.pages = 2;
                paging.getPages();
                expect(paging.getPages()).toEqual([1, 2]);
            });
        });
    });
});
