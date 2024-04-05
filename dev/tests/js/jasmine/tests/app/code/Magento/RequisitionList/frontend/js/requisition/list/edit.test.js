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
            'mage/storage': jasmine.createSpyObj(['post']),
            'mage/dataPost': jasmine.createSpy().and.returnValue({
                postData: jasmine.createSpy()
            })
        },
        params,
        editObj;

    describe('RequisitionList/js/requisition/list/edit', function () {

        beforeEach(function (done) {
            injector.mock(mocks);
            injector.require(['Magento_RequisitionList/js/requisition/list/edit'], function (Edit) {
                params = {
                    saveUrl: ''
                };
                editObj = new Edit(params);
                done();
            });
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {}
        });

        describe('"edit" method', function () {
            it('Check for defined', function () {
                expect(editObj.edit).toBeDefined();
                expect(editObj.edit).toEqual(jasmine.any(Function));
            });

            it('Check edit form', function () {
                var deferred = $.Deferred();

                editObj.modal = jasmine.createSpy().and.returnValue({
                    setValues: jasmine.createSpy(),
                    openModal: jasmine.createSpy().and.returnValue(deferred.promise())
                });

                editObj.edit({});
                expect(editObj.modal().setValues).toHaveBeenCalledWith({});
                expect(editObj.modal().openModal).toHaveBeenCalled();
            });
        });

        describe('"_saveAjax" method', function () {
            it('Check for defined', function () {
                expect(editObj._saveAjax).toBeDefined();
                expect(editObj._saveAjax).toEqual(jasmine.any(Function));
            });

            it('Check saving of data using ajax', function () {
                var str = JSON.stringify({
                    requisitionList: {}
                });

                editObj._saveAjax({});
                expect(mocks['mage/storage'].post).toHaveBeenCalledWith('', str);
            });
        });

        describe('"_save" method', function () {
            it('Check for defined', function () {
                expect(editObj._save).toBeDefined();
                expect(editObj._save).toEqual(jasmine.any(Function));
            });

            it('Check saving of data without ajax', function () {
                var data = {
                    action: '',
                    data: {}
                };

                editObj._save({});
                expect(mocks['mage/dataPost']().postData).toHaveBeenCalledWith(data);
            });
        });

        describe('"save" method', function () {
            it('Check for defined', function () {
                expect(editObj.save).toBeDefined();
                expect(editObj.save).toEqual(jasmine.any(Function));
            });

            it('Check "_saveAjax" method call', function () {
                spyOn(editObj, '_saveAjax').and.returnValue($.Deferred().promise());
                spyOn(editObj, '_save').and.returnValue($.Deferred().promise());

                editObj.save({});
                expect(editObj._saveAjax).toHaveBeenCalledWith({});
                expect(editObj._save).not.toHaveBeenCalled();
            });

            it('Check "_save" method call', function () {
                spyOn(editObj, '_saveAjax').and.returnValue($.Deferred().promise());
                spyOn(editObj, '_save').and.returnValue($.Deferred().promise());

                editObj.isAjax = false;
                editObj.save({});
                expect(editObj._saveAjax).not.toHaveBeenCalled();
                expect(editObj._save).toHaveBeenCalledWith({});
            });
        });
    });
});
