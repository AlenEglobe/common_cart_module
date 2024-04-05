<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Role\Permission;
use Magento\CompanyGraphQl\Model\Company\Role\ValidateRole;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test for UpdateCompanyRole resolver
 */
class UpdateCompanyRoleTest extends GraphQlAbstract
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
        $this->permission = $this->objectManager->create(Permission::class);
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
  updateCompanyRole(
    input: {
      id: 1
      name: "edit_role"
      permissions: [
        "Magento_Company::index"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test role creating with missing name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdateWithoutName()
    {
        $roleName = 'Role A';
        $role = $this->findRoleByName($roleName);
        $roleId = $role->getId();
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
        id: "{$encodedRoleId}"
    	permissions: [
        "Magento_Company::view"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
        self::assertEquals($encodedRoleId, $response['updateCompanyRole']['role']['id']);
        self::assertEquals($roleName, $response['updateCompanyRole']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($roleId),
            $response['updateCompanyRole']['role']['users_count']
        );
        $this->validateAclResource($response['updateCompanyRole']['role']['permissions']);
    }

    /**
     * Test role creating with missing name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdateWithoutPermissions()
    {
        $role = $this->findRoleByName('Role B');
        $roleId = $role->getId();
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
        id: "{$encodedRoleId}"
        name: "edited_role B"
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
        self::assertEquals($encodedRoleId, $response['updateCompanyRole']['role']['id']);
        self::assertEquals('edited_role B', $response['updateCompanyRole']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($roleId),
            $response['updateCompanyRole']['role']['users_count']
        );
        $this->validateAclResource($response['updateCompanyRole']['role']['permissions']);

        $expectedPermissions = [
            0 => [
                'id' => 'Magento_Company::index',
                'sort_order' => 100,
                'children' => [
                    3 => [
                        'id' => 'Magento_Company::view',
                        'text' => 'Company Profile',
                        'sort_order' => 100,
                        'children' => []
                    ]
                ],
                'text' => 'All'
            ]
        ];
        self::assertEqualsCanonicalizing($expectedPermissions, $response['updateCompanyRole']['role']['permissions']);
    }

    /**
     * Test company role updating
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdate()
    {
        $roleName = 'edited_role A';
        $role = $this->findRoleByName('Role A');
        $roleId = $role->getId();
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
      id: "{$encodedRoleId}"
      name: "edited_role A"
      permissions: [
        "Magento_Company::user_management"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
  }
}
MUTATION;
        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        self::assertNotEmpty($response['updateCompanyRole']['role']['id']);
        self::assertEquals($roleName, $response['updateCompanyRole']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($roleId),
            $response['updateCompanyRole']['role']['users_count']
        );
        $this->validateAclResource($response['updateCompanyRole']['role']['permissions']);

        $expectedPermissions = [
            0 => [
                'id' => 'Magento_Company::index',
                'sort_order' => 100,
                'children' => [
                    4 => [
                        'id' => 'Magento_Company::user_management',
                        'text' => 'Company User Management',
                        'sort_order' => 200,
                        'children' => []
                    ]
                ],
                'text' => 'All'
            ]
        ];
        self::assertEqualsCanonicalizing($expectedPermissions, $response['updateCompanyRole']['role']['permissions']);
    }

    /**
     * Test role updating with missing id
     */
    public function testUpdateWithoutId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field CompanyRoleUpdateInput.id of required type ID! was not provided.');

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
      name: "Wrong role"
      permissions: [
        "Magento_Company::view"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test role creating with invalid permissions
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdateWithInvalidPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid role permission resources: invalid.');

        $role = $this->findRoleByName('Role B');
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input:
    {
      id: "{$encodedRoleId}"
      name: "Invalid role",
      permissions: [
          "Magento_Company::view",
          "invalid"
      ]
    }
  )
  {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
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
     * Test role updating with too long name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     * @throws LocalizedException
     */
    public function testUpdateWithTooLongName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Field name cannot be longer than ' . ValidateRole::ROLE_NAME_LENGTH . ' characters'
        );

        $role = $this->findRoleByName('Role B');
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
      id: "{$encodedRoleId}"
      name: "too long role name..............end of string"
      permissions: [
        "Magento_Company::view",
        "Magento_Company::view_account"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
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
     * Test role creating with existing role name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdateWithExistingRoleName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User role with this name already exists.' .
            ' Enter a different name to save this role.');

        $role = $this->findRoleByName('Role A');
        $encodedRoleId = $this->idEncoder->encode((string)$role->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input: {
      id: "{$encodedRoleId}"
      name: "Role C"
      permissions: [
        "Magento_Company::user_management"
      ]
    }
  ) {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
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
     * Test role creating with not encoded id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testUpdateWithNotEncodedId()
    {
        $role = $this->findRoleByName('Role B');
        $roleId = $role->getId();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $roleId . '" is incorrect.');

        $mutation = <<<MUTATION
mutation {
  updateCompanyRole(
    input:
    {
      id: {$roleId}
      name: "Invalid role"
    }
  )
  {
    role {
      id
      name
      users_count
      permissions {
        children {
          id
          sort_order
          text
          children {
            id
            sort_order
            text
          }
        }
        id
        sort_order
        text
      }
    }
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
     * Get a role object by role name
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

    /**
     * Validate acl resource
     *
     * @param $aclResources
     */
    private function validateAclResource($aclResources): void
    {
        foreach ($aclResources as $aclResource) {
            self::assertArrayHasKey('id', $aclResource);
            self::assertArrayHasKey('sort_order', $aclResource);
            self::assertArrayHasKey('text', $aclResource);

            if (!empty($aclResource['children'])) {
                $this->validateAclResource($aclResource['children']);
            }
        }
    }
}
