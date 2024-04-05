/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_SharedCatalog/js/form/insert-form'
], function ($, InsertForm) {
    'use strict';

    describe('Magento_SharedCatalog/js/form/insert-form', function () {
        var obj, params;

        beforeEach(function () {
            params = {
                /** @inheritdoc */
                columns: function () {},
                responseData: {
                    data: {
                        status: false,
                        error: 'error'
                    }
                },
                errorContainerClass: 'message-error',

                /** @inheritdoc */
                toolbar: function () {},
                events: {
                    afterUpdate: ['eventName']
                },
                toolbarSection: '<header>test</header>',
                trigger: jasmine.createSpy().and.returnValue(true)
            },
            obj = new InsertForm(params);
        });

        describe('"afterUpdate" method', function () {
            it('Check method call', function () {
                spyOn(obj, 'afterUpdate');
                obj.afterUpdate();
                expect(obj.afterUpdate).toHaveBeenCalled();
            });

            it('Check if removeError has been called', function () {
                spyOn(obj, 'removeError');
                obj.afterUpdate();
                expect(obj.removeError).toHaveBeenCalled();
            });

            it('Check if event is triggered', function () {
                obj.afterUpdate();
                expect(obj.trigger).toHaveBeenCalledWith('eventName');
            });

            it('Check if renderError has been called', function () {
                spyOn(obj, 'renderError');
                obj.afterUpdate();
                expect(obj.renderError).toHaveBeenCalledWith('error');
            });

            it('Check if status is true', function () {
                spyOn(obj, 'toolbar').and.returnValue({
                    /** @inheritdoc */
                    closeModal: function () {
                        return true;
                    }
                });
                obj.responseData.data.status = true;
                obj.afterUpdate();
                expect(obj.toolbar).toHaveBeenCalled();
            });
        });

        describe('"destroyInserted" method', function () {
            it('Check if removeError has been called', function () {
                spyOn(obj, 'removeError');
                obj.destroyInserted();
                expect(obj.removeError).toHaveBeenCalled();
            });

            it('check for chainable', function () {
                spyOn(obj, 'destroyInserted').and.returnValue(obj);
                expect(obj.initialize(params)).toEqual(obj);
            });
        });

        describe('"render" method', function () {
            it('Check if event is triggered', function () {
                obj.render();
                expect(obj.trigger).toHaveBeenCalledWith('render-form', jasmine.any(Function));
            });

            it('Check if loader is hide', function () {
                spyOn(obj, 'columns');
                obj.render();
                expect(obj.columns).toHaveBeenCalledWith('hideLoader');
            });
        });

        describe('"renderError" method', function () {
            it('Check if formError is defined', function () {
                expect(obj.formError).not.toBeDefined();
                obj.renderError();
                expect(obj.formError).toBeDefined();
            });

            it('Check if an error class has been added to a toolbar', function () {
                obj.renderError();
                expect(obj.formError[0].classList.contains(obj.errorContainerClass)).toBeTruthy();
            });
        });

        describe('"removeError" method', function () {
            it('Check if formErrors is removed', function () {
                obj.formError = $('<div />');
                expect(obj.formError.length).toBe(1);
                obj.removeError();
                expect(obj.formError.length).toBe(0);
            });
        });
    });
});
