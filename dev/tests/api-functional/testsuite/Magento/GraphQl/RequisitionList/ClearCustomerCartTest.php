<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for Clear Customer Cart
 */
class ClearCustomerCartTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var GetMaskedQuoteIdByReservedOrderId
     */
    private $getMaskedQuoteIdByReservedOrderId;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testClearCustomerCart(): void
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');
        $query = $this->getQuery($maskedQuoteId);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
        $this->assertArrayHasKey('clearCustomerCart', $response);
        $this->assertArrayHasKey('cart', $response['clearCustomerCart']);
        $cartResponse = $response['clearCustomerCart']['cart'];
        $this->assertNotEmpty($cartResponse['id']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testClearCustomerCartForGuestUser(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');
        $query = $this->getQuery($maskedQuoteId);
        $this->graphQlMutation($query, [], '', ['Authorization' => 'Bearer test_token']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testClearCustomerCartForInvalidUser(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'The account sign-in was incorrect or your account is disabled temporarily.'
            . ' Please wait and try again later.'
        );

        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');
        $query = $this->getQuery($maskedQuoteId);
        $this->graphQlMutation(
            $query,
            [],
            '',
            $this->getHeaderAuthentication('customer@example.com', '123456')
        );
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testClearCustomerCartWithoutId(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Required parameter "cartUid" is missing');

        $maskedQuoteId = '';
        $query = $this->getQuery($maskedQuoteId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
     *
     * @return array
     *
     * @throws AuthenticationException
     */
    private function getHeaderAuthentication(
        string $username = 'customer@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);

        return ['Authorization' => 'Bearer ' . $customerToken];
    }

    /**
     * Returns GraphQl mutation string
     *
     * @param string $cartId
     *
     * @return string
     */
    private function getQuery(
        string $cartId
    ): string {
        return <<<MUTATION
mutation {
  clearCustomerCart(
    cartUid:"{$cartId}"
  ) {
    cart {
      id
      is_virtual
      items {
        quantity
      }
      email
    }
    status
  }
}
MUTATION;
    }
}
