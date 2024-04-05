/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_SharedCatalog/js/validation',
    'Magento_Ui/js/lib/validation/validator'
], function ($, Validation, validator) {
    'use strict';

    describe('Magento_SharedCatalog/js/validation', function () {
        var obj, element; //eslint-disable-line no-unused-vars

        beforeEach(function () {
            obj = new Validation();
            element = $('<input id="company-name" type="text" value="Some long company name" />');
        });

        describe('Testing the maximum allowed catalog name length validation', function () {
            it('The catalog name length is more than allowed', function () {
                element.validate('max-characters');
                expect(validator('max-characters', element.val(), 10).passed).toBeFalsy();
            });

            it('The catalog name length is less than allowed', function () {
                element.validate('max-characters');
                expect(validator('max-characters', element.val(), 30).passed).toBeTruthy();
            });
        });
    });
});
