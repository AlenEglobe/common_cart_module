/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_RequisitionList/js/requisition/list/edit/form'
], function (Form) {
    'use strict';

    describe('RequisitionList/js/requisition/list/edit/form', function () {
        var params,
            data,
            form;

        beforeEach(function () {
            params = {
                value: {}
            };
            data = {
                name: 'test'
            };
            form = new Form(params);
        });

        describe('"initObservable" method', function () {
            it('Check for defined', function () {
                expect(form.initObservable).toBeDefined();
                expect(form.initObservable).toEqual(jasmine.any(Function));
            });
        });

        describe('"setValues" method', function () {
            it('Check for defined', function () {
                expect(form.setValues).toBeDefined();
                expect(form.setValues).toEqual(jasmine.any(Function));
            });

            it('Check new values', function () {
                form.setValues({});
                expect(form.value()).toEqual({});
                form.setValues(data);
                expect(form.value()).toEqual(data);
            });
        });

        describe('"getValues" method', function () {
            it('Check for defined', function () {
                expect(form.getValues).toBeDefined();
                expect(form.getValues).toEqual(jasmine.any(Function));
            });

            it('Check current values', function () {
                expect(form.getValues()).toEqual({});
                form.value(data);
                expect(form.getValues()).toEqual(data);
            });
        });
    });
});
