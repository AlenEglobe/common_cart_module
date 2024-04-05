/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/*eslint-disable max-nested-callbacks*/
/*jscs:disable jsDoc*/
define([
    'squire',
    'ko'
], function (Squire, ko) {
    'use strict';

    describe('Magento_NegotiableQuote/js/model/checkout-data-resolver-mixin', function () {
        var injector = new Squire(),
            checkoutDataResolverExtended = null,
            checkoutDataResolver = null,
            resolveShippingAddress = null,
            quote = null,
            mocks = null,
            negotiableQuoteAddress = {
                getKey: function () {
                    return 'negotiable-quote-key';
                },
                firstname: 'Company 1245',
                lastname: 'Store',
                street: ['11501 Domain Dr'],
                city: 'Austin',
                postcode: '78071',
                'country_id': 'US',
                telephone: '111-111-1111'
            };

        beforeEach(function (done) {
            quote = {
                shippingAddress: ko.observable(null)
            };
            mocks = {
                'Magento_Checkout/js/action/select-shipping-address': function (address) {
                    quote.shippingAddress(address);
                },
                'Magento_Customer/js/model/address-list':
                    jasmine.createSpy().and.returnValue([negotiableQuoteAddress])
            };

            injector.mock(mocks);
            injector.require(
                ['Magento_NegotiableQuote/js/model/checkout-data-resolver-mixin'],
                function (checkoutDataResolverExt) {
                    checkoutDataResolver = jasmine.createSpyObj('checkoutDataResolver', ['resolveShippingAddress']);
                    resolveShippingAddress = checkoutDataResolver.resolveShippingAddress;
                    checkoutDataResolverExtended = checkoutDataResolverExt(checkoutDataResolver);
                    done();
                });
            window.checkoutConfig = {
                selectedShippingKey : '',
                isAddressSelected : true,
                isNegotiableQuote: true
            };
        });

        afterEach(function () {
            try {
                injector.clean();
                injector.remove();
            } catch (e) {
            }
            window.checkoutConfig = {};
        });

        describe('resolveShippingAddress()', function () {
            describe(
                'shipping address is resolved as selected negotiable quote address matches selected shipping address',
                function () {
                    it('selected negotiable quote address is set as shipping address', function () {
                        window.checkoutConfig.selectedShippingKey = 'negotiable-quote-key';
                        checkoutDataResolverExtended.resolveShippingAddress();
                        expect(quote.shippingAddress()).toEqual(negotiableQuoteAddress);
                        expect(resolveShippingAddress).toHaveBeenCalled();
                    });
                    it('selected negotiable quote address is not set as shipping address', function () {
                        window.checkoutConfig.selectedShippingKey = 'negotiable-different-key';
                        checkoutDataResolverExtended.resolveShippingAddress();
                        expect(quote.shippingAddress()).not.toEqual(negotiableQuoteAddress);
                        expect(resolveShippingAddress).toHaveBeenCalled();
                    });
                    it('shipping address is not selected as negotiable quote address',
                        function () {
                            window.checkoutConfig.selectedShippingKey = '';
                            window.checkoutConfig.isAddressSelected = false;
                            window.checkoutConfig.isNegotiableQuote = true;
                            checkoutDataResolverExtended.resolveShippingAddress();
                            expect(quote.shippingAddress()).toBeNull();
                            expect(resolveShippingAddress).toHaveBeenCalled();
                        });
                    it('quote is not a negotiable quote',
                        function () {
                            window.checkoutConfig.selectedShippingKey = 'regular-quote';
                            window.checkoutConfig.isAddressSelected = false;
                            window.checkoutConfig.isNegotiableQuote = false;
                            checkoutDataResolverExtended.resolveShippingAddress();
                            expect(quote.shippingAddress()).toBeNull();
                            expect(resolveShippingAddress).toHaveBeenCalled();
                        });
                }
            );
        });
    });
});
