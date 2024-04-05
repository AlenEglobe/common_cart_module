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
 * Test coverage for Update Requisition List
 */
class UpdateRequisitionListTest extends GraphQlAbstract
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
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testUpdateRequisitionList(): void
    {
        $requisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $listId = base64_encode((string)$requisitionListId);
        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";

        $query = $this->getQuery($listId, $requisitionListNewName, $requisitionListNewDescription);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
        $this->assertArrayHasKey('updateRequisitionList', $response);
        $this->assertArrayHasKey('requisition_list', $response['updateRequisitionList']);
        $requisitionListResponse = $response['updateRequisitionList']['requisition_list'];
        $this->assertEquals($requisitionListId, base64_decode($requisitionListResponse['uid']));
        $this->assertEquals($requisitionListNewName, $requisitionListResponse['name']);
        $this->assertEquals($requisitionListNewDescription, $requisitionListResponse['description']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testUpdateRequisitionListGuestUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $requisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $listId = base64_encode((string)$requisitionListId);
        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";

        $query = $this->getQuery($listId, $requisitionListNewName, $requisitionListNewDescription);
        $this->graphQlMutation($query, [], '', ['Authorization' => 'Bearer testtoken123']);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testUpdateRequisitionListInvalidUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'The account sign-in was incorrect or your account is disabled temporarily. '
            . 'Please wait and try again later.'
        );

        $requisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $listId = base64_encode((string)$requisitionListId);
        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";

        $query = $this->getQuery($listId, $requisitionListNewName, $requisitionListNewDescription);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication('customer@example.com', '123456'));
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     */
    public function testUpdateRequisitionListWithoutId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Specify the "requisitionListUid" value.');

        $requisitionListId = '';
        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";

        $query = $this->getQuery($requisitionListId, $requisitionListNewName, $requisitionListNewDescription);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     */
    public function testUpdateRequisitionListWithInvalidId()
    {
        $requisitionListId = 9999;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No such entity with id = ' . $requisitionListId);

        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";
        $listId = base64_encode((string)$requisitionListId);
        $query = $this->getQuery($listId, $requisitionListNewName, $requisitionListNewDescription);
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/Customer/_files/two_customers.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_with_id.php
     */
    public function testUpdateRequisitionListCorrectIdUnauthorizedUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current user cannot perform operations on requisition list');

        $requisitionListId = $this->getRequisitionList->execute('Test - Requisition List');
        $listId = base64_encode((string)$requisitionListId);
        $requisitionListNewName = "Functional Test Requisition List - New Name";
        $requisitionListNewDescription = "Functional Test Requisition List - New Description";

        $query = $this->getQuery($listId, $requisitionListNewName, $requisitionListNewDescription);
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
     * @param string $requisitionListNewName
     * @param string $requisitionListNewDescription
     * @return string
     */
    private function getQuery(
        string $requisitionListId,
        string $requisitionListNewName,
        string $requisitionListNewDescription
    ): string {
        return <<<MUTATION
mutation {
    updateRequisitionList(
    requisitionListUid:"{$requisitionListId}"
    input: {
        name:"{$requisitionListNewName}"
        description:"{$requisitionListNewDescription}"
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
