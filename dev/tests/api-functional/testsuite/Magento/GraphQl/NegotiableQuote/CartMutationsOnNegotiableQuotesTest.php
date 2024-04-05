<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Exception;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage verifying that negotiable quotes cannot be mutated through non-NQ endpoints
 */
class CartMutationsOnNegotiableQuotesTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    protected function setUp(): void
    {
        $this->customerTokenService = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class);
    }

    /**
     * Test that attempting to use the addSimpleProductToCart mutation on a negotiable quote fails.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testAddSimpleProductToCartForNegotiableQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The cart isn\'t active.');
        $mutation = <<<MUTATION
mutation {
  addSimpleProductsToCart(input: {
    cart_id: "nq_customer_mask",
    cart_items: [
      {
        data: {
          quantity: 1
          sku: "simple"
        }
      }
    ]
  }) {
    cart {
      items {
        id
      }
    }
  }
}
MUTATION;
        $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * Test that attempting to set the billing address on a negotiable quote through setBillingAddressOnCart fails.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetBillingAddressOnCartMutationForNegotiableQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The cart isn\'t active.');

        $query = <<<QUERY
mutation {
  setBillingAddressOnCart(
    input: {
      cart_id: "nq_customer_mask"
      billing_address: {
         address: {
          firstname: "test firstname"
          lastname: "test lastname"
          company: "test company"
          street: ["test street 1", "test street 2"]
          city: "test city"
          region: "AZ"
          postcode: "887766"
          country_code: "US"
          telephone: "88776655"
         }
      }
    }
  ) {
    cart {
      billing_address {
        __typename
      }
    }
  }
}
QUERY;
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Test that attempting to use the non-negotiable placeOrder mutation on a negotiable quote fails.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_for_customer_checkout_ready.php
     * @magentoConfigFixture default_store carriers/flatrate/active 1
     * @magentoConfigFixture default_store payment/checkmo/active 1
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testPlaceOrderMutationForNegotiableQuote()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The cart isn\'t active.');

        $maskedQuoteId = 'cart_checkout_customer_mask';
        $requestMutation = <<<REQUEST
mutation {
  requestNegotiableQuote(
    input: {
      cart_id: "$maskedQuoteId"
      quote_name: "quote_customer_send"
      comment: {
        comment: "Quote Comment"
      }
    }
  ) {
    quote{
      uid
      status
    }
  }
}
REQUEST;

        $requestResponse = $this->graphQlMutation($requestMutation, [], '', $this->getHeaderMap());
        $this->assertEquals($maskedQuoteId, $requestResponse['requestNegotiableQuote']['quote']['uid']);
        $this->assertEquals('SUBMITTED', $requestResponse['requestNegotiableQuote']['quote']['status']);

        $placeOrderMutation = <<<PLACEORDER
mutation {
  placeOrder(input: {cart_id: "{$maskedQuoteId}"}) {
    order {
      order_number
    }
  }
}
PLACEORDER;
        $this->graphQlMutation($placeOrderMutation, [], '', $this->getHeaderMap());
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     * @throws AuthenticationException
     */
    private function getHeaderMap(
        string $username = 'customercompany22@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }
}
