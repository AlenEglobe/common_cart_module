/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'squire'
], function (Squire) {
    'use strict';

    var injector = new Squire(),
        mocks = {
            'Magento_SharedCatalog/js/wizard': jasmine.createSpy('Wizard').and.returnValue(true)
        },
        stepWizard;

    describe('SharedCatalog/js/step-wizard', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_SharedCatalog/js/step-wizard'], function (Constr) {
                stepWizard = new Constr({
                    trigger: jasmine.createSpy().and.returnValue(true)
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

        describe('initialize method', function () {
            it('Check for defined ', function () {
                expect(stepWizard.hasOwnProperty('initialize')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof stepWizard.initialize;

                expect(type).toEqual('function');
            });
        });

        describe('open method', function () {
            it('Check if selectedStep has been called', function () {
                spyOn(stepWizard, 'selectedStep');
                stepWizard.open();
                expect(stepWizard.selectedStep).toHaveBeenCalled();
            });

            it('Check for defined', function () {
                expect(stepWizard.wizard).toBeUndefined();
                stepWizard.open();
                expect(stepWizard.wizard).toBeDefined();
            });
        });

        describe('close method', function () {
            it('Check if event is triggered', function () {
                stepWizard.close();
                expect(stepWizard.trigger).toHaveBeenCalled();
            });
        });

        describe('_closeModal method', function () {
            it('Check for defined ', function () {
                expect(stepWizard.hasOwnProperty('_closeModal')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof stepWizard._closeModal;

                expect(type).toEqual('function');
            });
        });

        describe('hideLoader method', function () {
            it('Check for defined ', function () {
                expect(stepWizard.hasOwnProperty('hideLoader')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof stepWizard.hideLoader;

                expect(type).toEqual('function');
            });
        });

        describe('showLoader method', function () {
            it('Check for defined ', function () {
                expect(stepWizard.hasOwnProperty('showLoader')).toBeDefined();
            });

            it('Check method type', function () {
                var type = typeof stepWizard.showLoader;

                expect(type).toEqual('function');
            });
        });

        describe('back method', function () {
            it('Check if event is triggered', function () {
                stepWizard.back();
                expect(stepWizard.trigger).toHaveBeenCalled();
            });
        });
    });
});
