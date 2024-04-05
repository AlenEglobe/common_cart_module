/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/* eslint-disable max-nested-callbacks */
define([
    'jquery',
    'Magento_OrderHistorySearch/js/validation',
    'mage/validation'
], function ($, mixinFunc) {
    'use strict';

    describe('Magento_OrderHistorySearch/js/validation', function () {
        var fromDateElement, toDateElement;

        // Add the validation method from the mixin
        mixinFunc.call();

        // Mock an html form
        fromDateElement = $('<input type="text" class="date-range-test-from">').get(0);
        toDateElement = $('<input type="text" class="date-range-test-to">').get(0);
        $('<form/>').append([fromDateElement, toDateElement]);

        describe('validate-formatted-date - MM/DD/YYYY', function () {
            var params = {
                'dateFormat': 'MM/DD/YYYY'
            };

            it('Test that expected format is allowed', function () {
                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '01/13/2020', null, params)
                ).toEqual(true);
            });

            it('Test that alternate formats are not allowed', function () {
                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '13/01/2020', null, params)
                ).toEqual(false);

                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '2020-01-13', null, params)
                ).toEqual(false);

                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '113', null, params)
                ).toEqual(false);

                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, 'foo', null, params)
                ).toEqual(false);
            });
        });

        describe('validate-formatted-date - DD/MM/YYYY', function () {
            var params = {
                'dateFormat': 'DD/MM/YYYY'
            };

            it('Test that expected format is allowed', function () {
                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '13/01/2020', null, params)
                ).toEqual(true);
            });

            it('Test that alternate formats are not allowed', function () {
                expect(
                    $.validator.methods['validate-formatted-date']
                        .call($.validator.prototype, '01/013/2020', null, params)
                ).toEqual(false);
            });
        });

        describe('validate-formatted-date-range - MM/DD/YYYY', function () {
            var params = {
                'dateFormat': 'MM/DD/YYYY'
            };

            it('Test that "from" date < "to" date is allowed', function () {
                fromDateElement.value = '01/02/2020';
                toDateElement.value = '02/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                        fromDateElement.value,
                        fromDateElement,
                        params
                    )
                ).toEqual(true);
            });

            it('Test that "from" date == "to" date is allowed', function () {
                fromDateElement.value = '01/15/2020';
                toDateElement.value = '01/15/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that empty "from" date is allowed', function () {
                fromDateElement.value = '';
                toDateElement.value = '02/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that empty "to" date is allowed', function () {
                fromDateElement.value = '01/02/2020';
                toDateElement.value = '';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that "from" date > "to" date is not allowed', function () {
                fromDateElement.value = '02/01/2020';
                toDateElement.value = '01/02/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that invalid date is not allowed', function () {
                fromDateElement.value = '01/32/2020';
                toDateElement.value = '02/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that valid range in alternate date format DD/MM/YYYY is not allowed', function () {
                fromDateElement.value = '15/01/2020';
                toDateElement.value = '01/02/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that valid range in alternate date format YYYY-MM-DD is not allowed', function () {
                fromDateElement.value = '2020-01-02';
                toDateElement.value = '2020-02-01';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });
        });

        describe('validate-formatted-date-range - DD/MM/YYYY', function () {
            var params = {
                'dateFormat': 'DD/MM/YYYY'
            };

            it('Test that "from" date < "to" date is allowed', function () {
                fromDateElement.value = '02/01/2020';
                toDateElement.value = '01/02/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that "from" date == "to" date is allowed', function () {
                fromDateElement.value = '15/01/2020';
                toDateElement.value = '15/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that empty "from" date is allowed', function () {
                fromDateElement.value = '';
                toDateElement.value = '01/02/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that empty "to" date is allowed', function () {
                fromDateElement.value = '02/01/2020';
                toDateElement.value = '';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(true);
            });

            it('Test that "from" date > "to" date is not allowed', function () {
                fromDateElement.value = '01/02/2020';
                toDateElement.value = '02/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that invalid date is not allowed', function () {
                fromDateElement.value = '32/01/2020';
                toDateElement.value = '01/02/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that valid range with alternate date format MM/DD/YYYY is not allowed', function () {
                fromDateElement.value = '01/15/2020';
                toDateElement.value = '02/01/2020';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });

            it('Test that valid range with alternate date format YYYY-MM-DD is not allowed', function () {
                fromDateElement.value = '2020-01-02';
                toDateElement.value = '2020-02-01';

                expect(
                    $.validator.methods['validate-formatted-date-range']
                        .call($.validator.prototype,
                            fromDateElement.value,
                            fromDateElement,
                            params
                        )
                ).toEqual(false);
            });
        });
    });
});
