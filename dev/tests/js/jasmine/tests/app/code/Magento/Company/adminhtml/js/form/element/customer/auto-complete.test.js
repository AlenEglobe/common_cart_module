/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_Company/js/form/element/customer/auto-complete'
], function (AutoComplete) {
    'use strict';

    describe('Magento_Company/js/form/element/customer/auto-complete', function () {
        var obj, params;

        beforeEach(function () {
            params = {
                dataScope: 'abstract',
                selectedCompany: '',
                parentScope: 'testScope',
                disabled: jasmine.createSpy()
            };
            obj = new AutoComplete(params);
            obj.source = {
                get: jasmine.createSpy().and.returnValue({
                    'company_name': 'company',
                    'is_super_user': 0
                })
            };
        });

        describe('"initialize" method', function () {
            it('Check for defined', function () {
                expect(obj.initialize).toBeDefined();
                expect(obj.initialize).toEqual(jasmine.any(Function));
            });

            it('Check if initStateConfig has been called', function () {
                spyOn(obj, 'initStateConfig');
                obj.initialize();
                expect(obj.initStateConfig).toHaveBeenCalled();
            });
        });

        describe('"initStateConfig" method', function () {
            it('Check initStateConfig method call', function () {
                obj.initStateConfig();
                expect(obj.source.get).toHaveBeenCalled();
                expect(obj.selectedCompany).toEqual('company');
                expect(obj.disabled).toHaveBeenCalledWith(0);
            });
        });
    });
});
