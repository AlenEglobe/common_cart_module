/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'ko',
    'squire'
], function (ko, Squire) {
    'use strict';

    var injector = new Squire(),
        modalStub = {
            openModal: jasmine.createSpy(),
            closeModal: jasmine.createSpy()
        },
        mocks = {
            'Magento_Ui/js/modal/confirm': jasmine.createSpy('confirm').and.returnValue(modalStub),
            'text!Magento_RequisitionList/template/modal/confirm/add-to-cart.html':
                '<p class="text-main"><%- mainText %></p>'
        },
        data = {},
        element;

    describe('Magento_RequisitionList/js/modal/confirm/add-to-cart', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_RequisitionList/js/modal/confirm/add-to-cart'], function (Constr) {
                element = Constr({
                    confirmContentData: {
                        mainText: 'You have items in your shopping cart.'
                    }
                });
                done();
            });
        });

        describe('"confirm" method', function () {
            it('Check method call', function () {
                spyOn(element, 'confirm');
                element.confirm(data);
                expect(element.confirm).toHaveBeenCalled();
            });

            it('Check if popup has been opened', function () {
                element.confirm(data);
                expect(mocks['Magento_Ui/js/modal/confirm']).toHaveBeenCalled();
            });
        });

        describe('_getContentText method', function () {
            it('Check method call', function () {
                spyOn(element, '_getContentText');
                element._getContentText();
                expect(element._getContentText).toHaveBeenCalled();
            });

            it('Check if template for popup has been rendered', function () {
                expect(element._getContentText())
                    .toBe('<p class="text-main">You have items in your shopping cart.</p>');
            });
        });
    });
});
