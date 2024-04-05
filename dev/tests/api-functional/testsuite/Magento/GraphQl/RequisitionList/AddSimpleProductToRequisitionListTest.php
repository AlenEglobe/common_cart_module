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
 * Test coverage for adding simple product to Requisition List
 */
class AddSimpleProductToRequisitionListTest extends GraphQlAbstract
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
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     *
     * @throws Exception
     */
    public function testAddSimpleProduct(): void
    {
        $sku = 'item_1';
        $qty = 1;
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $listId = base64_encode((string)$requisitionListId);
        $query = $this->getQuery($sku, $qty, $listId);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $this->assertArrayHasKey('addProductsToRequisitionList', $response);
        $this->assertArrayHasKey('requisition_list', $response['addProductsToRequisitionList']);
        $requisitionListResponse = $response['addProductsToRequisitionList']['requisition_list'];
        $this->assertEquals($requisitionList->getId(), base64_decode($requisitionListResponse['uid']));
        $this->assertEquals($requisitionList->getDescription(), $requisitionListResponse['description']);
        $this->assertEquals($requisitionList->getName(), $requisitionListResponse['name']);
        $this->assertEquals(count($requisitionList->getItems()), $requisitionListResponse['items_count']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     * @throws AuthenticationException
     */
    public function testAddSimpleProductInvalidSku(): void
    {
        $sku = 'simple_product';
        $qty = 1;
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $query = $this->getQuery($sku, $qty, $listId);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The SKU was not found.');
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
     * @param string $sku
     * @param int $qty
     * @param string $requisitionListId
     * @return string
     */
    private function getQuery(
        string $sku,
        int $qty,
        string $requisitionListId
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        sku: "{$sku}"
        quantity: {$qty}
      }
    ]
) {
    requisition_list {
        uid
        name
        items_count
        description
        updated_at
        items {
            items {
                uid
                quantity
            }
        }
    }
  }
}
MUTATION;
    }
}
