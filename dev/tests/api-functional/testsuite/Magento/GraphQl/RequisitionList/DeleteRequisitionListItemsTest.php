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
use Magento\RequisitionList\Model\RequisitionListRepository;

/**
 * Test coverage for Delete Requisition List items
 */
class DeleteRequisitionListItemsTest extends GraphQlAbstract
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
     * @var RequisitionListRepository
     */
    private $requisitionListRepository;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
        $this->getRequisitionListItemId = $objectManager->get(GetRequisitionListItemId::class);
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     *
     * @throws Exception
     */
    public function testDeleteRequisitionList(): void
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $responseList = $response['deleteRequisitionListItems']['requisition_list'];
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $this->assertArrayHasKey('deleteRequisitionListItems', $response);
        $this->assertArrayHasKey('requisition_list', $response['deleteRequisitionListItems']);
        $this->assertEquals($requisitionList->getId(), base64_decode($responseList['uid']));
        $this->assertEquals($requisitionList->getDescription(), $responseList['description']);
        $this->assertEquals($requisitionList->getName(), $responseList['name']);
        $this->assertEquals(count($requisitionList->getItems()), $responseList['items_count']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testDeleteRequisitionListGuestUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', ['Authorization' => 'Bearer testtoken']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testDeleteRequisitionListInvalidUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The account sign-in was incorrect or your account is disabled temporarily. '
            . 'Please wait and try again later.'
        );

        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication('customer@example.com', '123456'));
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testDeleteRequisitionListWithoutId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Specify the "requisitionListUid" value.');

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
    public function testDeleteRequisitionListWithInvalidId(): void
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
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testDeleteRequisitionListCorrectIdUnauthorizedUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The account sign-in was incorrect or your account is disabled temporarily. '
            . 'Please wait and try again later.'
        );

        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $itemId = $this->getRequisitionListItemId->execute('list two', 'item_1');
        $itemIds = base64_encode((string)$itemId);

        $query = $this->getQuery($listId, $itemIds);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication('customer_two@example.com', 'password'));
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
     * @param string $requisitionListId
     * @param string $itemIds
     *
     * @return string
     */
    private function getQuery(
        string $requisitionListId,
        string $itemIds
    ): string {
        return <<<MUTATION
mutation {
  deleteRequisitionListItems(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItemUids: ["{$itemIds}"]
) {
    requisition_list {
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
