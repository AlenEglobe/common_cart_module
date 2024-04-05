<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Exception;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for add requisition list items to cart
 */
class AddRequisitionListItemsToCartTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var GetRequisitionList
     */
    private $getRequisitionList;

    /**
     * @var GetRequisitionListItemId
     */
    private $getRequisitionListItemId;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
        $this->getRequisitionListItemId = $objectManager->get(GetRequisitionListItemId::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testAddItemsToCart(): void
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemId);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $this->assertArrayHasKey('addRequisitionListItemsToCart', $response);
        $this->assertArrayHasKey('cart', $response['addRequisitionListItemsToCart']);
        $cartResponse = $response['addRequisitionListItemsToCart']['cart'];
        $this->assertNotEmpty($cartResponse['id']);
        $this->assertEmpty($cartResponse['is_virtual']);
        $this->assertNotEmpty($cartResponse['email']);
        $this->assertNotEmpty($cartResponse['items']);
        $this->assertNotEmpty($cartResponse['items'][0]['quantity']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testAddItemsToCartForInvalidUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current user cannot perform operations on requisition list");

        $requisitionListId = $this->getRequisitionList->execute('list two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication('customer2@example.com', 'password'));
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testAddItemsToCartForGuestUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', ['Authorization' => 'Bearer test_token']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testAddItemsToCartWithoutId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('"requisitionListUid" value should be specified');

        $requisitionListId = '';
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testAddItemsToCartWithInvalidId(): void
    {
        $requisitionListId = '9999';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No such entity with id = ' . $requisitionListId);

        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
     *
     * @return array
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
     * @param string $requisitionListId
     * @param string $itemId
     * @return string
     */
    private function getQuery(
        string $requisitionListId,
        string $itemId
    ): string {
        return <<<MUTATION
mutation {
    addRequisitionListItemsToCart
    (
      requisitionListUid: "{$requisitionListId}"
      requisitionListItemUids: ["{$itemId}"]
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
    add_requisition_list_items_to_cart_user_errors {
      message
      type
    }
   }
}
MUTATION;
    }
}
