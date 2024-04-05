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
        mocks = {},
        tplElement = $('<div class="product-error general-error"></div>'),
        btnElement = $('<button type="submit" class="action tocart primary" disabled></button>'),
        params,
        obj;

    describe('Magento_QuickOrder/js/counting-errors', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_QuickOrder/js/counting-errors'], function (CountingErrors) {
                params = {
                    options: {
                        nameErrorBlock: '',
                        wrapError: '',
                        renderData: {
                            countError: null,
                            countErrorText: ' product(s) require(s) your attention.',
                            text: null
                        }
                    }
                };
                obj = new CountingErrors(params);
                done();
            });

            tplElement.appendTo(document.body);
            btnElement.appendTo(document.body);
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"_setError" method', function () {
            it('Check for defined', function () {
                expect(obj._setError).toBeDefined();
                expect(obj._setError).toEqual(jasmine.any(Function));
            });

            it('Check error setting', function () {
                spyOn(obj, '_renderError');
                obj._setError();
                expect(obj._renderError).not.toHaveBeenCalled();
                obj.options.renderData.text = 'error';
                obj._setError();
                expect(obj._renderError).toHaveBeenCalled();
            });

            it('Check status of submit button', function () {
                var $addBtn = $('button.tocart');

                obj._setError();
                expect($addBtn.prop('disabled')).toBe(false);
            });
        });
    });
});
