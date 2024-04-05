/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_Company/js/view/company'
], function (Company) {
    'use strict';

    describe('Magento_Company/js/view/company', function () {
        var obj;

        beforeEach(function () {
            obj = new Company();
        });

        describe('"isStoreFrontRegistrationAllowed" method', function () {
            it('Check for defined', function () {
                expect(obj.isStoreFrontRegistrationAllowed).toBeDefined();
                expect(obj.isStoreFrontRegistrationAllowed).toEqual(jasmine.any(Function));
            });

            it('Check if isStoreFrontRegistrationAllowed has been called', function () {
                spyOn(obj, 'isStoreFrontRegistrationAllowed');
                obj.isStoreFrontRegistrationAllowed();
                expect(obj.isStoreFrontRegistrationAllowed).toHaveBeenCalled();
            });

            it('Check returned value', function () {
                obj.config()['is_storefront_registration_allowed'] = 1;
                expect(obj.isStoreFrontRegistrationAllowed()).toBeTruthy();
            });
        });
    });
});
