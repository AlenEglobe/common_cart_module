/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_NegotiableQuote/js/quote/create/draft',
    'mage/translate'
], function ($, draftQuote) {
    'use strict';

    describe('Test Magento_NegotiableQuote/js/quote/create/draft', function () {
        var obj;

        beforeEach(function () {
            obj = draftQuote;
        });

        describe('Test "initialize" method', function () {
            it('Check initForm is not called', function () {
                var data = {
                    view_url: 'viewUrl',
                    create_draft_quote_url: 'createDraftUrl',
                    customer_id: 1,
                    store_id: 1,
                    customer_name: 'John Doe'
                };

                spyOn(obj, 'initForm');
                obj.initialize(data);
                expect(obj.initForm).not.toHaveBeenCalled();
            });

            it('Check initForm is called with no store ID', function () {
                var data = {
                    view_url: 'viewUrl',
                    create_draft_quote_url: 'createDraftUrl',
                    customer_id: 1,
                    store_id: null,
                    customer_name: 'John Doe'
                };

                spyOn(obj, 'initForm');
                obj.initialize(data);
                expect(obj.initForm).toHaveBeenCalled();
            });

            it('Check initForm is called with no customer ID', function () {
                var data = {
                    view_url: 'viewUrl',
                    create_draft_quote_url: 'createDraftUrl',
                    customer_id: null,
                    store_id: 1,
                    customer_name: null
                };

                spyOn(obj, 'initForm');
                obj.initialize(data);
                expect(obj.initForm).toHaveBeenCalled();
            });
        });

        describe('Test "initForm" method', function () {
            it('Check customer display area is shown when customerId is not set', function () {
                obj.customerId = false;
                obj.storeId = 1;

                spyOn(obj, 'updatePageTitle');
                spyOn(obj, 'displayContent');
                obj.initForm();
                expect(obj.updatePageTitle).toHaveBeenCalled();
                expect(obj.displayContent).toHaveBeenCalledWith(obj.backButton, true);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.resetButton, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.customerSection, true);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.storeSection, false);
            });

            it('Check customer display area is shown when customerId and storeId is not set', function () {
                obj.customerId = false;
                obj.storeId = false;

                spyOn(obj, 'updatePageTitle');
                spyOn(obj, 'displayContent');
                obj.initForm();
                expect(obj.updatePageTitle).toHaveBeenCalled();
                expect(obj.displayContent).toHaveBeenCalledWith(obj.backButton, true);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.resetButton, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.customerSection, true);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.storeSection, false);
            });

            it('Check store display area is shown when storeId is not set', function () {
                obj.customerId = 1;
                obj.storeId = false;

                spyOn(obj, 'updatePageTitle');
                spyOn(obj, 'displayContent');
                spyOn(obj, 'loadStoreSection');
                obj.initForm();
                expect(obj.updatePageTitle).toHaveBeenCalled();
                expect(obj.displayContent).toHaveBeenCalledWith(obj.backButton, true);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.resetButton, false);
                expect(obj.loadStoreSection).toHaveBeenCalled();
            });
        });

        describe('Test "selectCustomer" method', function () {
            it('Check setCustomerData is called', function () {
                var grid = {},
                    event = {target: {parentNode: 'tr.even._clickable.on-mouse'}},
                    table = '<table class="data-grid"><tbody>' +
                        '<tr class="even _clickable on-mouse" title="1"><td data-column="name">John Doe</td></tr>' +
                        '</tbody></table>';

                $(table).appendTo('body');

                spyOn(obj, 'clearErrorMessage');
                spyOn(obj, 'setCustomerData');
                obj.selectCustomer(grid, event);
                expect(obj.clearErrorMessage).toHaveBeenCalled();
                expect(obj.setCustomerData).toHaveBeenCalledWith('1', 'John Doe');

                $('table.data-grid').remove();
            });

            it('Check setCustomerData is not called', function () {
                var grid = {},
                    event = {target: {parentNode: 'tr'}};

                spyOn(obj, 'clearErrorMessage');
                spyOn(obj, 'setCustomerData');
                obj.selectCustomer(grid, event);
                expect(obj.clearErrorMessage).toHaveBeenCalled();
                expect(obj.setCustomerData).not.toHaveBeenCalled();
            });
        });

        describe('Test "setCustomerData" method', function () {
            it('Check store area is displayed', function () {
                obj.storeId = false;
                obj.customerId = false;
                obj.customerName = false;

                spyOn(obj, 'updatePageTitle');
                spyOn(obj, 'displayContent');
                spyOn(obj, 'loadStoreSection');
                obj.setCustomerData('1', 'John Doe');
                expect(obj.customerId).toEqual('1');
                expect(obj.customerName).toEqual('John Doe');
                expect(obj.updatePageTitle).toHaveBeenCalled();
                expect(obj.displayContent).toHaveBeenCalledWith(obj.backButton, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.resetButton, true);
                expect(obj.loadStoreSection).toHaveBeenCalled();
            });

            it('Check createQuote is called', function () {
                obj.storeId = 1;
                obj.customerId = false;
                obj.customerName = false;

                spyOn(obj, 'updatePageTitle');
                spyOn(obj, 'createQuote');
                obj.setCustomerData('1', 'John Doe');
                expect(obj.customerId).toEqual('1');
                expect(obj.customerName).toEqual('John Doe');
                expect(obj.updatePageTitle).not.toHaveBeenCalled();
                expect(obj.createQuote).toHaveBeenCalled();
            });
        });

        describe('Test "loadStoreSection" method', function () {
            var originaljQueryAjax;

            beforeEach(function () {
                originaljQueryAjax = $.ajax;
            });

            afterEach(function () {
                $.ajax = originaljQueryAjax;
            });

            it('Check success callback with content', function () {
                var request,
                    response = {success: true, content: '<div>Test Content</div>'};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.success;
                });

                spyOn($.fn, 'html');
                spyOn(obj, 'displayContent');
                spyOn(obj, 'checkStoreSection');
                obj.loadStoreSection();
                request(response);
                expect($.fn.html).toHaveBeenCalledWith('');
                expect(obj.displayContent).toHaveBeenCalledWith(obj.customerSection, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.storeSection, true);
                expect($.fn.html).toHaveBeenCalledWith(response.content);
                expect(obj.checkStoreSection).toHaveBeenCalled();
            });

            it('Check success callback with error', function () {
                var request,
                    response = {error: true, message: 'failed'};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.success;
                });

                spyOn($.fn, 'html');
                spyOn(obj, 'displayContent');
                spyOn(obj, 'addErrorMessage');
                obj.loadStoreSection();
                request(response);
                expect($.fn.html).toHaveBeenCalledWith('');
                expect(obj.displayContent).toHaveBeenCalledWith(obj.customerSection, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.storeSection, true);
                expect(obj.addErrorMessage).toHaveBeenCalledWith('failed');
            });

            it('Check error callback', function () {
                var request,
                    response = {};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.error;
                });

                spyOn($.fn, 'html');
                spyOn(obj, 'displayContent');
                spyOn(obj, 'addErrorMessage');
                obj.loadStoreSection();
                request(response);
                expect($.fn.html).toHaveBeenCalledWith('');
                expect(obj.displayContent).toHaveBeenCalledWith(obj.customerSection, false);
                expect(obj.displayContent).toHaveBeenCalledWith(obj.storeSection, true);
                expect(obj.addErrorMessage).toHaveBeenCalledWith(
                    $.mage.__('Unable to get list of stores for this customer')
                );
            });
        });

        describe('Test "checkStoreSection" method', function () {
            it('Check that no error message is shown', function () {
                var element = '<div id="quote-store-selector"><div class="admin-page-section-content">' +
                    '<input type="radio" id="store_1" class="admin__control-radio" data-store-id="1">' +
                    '</div></div>';

                $(element).appendTo('body');

                spyOn(obj, 'addErrorMessage');
                spyOn($.fn, 'prop');
                spyOn($.fn, 'on');
                obj.checkStoreSection();
                expect(obj.addErrorMessage).not.toHaveBeenCalled();
                expect($.fn.prop).toHaveBeenCalledWith('checked', false);
                expect($.fn.on).toHaveBeenCalledWith(
                    'click',
                    jasmine.any(Function)
                );

                $('#quote-store-selector').remove();
            });

            it('Check that an error message is shown', function () {
                spyOn(obj, 'addErrorMessage');
                obj.checkStoreSection();
                expect(obj.addErrorMessage).toHaveBeenCalledWith(
                    $.mage.__('There are no websites associated with this customer that have' +
                        ' "B2B Quote" enabled in configuration.')
                );
            });
        });

        describe('Test "selectStore" method', function () {
            it('Check setStoreId is called', function () {
                var event = {target: 'input#store_1'},
                    element = '<div id="quote-store-selector">' +
                        '<input type="radio" id="store_1" class="admin__control-radio" data-store-id="1">' +
                        '</div>';

                $(element).appendTo('body');

                spyOn(obj, 'setStoreId');
                obj.selectStore(event);
                expect(obj.setStoreId).toHaveBeenCalledWith(1);

                $('#quote-store-selector').remove();
            });

            it('Check setStoreId is not called', function () {
                var event = {target: 'input#store_1'};

                spyOn(obj, 'setStoreId');
                obj.selectStore(event);
                expect(obj.setStoreId).not.toHaveBeenCalled();
            });
        });

        describe('Test "setStoreId" method', function () {
            it('Check createQuote is called', function () {
                obj.storeId = false;

                spyOn(obj, 'clearErrorMessage');
                spyOn(obj, 'createQuote');
                obj.setStoreId('1');
                expect(obj.storeId).toEqual('1');
                expect(obj.clearErrorMessage).toHaveBeenCalled();
                expect(obj.createQuote).toHaveBeenCalled();
            });
        });

        describe('Test "updatePageTitle" method', function () {
            it('Check title is updated when customerName is set', function () {
                var title = 'Create New Quote for John Doe';

                obj.customerName = 'John Doe';

                spyOn($.fn, 'text');
                spyOn($.fn, 'attr');
                obj.updatePageTitle();
                expect($.fn.text).toHaveBeenCalledWith(title);
                expect($.fn.attr).toHaveBeenCalledWith('data-title', title);
            });

            it('Check title is updated when customerName is not set', function () {
                var title = 'Create New Quote';

                obj.customerName = false;

                spyOn($.fn, 'text');
                spyOn($.fn, 'attr');
                obj.updatePageTitle();
                expect($.fn.text).toHaveBeenCalledWith(title);
                expect($.fn.attr).toHaveBeenCalledWith('data-title', title);
            });
        });

        describe('Test "displayContent" method', function () {
            beforeEach(function () {
                $('<div id="test"></div>').appendTo('body');
            });

            afterEach(function () {
                $('#test').remove();
            });

            it('Check element is hidden', function () {
                $('#test').show();
                expect($('#test').is(':visible')).toBeTrue();
                obj.displayContent('#test', false);
                expect($('#test').is(':hidden')).toBeTrue();
            });

            it('Check element is visible', function () {
                $('#test').hide();
                expect($('#test').is(':hidden')).toBeTrue();
                obj.displayContent('#test', true);
                expect($('#test').is(':visible')).toBeTrue();
            });
        });

        describe('Test "createQuote" method', function () {
            var originaljQueryAjax;

            beforeEach(function () {
                originaljQueryAjax = $.ajax;
            });

            afterEach(function () {
                $.ajax = originaljQueryAjax;
            });

            it('Check success callback with quote_id', function () {
                var request,
                    response = {success: true, quote_id: '1'};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.success;
                });

                spyOn(obj, 'viewQuote');
                obj.createQuote();
                request(response);
                expect(obj.viewQuote).toHaveBeenCalledWith('1');
            });

            it('Check success callback with error', function () {
                var request,
                    response = {error: true, message: 'failed'};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.success;
                });

                spyOn(obj, 'quoteError');
                obj.createQuote();
                request(response);
                expect(obj.quoteError).toHaveBeenCalledWith('failed');
            });

            it('Check error callback', function () {
                var request,
                    response = {};

                $.ajax = jasmine.createSpy().and.callFake(function (req) {
                    request = req.error;
                });

                spyOn(obj, 'quoteError');
                obj.createQuote();
                request(response);
                expect(obj.quoteError).toHaveBeenCalledWith($.mage.__('Unable to create a new negotiable quote'));
            });
        });

        describe('Test "getViewQuoteUrl" method', function () {
            it('Check customer id is not added to the url', function () {
                var result;

                obj.viewUrl = 'viewUrl/';
                obj.originalData = {
                    view_url: 'viewUrl/'
                };

                result = obj.getViewQuoteUrl('1');
                expect(result).toEqual('viewUrl/quote_id/1');
            });

            it('Check customer id is added to the url', function () {
                var result;

                obj.viewUrl = 'viewUrl/';
                obj.originalData = {
                    view_url: 'viewUrl/',
                    customer_id: '2'
                };

                result = obj.getViewQuoteUrl('1');
                expect(result).toEqual('viewUrl/quote_id/1/customer_id/2');
            });
        });

        describe('Test "quoteError" method', function () {
            it('Check initialize and addErrorMessage gets called', function () {
                obj.originalData = {
                    view_url: 'viewUrl',
                    create_draft_quote_url: 'createDraftUrl'
                };

                spyOn(obj, 'initialize');
                spyOn(obj, 'addErrorMessage');
                obj.quoteError('Test Message');
                expect(obj.initialize).toHaveBeenCalledWith(obj.originalData);
                expect(obj.addErrorMessage).toHaveBeenCalledWith('Test Message');
            });
        });
    });
});
