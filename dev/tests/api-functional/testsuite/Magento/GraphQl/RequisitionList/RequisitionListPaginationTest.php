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
 * Test coverage for Requisition List pagination atest
 */
class RequisitionListPaginationTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
    }

    /**
     * Test fetching customer Requisition list
     *
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/requisition_lists.php
     */
    public function testDefaultPagination(): void
    {
        $query = <<<QUERY
{
customer {
  requisition_lists(filter: {name: {match: "List"}}) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
  }
  }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());

        $requisitionList = $response['customer']['requisition_lists']['total_count'];
        $this->assertEquals($requisitionList, $response['customer']['requisition_lists']['total_count']);
        $this->assertArrayHasKey('page_info', $response['customer']['requisition_lists']);
        $pageInfo = $response['customer']['requisition_lists']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(20, $pageInfo['page_size']);
        $this->assertEquals(1, $pageInfo['total_pages']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/requisition_lists.php
     */
    public function testPageSize(): void
    {
        $query = <<<QUERY
{
customer {
  requisition_lists(
    filter: {name: {match: "List"}}
    pageSize: 2
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
  }
 }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
        $listTotal = $response['customer']['requisition_lists']['total_count'];
        $this->assertEquals($listTotal, $response['customer']['requisition_lists']['total_count']);
        $pageInfo = $response['customer']['requisition_lists']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(2, $pageInfo['page_size']);
        $this->assertEquals(2, $pageInfo['total_pages']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/requisition_lists.php
     */
    public function testCurrentPage()
    {
        $query = <<<QUERY
{
customer {
  requisition_lists(
    filter: {name: {match: "List"}}
    pageSize: 3
    currentPage: 3
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
  }
  }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
        $list = $response['customer']['requisition_lists']['total_count'];
        $this->assertEquals($list, $response['customer']['requisition_lists']['total_count']);
        $pageInfo = $response['customer']['requisition_lists']['page_info'];
        $this->assertEquals(3, $pageInfo['current_page']);
        $this->assertEquals(3, $pageInfo['page_size']);
        $this->assertEquals(2, $pageInfo['total_pages']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     */
    public function testCurrentPageZero()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('currentPage value must be greater than 0.');

        $query = <<<QUERY
{
customer {
  requisition_lists(
    filter: {name: {match: "List"}}
    currentPage: 0
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      name
    }
  }
  }
}
QUERY;
        $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     */
    public function testPageSizeZero()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('currentPage value must be greater than 0.');

        $query = <<<QUERY
{
  customer {
  requisition_lists(
    filter: {name: {match: "List"}}
    currentPage: 0
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      name
    }
  }
  }
}
QUERY;
        $this->graphQlQuery($query, [], '', $this->getHeaderAuthentication());
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
}
