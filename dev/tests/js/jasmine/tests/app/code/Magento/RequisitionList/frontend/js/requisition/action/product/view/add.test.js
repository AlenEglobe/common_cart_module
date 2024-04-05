/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_RequisitionList/js/requisition/action/product/view/add'
], function ($, ProductAddComponent) {
    'use strict';

    describe('RequisitionList/js/requisition/action/product/add', function () {
        var productAddComponent;

        beforeEach(function () {
            productAddComponent = new ProductAddComponent({
                productFormSelector: '<form />'
            });
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(productAddComponent.initialize).toBeDefined();
                expect(productAddComponent.initialize).toEqual(jasmine.any(Function));
            });

            it('Check _validateProductForm method call if isConfigureMode is true', function () {
                spyOn(productAddComponent, 'isConfigureMode').and.returnValue(true);
                spyOn(productAddComponent, '_validateProductForm');
                productAddComponent.initialize();
                expect(productAddComponent._validateProductForm).toHaveBeenCalled();
            });

            it('Check _validateProductForm method do not call if isConfigureMode is false', function () {
                spyOn(productAddComponent, 'isConfigureMode').and.returnValue(false);
                spyOn(productAddComponent, '_validateProductForm');
                productAddComponent.initialize();
                expect(productAddComponent._validateProductForm).not.toHaveBeenCalled();
            });
        });

        describe('"_validateProductForm" method', function () {
            beforeEach(function () {
                productAddComponent.isShow = false;

                spyOn(productAddComponent, '_getProductForm').and.returnValue({
                    /** @inheritdoc */
                    is: function () {
                        return false;
                    },

                    /** @inheritdoc */
                    valid: function () {
                        return 'valid';
                    },

                    /** @inheritdoc */
                    parent: function () {
                        return {
                            /** @inheritdoc */
                            show: function () {
                                productAddComponent.isShow = true;
                            }
                        };
                    }
                });
            });

            it('Check _validateProductForm method return correct params', function () {
                expect(productAddComponent._validateProductForm()).toEqual('valid');
            });

            it('Check if parent of product form is show', function () {
                productAddComponent._validateProductForm();
                expect(productAddComponent.isShow).toBeTruthy();
            });

            it('Check if parent of product form is hide', function () {
                /** @inheritdoc */
                productAddComponent._getProductForm().is = function () {
                    return true;
                };
                productAddComponent._validateProductForm();
                expect(productAddComponent.isShow).toBeFalsy();
            });
        });

        describe('"_isActionValid" method', function () {
            it('Check _isActionValid method return correct params', function () {
                spyOn(productAddComponent, '_validateProductForm').and.returnValue(true);
                expect(productAddComponent._isActionValid()).toBeTruthy();
            });
        });

        describe('"_getProductData" method', function () {
            it('Check _getProductData method return correct params', function () {
                var params = {
                        qty: 1
                    },
                    returnParams = {
                        sku: undefined,
                        qty: 1,
                        options: {
                            qty: 1
                        }
                    };

                spyOn(productAddComponent, '_getProductOptions').and.returnValue(params);
                expect(productAddComponent._getProductData()).toEqual(returnParams);
            });
        });

        describe('"_getProductForm" method', function () {
            it('Check _getProductForm method return correct params', function () {
                expect(productAddComponent._getProductForm()).toEqual($('<form />'));
            });
        });

        describe('"_getProductOptions" method', function () {
            it('Check _getProductForm method return correct params', function () {
                var input = $('<input />', {
                        value: 1,
                        name: 'input'
                    }),
                    form = $('<form />').append(input);

                spyOn(productAddComponent, '_getProductForm').and.returnValue(form);
                expect(productAddComponent._getProductOptions()).toEqual('input=1');
            });
        });

        describe('"isConfigureMode" method', function () {
            it('Check isConfigureMode method return correct params', function () {
                window.location.hash = '';
                expect(productAddComponent.isConfigureMode()).toBeFalsy();
                window.location.hash = 'requisition_configure';
                expect(productAddComponent.isConfigureMode()).toBeTruthy();
            });
        });
    });
});
