<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\Role\Permission;
use Magento\CompanyGraphQl\Model\Company\Users;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company user updating
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UpdateCompanyUserTest extends GraphQlAbstract
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
     * @var SearchCriteriaBuilder $searchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var StructureManager
     */
    private $structureManager;

    /**
     * @var Permission
     */
    private $permission;

    /**
     * @var TeamRepositoryInterface
     */
    private $teamRepository;

    /**
     * @var Uid
     */
    private $idEncoder;

    /**
     * @var Users\Formatter
     */
    private $formatter;

    /**
     * Setup
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);
        $this->structureManager = $this->objectManager->get(StructureManager::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->permission = $this->objectManager->get(Permission::class);
        $this->teamRepository = $this->objectManager->get(TeamRepositoryInterface::class);
        $this->idEncoder = $this->objectManager->get(Uid::class);
        $this->formatter = $this->objectManager->get(Users\Formatter::class);
    }

    /**
     * Test unauthorized access
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $roleName = 'new custom company role';
        $roleId = $this->idEncoder->encode((string)$this->getRoleByName($roleName)->getId());
        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$userId}"
      job_title: "updated_user"
      role_id: "{$roleId}"
      firstname: "Updated"
      lastname: "Updated"
      email: "testing_update@email.com"
      telephone: "15156614400"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
      role{
        id
        name
        users_count
        permissions{
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
      team {
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Test company user updating
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdate()
    {
        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));
        $roleName = 'new custom company role';
        $role = $this->getRoleByName($roleName);
        $roleId = $this->idEncoder->encode((string)$role->getId());
        $jobTitle = 'updated_user';
        $email = 'testing_update@email.com';
        $telephone = '1515661111';
        $firstname = 'Updated';
        $lastname = 'Updated';

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$userId}"
      job_title: "{$jobTitle}"
      role_id: "{$roleId}"
      firstname: "{$firstname}"
      lastname: "{$lastname}"
      email: "{$email}"
      telephone: "{$telephone}"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
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
      team{
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );

        self::assertEquals($jobTitle, $response['updateCompanyUser']['user']['job_title']);
        self::assertEquals($firstname, $response['updateCompanyUser']['user']['firstname']);
        self::assertEquals($lastname, $response['updateCompanyUser']['user']['lastname']);
        self::assertEquals($telephone, $response['updateCompanyUser']['user']['telephone']);
        self::assertEquals(Users::STATUS_INACTIVE, $response['updateCompanyUser']['user']['status']);
        self::assertEquals(
            $this->idEncoder->encode((string)$role->getId()),
            $response['updateCompanyUser']['user']['role']['id']
        );
        self::assertEquals($role->getRoleName(), $response['updateCompanyUser']['user']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($role->getId()),
            $response['updateCompanyUser']['user']['role']['users_count']
        );
        $this->validateAclResource($response['updateCompanyUser']['user']['role']['permissions']);
        self::assertNull($response['updateCompanyUser']['user']['team']);
    }

    /**
     * Test company user updating without id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdateWithoutId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field CompanyUserUpdateInput.id of required type ID! was not provided.');

        $roleName = 'new custom company role';
        $roleId = $this->idEncoder->encode((string)$this->getRoleByName($roleName)->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      job_title: "updated_user"
      role_id: "{$roleId}"
      firstname: "Updated"
      lastname: "Updated"
      email: "testing_update@email.com"
      telephone: "15156614400"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
      role{
        id
        name
        users_count
        permissions{
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
      team {
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Test company user updating with wrong permissions
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdateWithWrongPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));
        $role = $this->getRoleByName('new custom company role');
        $roleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$userId}"
      job_title: "updated_user"
      role_id: "{$roleId}"
      firstname: "Updated"
      lastname: "Updated"
      email: "testing_update@email.com"
      telephone: "1515661111"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
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
      team{
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('test@example.com', 'password')
        );
    }

    /**
     * Test company user updating without required fields
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdateWithoutRequiredfields()
    {
        $userId = $this->getUserIdByEmail('veronica.costello@example.com');
        $encodedUserId = $this->idEncoder->encode((string)$userId);

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$encodedUserId}"
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
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
      team{
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );

        self::assertEquals('Sales Rep', $response['updateCompanyUser']['user']['job_title']);
        self::assertEquals('Veronica', $response['updateCompanyUser']['user']['firstname']);
        self::assertEquals('Costello', $response['updateCompanyUser']['user']['lastname']);
        self::assertEquals('549583943048', $response['updateCompanyUser']['user']['telephone']);
        self::assertEquals(Users::STATUS_ACTIVE, $response['updateCompanyUser']['user']['status']);
        self::assertEquals('custom company role', $response['updateCompanyUser']['user']['role']['name']);
        self::assertNull($response['updateCompanyUser']['user']['team']);
    }

    /**
     * Test company user updating with not encoded id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdateWithNotEncodedId()
    {
        $userId = $this->getUserIdByEmail('veronica.costello@example.com');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $userId . '" is incorrect.');

        $role = $this->getRoleByName('new custom company role');
        $roleId = $this->idEncoder->encode((string)$role->getId());

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$userId}"
      job_title: "updated_user"
      role_id: "{$roleId}"
      firstname: "Updated"
      lastname: "Updated"
      email: "testing_update@email.com"
      telephone: "1515661111"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
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
      team{
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Test company user updating with not encoded roleId
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testUpdateWithNotEncodedRoleId()
    {
        $userId = $this->idEncoder->encode((string)$this->getUserIdByEmail('veronica.costello@example.com'));
        $role = $this->getRoleByName('new custom company role');
        $roleId = $role->getId();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $roleId . '" is incorrect.');

        $mutation = <<<MUTATION
mutation {
  updateCompanyUser(
    input: {
      id: "{$userId}"
      job_title: "updated_user"
      role_id: "{$roleId}"
      firstname: "Updated"
      lastname: "Updated"
      email: "testing_update@email.com"
      telephone: "1515661111"
	  status: INACTIVE
    }
  ) {
    user {
      id
      firstname
      lastname
      email
      telephone
      status
      job_title
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
      team{
        id
        name
        description
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }

    /**
     * Get user's id by email
     *
     * @param string $email
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getUserIdByEmail(string $email)
    {
        return $this->customerRepository->get($email)->getId();
    }

    /**
     * Get role object by role name
     *
     * @param string $roleName
     * @return RoleInterface
     * @throws LocalizedException
     */
    private function getRoleByName(string $roleName): RoleInterface
    {
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName);
        /** @var SearchResults $results */
        $results = $this->roleRepository->getList($this->searchCriteriaBuilder->create());
        /** @var RoleInterface[] $items */
        $items = $results->getItems();
        /** @var RoleInterface $team */
        return current(array_values($items));
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
