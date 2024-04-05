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
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for move items between requisition list
 */
class MoveItemsBetweenRequisitionListTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var RequisitionListRepository
     */
    private $requisitionListRepository;

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
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
        $this->getRequisitionListItemId = $objectManager->get(GetRequisitionListItemId::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveItemsToOtherRequisitionList(): void
    {
        $sourceRequisitionListId = $this->getRequisitionList->execute('list two');
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
        $destinationRequisitionList = $this->requisitionListRepository->get($destinationRequisitionListId);

        $this->assertArrayHasKey('moveItemsBetweenRequisitionLists', $response);
        $this->assertArrayHasKey('destination_requisition_list', $response['moveItemsBetweenRequisitionLists']);
        $requisitionListResponse = $response['moveItemsBetweenRequisitionLists']['destination_requisition_list'];
        $this->assertEquals($destinationRequisitionList->getId(), base64_decode($requisitionListResponse['uid']));
        $this->assertEquals($destinationRequisitionList->getDescription(), $requisitionListResponse['description']);
        $this->assertEquals($destinationRequisitionList->getName(), $requisitionListResponse['name']);
        $this->assertEquals(count($destinationRequisitionList->getItems()), $requisitionListResponse['items_count']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveItemsForGuestRequisitionList(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $sourceRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');

        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $this->graphQlMutation($query, [], '', []);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveItemsFromAnotherCustomerRequisitionList(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current user cannot perform operations on requisition list");

        $sourceRequisitionListId = $this->getRequisitionList->execute('list two');
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication('customer2@example.com', 'password'));
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testWithoutSourceRequiredRequisitionIdParameter(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Specify the "sourceRequisitionListUid" value.');

        $sourceRequisitionListId = '';
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     * @throws AuthenticationException
     */
    public function testWithoutRequiredItemIdParameter(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Specify the "requisitionListItemUids" value.');

        $sourceRequisitionListId = $this->getRequisitionList->execute('list two');
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);

        $query = <<<MUTATION
mutation {
    moveItemsBetweenRequisitionLists
    (
      sourceRequisitionListUid: "{$sourceId}"
      destinationRequisitionListUid: "{$destinationId}"
      requisitionListItem: {
        requisitionListItemUids: []
      }
    ) {
    destination_requisition_list {
      uid
      name
      items_count
      description
      updated_at
    }
   }
}
MUTATION;
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveItemsFromNonExistentSourceRequisitionList(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No such entity with id = 999');

        $sourceRequisitionListId = 999;
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveItemsToNonExistentRequisitionList(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No such entity with id = 0");

        $sourceRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $destinationRequisitionListId = 0;
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$itemId);

        $query = $this->getQuery($sourceId, $destinationId, $itemId);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testMoveNonExistentItem(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No such entity with id = 0');

        $sourceRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $destinationRequisitionListId = $this->getRequisitionList->execute('Test - Requisition List Two');
        $notExistentItemId = 0;

        $sourceId = base64_encode((string)$sourceRequisitionListId);
        $destinationId = base64_encode((string)$destinationRequisitionListId);
        $itemId = base64_encode((string)$notExistentItemId);
        $query = $this->getQuery($sourceId, $destinationId, $itemId);

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
     * @param string $sourceRequisitionListId
     * @param string $destinationRequisitionListId
     * @param string $itemId
     * @return string
     */
    private function getQuery(
        string $sourceRequisitionListId,
        string $destinationRequisitionListId,
        string $itemId
    ): string {
        return <<<MUTATION
mutation {
    moveItemsBetweenRequisitionLists
    (
      sourceRequisitionListUid: "{$sourceRequisitionListId}"
      destinationRequisitionListUid: "{$destinationRequisitionListId}"
      requisitionListItem: {
        requisitionListItemUids: ["{$itemId}"]
      }
    ) {
    destination_requisition_list {
      uid
      name
      items_count
      description
      updated_at
    }
   }
}
MUTATION;
    }
}
