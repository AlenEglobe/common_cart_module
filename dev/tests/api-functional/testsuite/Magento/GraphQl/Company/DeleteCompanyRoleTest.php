<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Role\Permission;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class DeleteCompanyRoleTest extends GraphQlAbstract
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /**
     * @var Permission
     */
    private $permission;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var Uid
     */
    private $idEncoder;

    /**
     * Setup
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->permission = $this->objectManager->get(Permission::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);
        $this->idEncoder = $this->objectManager->get(Uid::class);
    }

    /**
     * Test unauthorized access
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $mutation = <<<MUTATION
mutation {
  deleteCompanyRole(
    id: 1
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test deleting a role without id
     */
    public function testDeleteRoleWithoutId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Field "deleteCompanyRole" argument "id" of type "ID!" is required but not provided.'
        );

        $mutation = <<<MUTATION
mutation {
  deleteCompanyRole {
    success
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test deleting a role with wrong id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testDeleteRoleWithWrongId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "1000000" is incorrect.');

        $mutation = <<<MUTATION
mutation {
  deleteCompanyRole(
    id: 1000000
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
    }

    /**
     * Test deleting a role
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testDelete()
    {
        $role = $this->findRoleByName('Role C');
        $roleId = $this->idEncoder->encode($role->getId());

        $mutation = <<<MUTATION
mutation {
  deleteCompanyRole(
    id: "{$roleId}"
  ) {
    success
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        self::assertEquals(true, $response['deleteCompanyRole']['success']);
    }

    /**
     * Test deleting a role
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testDeleteWithAssignedUser()
    {
        $role = $this->findRoleByName('custom company role');
        $roleId = $this->idEncoder->encode($role->getId());

        $mutation = <<<MUTATION
mutation {
  deleteCompanyRole(
    id: "{$roleId}"
  ) {
    success
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );

        self::assertEquals(false, $response['deleteCompanyRole']['success']);
    }

    /**
     * Find role by name
     *
     * @param string $name
     * @return RoleInterface
     * @throws LocalizedException
     */
    private function findRoleByName(string $name): RoleInterface
    {
        $this->searchCriteriaBuilder->addFilter('role_name', $name);
        /** @var SearchResults $results */
        $results = $this->roleRepository->getList($this->searchCriteriaBuilder->create());
        /** @var RoleInterface[] $items */
        $items = $results->getItems();
        /** @var RoleInterface $role */
        $role = array_values($items)[0];
        return $role;
    }
}
