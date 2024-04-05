/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'squire'
], function ($, Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_QuickOrder/js/item-table/mass-add-rows': {
                addNewRows: jasmine.createSpy().and.returnValue({
                    done: jasmine.createSpy().and.returnValue($.Deferred().promise())
                })
            }
        },
        tplElement = $('<textarea name="multiple_skus" ' +
            'data-role="multiple-skus" id="multiple_skus" class="input-text">value1</textarea>'),
        button = $('<button name="single-skus"/>'),
        obj,
        errorType = 'example_error_type';

    describe('Magento_QuickOrder/js/multiple-skus', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['quickOrderMultipleSkus'], function (MultipleSku) {
                obj = new MultipleSku({
                    errorType: errorType
                }, button);
                done();
            });

            $(document.body).append(tplElement).append(button);

            jQuery.post = jasmine.createSpy().and.callFake(function () {
                var d = $.Deferred();

                d.resolve([
                    {
                        items: {
                            'value1': {
                                sku: 'value1'
                            }
                        }
                    },
                    'success'
                ]);

                return d.promise();
            });
        });

        afterEach(function () {
            tplElement.remove();
            button.remove();
        });

        describe('"_moveSkusToSingleInputs" method', function () {
            it('Check for defined', function () {
                expect(obj._moveSkusToSingleInputs).toBeDefined();
                expect(obj._moveSkusToSingleInputs).toEqual(jasmine.any(Function));
            });

            it('Check post request sending', function () {
                var localParams = {
                    items: JSON.stringify([
                        {
                            'sku': 'value1',
                            'qty': 1
                        }
                    ]),
                    errorType: errorType
                };

                expect(button.is(':disabled')).toBeFalsy();
                obj._moveSkusToSingleInputs();
                expect(button.is(':disabled')).toBeTruthy();
                expect(jQuery.post).toHaveBeenCalledWith('', localParams);
            });
        });
    });
});
