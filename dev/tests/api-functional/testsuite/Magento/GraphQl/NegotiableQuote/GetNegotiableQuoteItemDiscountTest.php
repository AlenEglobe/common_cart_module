<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Test\Fixture\ApplyQuoteConfigForCompany;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote;
use Magento\NegotiableQuote\Test\Fixture\QuoteIdMask;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuoteItemDiscount;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Test coverage for getting Negotiable Quote Line item discount data
 */
class GetNegotiableQuoteItemDiscountTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    protected function setUp(): void
    {
        $this->customerTokenService = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 1
                        ],
                    ],
                ],
            ],
            'quote'
        ),
        DataFixture(
            NegotiableQuoteItemDiscount::class,
            [
                'quote_id' => '$quote.id$',
                'item_sku' => '$product.sku$',
                'negotiated_price_type' => 1,
                'negotiated_price_value' => 50.0
            ]
        ),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask')
    ]
    public function testGetNegotiableQuoteById(): void
    {
        $maskedQuoteId = DataFixtureStorageManager::getStorage()->get('quoteIdMask')->getMaskedId();
        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        $customerToken = $this->customerTokenService->createCustomerAccessToken($customer->getEmail(), 'password');

        $negotiableQuoteQuery = $this->getQuery($maskedQuoteId);
        $response = $this->graphQlQuery(
            $negotiableQuoteQuery,
            [],
            '',
            ['Authorization' => sprintf('Bearer %s', $customerToken)]
        );

        $this->assertArrayHasKey('discount', $response['negotiableQuote']['items'][0]);
        $this->assertArrayHasKey('value', $response['negotiableQuote']['items'][0]['discount'][0]);
        $this->assertArrayHasKey('type', $response['negotiableQuote']['items'][0]['discount'][0]);
        $this->assertNotEmpty($response['negotiableQuote']['items'][0]['discount'][0]['type']);
        $this->assertNotEmpty($response['negotiableQuote']['items'][0]['discount'][0]['value']);
        $this->assertEquals(1, $response['negotiableQuote']['items'][0]['discount'][0]['type']);
        $this->assertEquals(50.0, $response['negotiableQuote']['items'][0]['discount'][0]['value']);
    }

    /**
     * Returns GraphQl Query string to get a negotiable quote
     *
     * @param string $negotiableQuoteId
     * @return string
     */
    private function getQuery(string $negotiableQuoteId): string
    {
        return <<<QUERY
{
  negotiableQuote(uid: "{$negotiableQuoteId}") {
    items {
      discount {
        type
        value
      }
    }
  }
}
QUERY;
    }
}
