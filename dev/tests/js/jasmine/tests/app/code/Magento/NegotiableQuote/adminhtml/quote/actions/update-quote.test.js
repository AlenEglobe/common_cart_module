/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_NegotiableQuote/quote/actions/update-quote'
], function ($, UpdateQuote) {
    'use strict';

    describe('Magento_NegotiableQuote/quote/actions/update-quote', function () {
        var obj, Event, tplElement, originalJQueryAjax;

        beforeEach(function () {
            originalJQueryAjax = $.ajax;
            obj = new UpdateQuote({});
            Event = 'testEvent';
            tplElement = $('<input type="hidden" name="quote_id" value="10">');

            tplElement.appendTo(document.body);
        });

        afterEach(function () {
            $.ajax = originalJQueryAjax;
        });

        describe('"_setUrl" method', function () {
            it('Check for defined', function () {
                expect(obj._setUrl).toBeDefined();
                expect(obj._setUrl).toEqual(jasmine.any(Function));
            });

            it('Check if event was not triggered', function () {
                obj._setUrl();
                expect(obj.options.reload).toBeFalsy();
            });

            it('Check if event was triggered', function () {
                var data = {
                    data: {
                        append: jasmine.createSpy()
                    },
                    url: 'testUrl'
                };

                spyOn(obj, '_sendAjax');
                obj._setUrl(Event, data);
                expect(obj.options.url).toEqual('testUrl');
                expect(obj._sendAjax).toHaveBeenCalledWith(
                    data.data,
                    {
                        contentType: false,
                        processData: false
                    },
                    false,
                    jasmine.any(Function)
                );
            });

            it('Check sending of ajax request', function () {
                var data = {
                    data: {
                        append: jasmine.createSpy()
                    },
                    url: 'testUrl'
                };

                $.ajax = jasmine.createSpy().and.callFake(function () {
                    var d = $.Deferred();

                    /** @inheritdoc */
                    d.promise().complete = function () {};

                    return d.promise();
                });
                obj._setUrl(Event, data);
                expect($.ajax).toHaveBeenCalled();
            });
        });

        describe('"_updateQuote" method', function () {
            it('Check for defined', function () {
                expect(obj._updateQuote).toBeDefined();
                expect(obj._updateQuote).toEqual(jasmine.any(Function));
            });

            it('Check if "_sendAjax" was called', function () {
                obj.options.dataSend['quote_id'] = 1;
                spyOn(obj, '_sendAjax');
                obj._updateQuote(true);
                expect(obj._sendAjax).toHaveBeenCalledWith(
                    {
                        'quote_id': 1,
                        quote: {
                            items: [],
                            recalcPrice: 1,
                            update: 1
                        }
                    },
                    false,
                    false,
                    jasmine.any(Function)
                );
                expect(obj.options.displayMessageChanges).toBeTruthy();
            });
        });
    });
});
