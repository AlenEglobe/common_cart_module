<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\RequisitionList\Model\ResourceModel\RequisitionList;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for Create Requisition List
 */
class CreateRequisitionListTest extends GraphQlAbstract
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
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     *
     */
    public function testCreateRequisitionList(): void
    {
        $requisitionListName = "Functional Test Requisition List - Name 2";
        $requisitionListDescription = "Functional Test Requisition List - Description 2";

        $query = $this->getQuery($requisitionListName, $requisitionListDescription);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $this->assertArrayHasKey('createRequisitionList', $response);
        $this->assertArrayHasKey('requisition_list', $response['createRequisitionList']);
        $requisitionListResponse = $response['createRequisitionList']['requisition_list'];
        $this->assertEquals($requisitionListName, $requisitionListResponse['name']);
        $this->assertEquals($requisitionListDescription, $requisitionListResponse['description']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     *
     */
    public function testCreateRequisitionListGuestUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $requisitionListName = 'Functional Test Requisition List - Name 2';
        $requisitionListDescription = 'Functional Test Requisition List - Description 2';

        $query = $this->getQuery($requisitionListName, $requisitionListDescription);
        $this->graphQlMutation($query, [], '', ['Authorization' => 'Bearer testtoken123']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testCreateRequisitionListInvalidUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'The account sign-in was incorrect or your account is disabled temporarily. '
            .'Please wait and try again later.'
        );

        $requisitionListName = 'Functional Test Requisition List - Name 2';
        $requisitionListDescription = 'Functional Test Requisition List - Description 2';

        $query = $this->getQuery($requisitionListName, $requisitionListDescription);
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
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testCreateRequisitionListWithoutName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Specify the "name" value.');

        $requisitionListName = '';
        $requisitionListDescription = 'Functional Test Requisition List - Description 2';

        $query = $this->getQuery($requisitionListName, $requisitionListDescription);
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
     * @param string $requisitionListName
     * @param string $requisitionListDescription
     * @return string
     */
    private function getQuery(
        string $requisitionListName,
        string $requisitionListDescription
    ): string {
        return <<<MUTATION
mutation {
  createRequisitionList(
    input: {
        name:"{$requisitionListName}"
        description:"{$requisitionListDescription}"
   }
) {
    requisition_list {
        uid
        name
        description
    }
  }
}
MUTATION;
    }
}
