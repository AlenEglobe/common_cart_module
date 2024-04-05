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
 * Test coverage for Update Requisition List items
 */
class UpdateRequisitionListItemsTest extends GraphQlAbstract
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
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
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
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     *
     * @throws Exception
     */
    public function testUpdateRequisitionListItems(): void
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');

        $listId = base64_encode((string)$requisitionListId);
        $requisitionListData = $this->getRequisitionListFromCustomer($listId);

        $this->assertNotEmpty($requisitionListData['customer']['requisition_lists']['total_count']);
        $this->assertNotEmpty($requisitionListData['customer']['requisition_lists']['items'][0]['items']);
        $itemId = $requisitionListData['customer']['requisition_lists']['items'][0]['items']['items'][0]['uid'];
        $qty = 5;
        $query = $this->getQuery($listId, $itemId, $qty);

        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $responseList = $response['updateRequisitionListItems']['requisition_list'];
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $this->assertArrayHasKey('updateRequisitionListItems', $response);
        $this->assertArrayHasKey('requisition_list', $response['updateRequisitionListItems']);
        $this->assertEquals($requisitionList->getId(), base64_decode($responseList['uid']));
        $this->assertEquals($requisitionList->getDescription(), $responseList['description']);
        $this->assertEquals($requisitionList->getName(), $responseList['name']);
        $this->assertEquals(count($requisitionList->getItems()), $responseList['items_count']);
    }

    /**
     * Get requisition list result
     *
     * @param string $id
     * @return array
     *
     * @throws AuthenticationException
     */
    public function getRequisitionListFromCustomer(string $id): array
    {
        return $this->graphQlQuery(
            $this->getCustomerRequisitionListQuery($id),
            [],
            '',
            $this->getHeaderAuthentication()
        );
    }

    /**
     * Get Customer Requisition list by id
     * @param string $id
     * @return string
     */
    private function getCustomerRequisitionListQuery(string $id): string
    {
        return <<<QUERY
{
  customer {
    requisition_lists (
      filter:{
        uids:{
          eq: "{$id}"
        }
      }
    ) {
      total_count
      items {
        name
        items_count
        description
        uid
        items {
          items {
            uid
            quantity
          }
        }
      }
    }
  }
}
QUERY;
    }

    /**
     * Returns GraphQl mutation string
     *
     * @param string $requisitionListId
     * @param string $itemId
     * @param float $quantity
     * @return string
     */
    private function getQuery(
        string $requisitionListId,
        string $itemId,
        float $quantity
    ): string {
        return <<<MUTATION
mutation {
  updateRequisitionListItems(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        item_id: "{$itemId}"
        quantity: {$quantity}
      }
    ]
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
