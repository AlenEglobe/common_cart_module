<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for Requisition List
 */
class CustomerRequisitionListsTest extends GraphQlAbstract
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
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
    }

    /**
     * Test fetching customer Requisition list
     *
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_items_v2_for_list_two.php
     */
    public function testCustomerRequisitionList(): void
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $uid = base64_encode((string)$requisitionListId);

        $query = $this->getQuery($uid, 'eq');
        $response = $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
        $this->assertArrayHasKey('requisition_lists', $response['customer']);
        $list = $response['customer']['requisition_lists']['items'][0];

        $this->assertNotEmpty($list['name']);
        $this->assertEquals('list two', $list['name']);
        $this->assertNotEmpty($list['uid']);
        $this->assertEquals($uid, $list['uid']);
        $this->assertNotEmpty($list['name']);
        $this->assertArrayHasKey('items', $list);

        $query = $this->getQuery($uid, 'in');
        $response = $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
        $this->assertArrayHasKey('requisition_lists', $response['customer']);
        $list = $response['customer']['requisition_lists']['items'][0];

        $this->assertNotEmpty($list['name']);
        $this->assertEquals('list two', $list['name']);
        $this->assertNotEmpty($list['uid']);
        $this->assertEquals($uid, $list['uid']);
        $this->assertNotEmpty($list['name']);
        $this->assertArrayHasKey('items', $list);
    }

    /**
     * Test Requisition list fetching for a guest customer
     */
    public function testGuestCannotGetRequisitionList(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current customer isn\'t authorized.');
        $this->graphQlQuery($this->getQuery(null, null));
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
     * Returns GraphQl query string
     *
     * @param ?string $requisitionListId
     * @param ?string $conditionType
     * @return string
     */

    private function getQuery(?string $requisitionListId, ?string $conditionType): string
    {
        $query = <<<QUERY
query {
  customer {
    requisition_lists(filter: {uids: {eq: "$requisitionListId"}}) {
      total_count
      items {
        uid
        name
        items_count
        description
        items{
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
        if ($conditionType === 'in') {
            $query = <<<QUERY
query {
  customer {
    requisition_lists(filter: {uids: {in: "$requisitionListId"}}) {
      total_count
      items {
        uid
        name
        items_count
        description
        items{
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
        return $query;
    }
}
