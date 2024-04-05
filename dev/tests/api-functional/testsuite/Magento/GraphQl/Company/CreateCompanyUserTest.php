<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\TeamInterface;
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
 * Test company user creating
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateCompanyUserTest extends GraphQlAbstract
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
    }

    /**
     * Test unauthorized access
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $roleName = 'custom company role';
        $roleId = $this->idEncoder->encode((string)$this->getRoleByName($roleName)->getId());

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      role_id: "{$roleId}"
      firstname: "Test"
      lastname: "Testing"
      email: "testing@email.com"
      telephone: "15156614488"
	  status: ACTIVE
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
     * Test company user creating
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreate()
    {
        $roleName = 'custom company role';
        $role = $this->getRoleByName($roleName);
        $roleId = $this->idEncoder->encode((string)$role->getId());
        $team = $this->getTeamByName('Test team');
        $targetId = $this->idEncoder->encode(
            (string)$this->structureManager->getStructureByTeamId($team->getId())->getId()
        );

        $jobTitle = 'test_user';
        $email = 'test1@email.com';
        $telephone = '15156614488';
        $firstname = 'Oleksandr';
        $lastname = 'Melnyk';

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "{$jobTitle}"
      role_id: "{$roleId}"
      firstname: "{$firstname}"
      lastname: "{$lastname}"
      email: "{$email}"
      telephone: "{$telephone}"
	  status: ACTIVE
	  target_id: "{$targetId}"
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

        self::assertEquals($jobTitle, $response['createCompanyUser']['user']['job_title']);
        self::assertEquals($firstname, $response['createCompanyUser']['user']['firstname']);
        self::assertEquals($lastname, $response['createCompanyUser']['user']['lastname']);
        self::assertEquals($telephone, $response['createCompanyUser']['user']['telephone']);
        self::assertEquals(Users::STATUS_ACTIVE, $response['createCompanyUser']['user']['status']);
        self::assertEquals($roleId, $response['createCompanyUser']['user']['role']['id']);
        self::assertEquals($role->getRoleName(), $response['createCompanyUser']['user']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($role->getId()),
            $response['createCompanyUser']['user']['role']['users_count']
        );
        $this->validateAclResource($response['createCompanyUser']['user']['role']['permissions']);

        $team = $this->structureManager->getTeamByCustomerId($this->getUserIdByEmail($email));
        $teamId = $this->idEncoder->encode((string)$team->getId());

        $expectedTeam = [
                'id' => $teamId,
                'name' => 'Test team',
                'description' => 'Test team description'
        ];

        self::assertEqualsCanonicalizing($expectedTeam, $response['createCompanyUser']['user']['team']);

        $expectedPermissions = [
            0 => [
                'id' => 'Magento_Company::index',
                'sort_order' => 100,
                'children' => [
                    4 => [
                        'id' => 'Magento_Company::user_management',
                        'text' => "Company User Management",
                        'sort_order' => 200,
                        'children' => []
                    ]
                ],
                'text' => 'All'
            ]
        ];

        self::assertEqualsCanonicalizing(
            $expectedPermissions,
            $response['createCompanyUser']['user']['role']['permissions']
        );
    }

    /**
     * Test company user creating without target_id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     */
    public function testCreateWithoutTargetId()
    {
        $roleName = 'custom company role';
        $role = $this->getRoleByName($roleName);
        $roleId = $this->idEncoder->encode((string)$role->getId());
        $jobTitle = 'test_user';
        $email = 'test2@email.com';
        $telephone = '15156614488';
        $firstname = 'Oleksandr';
        $lastname = 'Melnyk';

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "{$jobTitle}"
      role_id: "{$roleId}"
      firstname: "{$firstname}"
      lastname: "{$lastname}"
      email: "{$email}"
      telephone: "{$telephone}"
	  status: ACTIVE
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
            $this->getCustomerAuthenticationHeader->execute('customrole@company.com', 'password')
        );

        self::assertEquals($jobTitle, $response['createCompanyUser']['user']['job_title']);
        self::assertEquals($firstname, $response['createCompanyUser']['user']['firstname']);
        self::assertEquals($lastname, $response['createCompanyUser']['user']['lastname']);
        self::assertEquals($telephone, $response['createCompanyUser']['user']['telephone']);
        self::assertEquals(Users::STATUS_ACTIVE, $response['createCompanyUser']['user']['status']);
        self::assertEquals($roleId, $response['createCompanyUser']['user']['role']['id']);
        self::assertEquals($role->getRoleName(), $response['createCompanyUser']['user']['role']['name']);
        self::assertEquals(
            $this->permission->getRoleUsersCount($role->getId()),
            $response['createCompanyUser']['user']['role']['users_count']
        );
        $this->validateAclResource($response['createCompanyUser']['user']['role']['permissions']);
        self::assertNull($response['createCompanyUser']['user']['team']);
    }

    /**
     * Test company user creating with wrong permissions
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreateWithWrongPermissions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user');

        $roleId = $this->idEncoder->encode((string)$this->getRoleByName('custom company role')->getId());

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      role_id: "{$roleId}"
      firstname: "Oleksandr"
      lastname: "Melnyk"
      email: "test2@email.com"
      telephone: "15156614488"
	  status: ACTIVE
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
     * Test company user creating with existing customer
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreateExistingCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invitation was sent to an existing customer, '
            . 'they will be added to your organization once they accept the invitation.');

        $roleId = $this->idEncoder->encode((string)$this->getRoleByName('custom company role')->getId());

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      role_id: "{$roleId}"
      firstname: "Firstname"
      lastname: "Lastname"
      email: "test@example.com"
      telephone: "15156614488"
	  status: ACTIVE
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
     * Test company user creating with existing company user
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreateExistingCompanyUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A customer with the same email already assigned to company.');

        $roleId = $this->idEncoder->encode((string)$this->getRoleByName('custom company role')->getId());

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      role_id: "{$roleId}"
      firstname: "Firstname"
      lastname: "Lastname"
      email: "veronica.costello@example.com"
      telephone: "15156614488"
	  status: ACTIVE
    }
  ) {
    user {
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
     * Test company user creating with missing data
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreateWithMissingData()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field CompanyUserCreateInput.role_id of required type ID! was not provided.');

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      firstname: "Firstname"
      lastname: "Lastname"
      email: "veronica.costello@example.com"
      telephone: "15156614488"
	  status: ACTIVE
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
     * Test company user creating with not encoded role_id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure_and_role.php
     */
    public function testCreateWithNotEncodedRoleId()
    {
        $roleId = $this->getRoleByName('custom company role')->getId();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $roleId . '" is incorrect.');

        $mutation = <<<MUTATION
mutation {
  createCompanyUser(
    input: {
      job_title: "test_user"
      role_id: {$roleId}
      firstname: "Oleksandr"
      lastname: "Melnyk"
      email: "test2@email.com"
      telephone: "15156614488"
	  status: ACTIVE
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

    /**
     * Get team object by team name
     *
     * @param string $name
     * @return TeamInterface
     * @throws LocalizedException
     */
    private function getTeamByName(string $name): TeamInterface
    {
        $this->searchCriteriaBuilder->addFilter('name', $name);
        /** @var SearchResults $results */
        $results = $this->teamRepository->getList($this->searchCriteriaBuilder->create());
        /** @var TeamInterface[] $items */
        $items = $results->getItems();
        /** @var TeamInterface $team */
        return current(array_values($items));
    }

    /**
     * @param string $email
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getUserIdByEmail(string $email)
    {
        return $this->customerRepository->get($email)->getId();
    }
}
