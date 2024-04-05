/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_SharedCatalog/js/wizard/step/structure'
], function (Step) {
    'use strict';

    describe('SharedCatalog/js/step/structure', function () {
        var step;

        beforeEach(function () {
            step = new Step({
                modules: {
                    provider: jasmine.createSpy(),
                    treeProvider: jasmine.createSpy()
                }
            });
        });

        describe('reloadAll method', function () {
            it('Check method call', function () {
                spyOn(step, 'reloadAll');
                step.reloadAll();
                expect(step.reloadAll).toHaveBeenCalled();
            });

            it('Check if _reloadProductListing has been called', function () {
                var returnData = {
                    '_reloadCategoryTree': jasmine.createSpy()
                };

                spyOn(step, '_reloadProductListing').and.returnValue(returnData);
                step.reloadAll();
                expect(step._reloadProductListing).toHaveBeenCalled();
            });

            it('Check if _reloadCategoryTree has been called', function () {
                spyOn(step, '_reloadCategoryTree');
                step.reloadAll();
                expect(step._reloadCategoryTree).toHaveBeenCalled();
            });
        });

        describe('_reloadProductListing method', function () {
            it('Check for defined ', function () {
                expect(step.hasOwnProperty('provider')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof step.provider;

                expect(type).toEqual('function');
            });

            it('Check if provider has been called with params', function () {
                spyOn(step, 'provider');
                step._reloadProductListing();
                expect(step.provider).toHaveBeenCalledWith('reload', {
                    refresh: true
                });
            });
        });

        describe('_reloadCategoryTree method', function () {
            it('Check for defined ', function () {
                expect(step.hasOwnProperty('treeProvider')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof step.treeProvider;

                expect(type).toEqual('function');
            });

            it('Check if treeProvider has been called with params', function () {
                spyOn(step, 'treeProvider');
                step._reloadCategoryTree();
                expect(step.treeProvider).toHaveBeenCalledWith('reload', {
                    refresh: true
                });
            });
        });

        describe('render method', function () {
            it('Check for defined', function () {
                expect(step.wizard).toBeUndefined();
                step.render(true);
                expect(step.wizard).toBeDefined();
            });
        });
    });
});
