/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable max-nested-callbacks */
define([
    'Magento_CompanyCredit/js/grid/massaction/selections-converter'
], function (SelectionsConverter) {
    'use strict';

    describe('Magento_CompanyCredit/js/grid/massaction/selections-converter', function () {
        describe('"convert" method', function () {
            it('return expected result for include mode', function () {
                var data = {
                        excludeMode: false,
                        excluded: [1, 2, 3],
                        selected: [4, 5],
                        params: {
                            filters: {
                                placeholder: true
                            }
                        },
                        total: 2
                    },
                    expected = {
                        filters: {
                            placeholder: true
                        },
                        selected: [4, 5]
                    };

                expect(SelectionsConverter.convert(data)).toEqual(expected);
            });

            it('return expected result for exclude mode', function () {
                var data = {
                        excludeMode: true,
                        excluded: [1, 2],
                        selected: [3, 4, 5],
                        params: {
                            filters: {
                                placeholder: true
                            }
                        },
                        total: 20
                    },
                    expected = {
                        filters: {
                            placeholder: true
                        },
                        excluded: [1, 2]
                    };

                expect(SelectionsConverter.convert(data)).toEqual(expected);
            });
        });
    });
});
