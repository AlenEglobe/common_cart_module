<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Model\Role\Permission;
use Magento\CompanyGraphQl\Model\Company\Role\ValidateRole;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test for CreateCompanyRole resolver
 */
class CreateCompanyRoleTest extends GraphQlAbstract
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
  createCompanyRole(
    input: {
      name: "test_role"
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

        $this->graphQlMutation($mutation);
    }

    /**
     * Test company role creating
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testCreate()
    {
        $roleName = 'Test role';

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input: {
      name: "Test role"
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

        self::assertNotEmpty($response['createCompanyRole']['role']['id']);
        self::assertEquals($roleName, $response['createCompanyRole']['role']['name']);

        if (isset($response['createCompanyRole']['role']['id'])) {
            $usersCount = $this->permission->getRoleUsersCount(
                $this->idEncoder->decode($response['createCompanyRole']['role']['id'])
            );
            self::assertEquals($usersCount, $response['createCompanyRole']['role']['users_count']);
        }

        $this->validateAclResource($response['createCompanyRole']['role']['permissions']);

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

        self::assertEqualsCanonicalizing($expectedPermissions, $response['createCompanyRole']['role']['permissions']);
    }

    /**
     * Test role creating with missing name
     */
    public function testCreateWithoutName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field CompanyRoleCreateInput.name of required type String! was not provided.');

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input: {
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

        $this->graphQlMutation($mutation);
    }

    /**
     * Test role creating with missing permissions
     */
    public function testCreateWithoutPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Field CompanyRoleCreateInput.permissions of required type [String]! was not provided.'
        );

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input: {
    	name: "Invalid role"
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
    public function testCreateWithInvalidPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid role permission resources: invalid.');

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input:
    {
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
     * Test role creating with too long name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_roles.php
     */
    public function testCreateWithTooLongName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Field name cannot be longer than ' . ValidateRole::ROLE_NAME_LENGTH . ' characters'
        );

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input: {
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
    public function testCreateWithExistingName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User role with this name already exists.' .
            ' Enter a different name to save this role.');

        $mutation = <<<MUTATION
mutation {
  createCompanyRole(
    input: {
      name: "Role C"
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
