<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query;

use Magento\Company\Api\RoleManagementInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Permission\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company structure resources access
 */
class CompanyUsersAccessTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @var RoleManagementInterface
     */
    private $roleManagement;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var PermissionManagementInterface
     */
    private $permissionManagement;

    /**
     * @var CollectionFactory
     */
    private $permissionCollection;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->roleManagement = $objectManager->get(RoleManagementInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->permissionManagement = $objectManager->get(PermissionManagementInterface::class);
        $this->permissionCollection = $objectManager->get(CollectionFactory::class);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyUsersAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::users_view',
            'Magento_Company::users_edit',
        ];

        $query = <<<QUERY
{
  company {
    users (pageSize:10, currentPage:1) {
      items {
        addresses {
          city
          company
          country_code
          default_billing
          default_shipping
        }
      }
      total_count
      page_info {
        page_size
        current_page
      }
    }
  }
}
QUERY;

        $this->verifyResourceAccess($deniedPermissions, $query);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRolesAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::roles_view',
            'Magento_Company::roles_edit',
        ];

        $query = <<<QUERY
{
  company {
    roles (pageSize:10, currentPage: 1) {
      items {
        name
        users_count
      }
      page_info {
        page_size
        current_page
        total_pages
      }
    }
  }
}
QUERY;

        $this->verifyResourceAccess($deniedPermissions, $query);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyUserManagementAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::user_management',
            'Magento_Company::users_view',
            'Magento_Company::users_edit',
            'Magento_Company::roles_view',
            'Magento_Company::roles_edit',
        ];

        $query = <<<QUERY
{
  company {
    users (pageSize:10, currentPage:1) {
      items {
        addresses {
          city
          company
          country_code
          default_billing
          default_shipping
        }
      }
      total_count
      page_info {
        page_size
        current_page
      }
    }
    roles (pageSize:10, currentPage: 1) {
      items {
        name
        users_count
      }
      page_info {
        page_size
        current_page
        total_pages
      }
    }
  }
}
QUERY;

        $this->verifyResourceAccess($deniedPermissions, $query);
    }

    /**
     * Update resource access
     *
     * @param $permissions
     * @param $query
     * @throws AuthenticationException
     */
    private function verifyResourceAccess($permissions, $query): void
    {
        $customer = $this->customerRepository->get('veronica.costello@example.com');
        $defaultRole = $this->roleManagement->getCompanyDefaultRole(
            $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $rolePermissions = $this->permissionCollection
            ->create()
            ->addFieldToFilter('role_id', ['eq' => $defaultRole->getId()])
            ->getColumnValues('resource_id');

        // Disable access
        foreach ($permissions as $permission) {
            if (in_array($permission, $rolePermissions, true)) {
                $key = array_search($permission, $rolePermissions, true);
                unset($rolePermissions[$key]);
            }
        }

        $defaultRole->setPermissions($this->permissionManagement->populatePermissions($rolePermissions));
        $this->roleRepository->save($defaultRole);

        $expectedMessage = 'You do not have authorization to perform this action.';

        try {
            $this->executeQuery($query);
            self::fail('Response should contains errors.');
        } catch (ResponseContainsErrorsException $e) {
            $responseData = $e->getResponseData();
            self::assertEquals($expectedMessage, $responseData['errors'][0]['message']);
        }
    }

    /**
     * @param $query
     * @throws AuthenticationException
     */
    private function executeQuery($query): void
    {
        $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute('veronica.costello@example.com', 'password')
        );
    }
}
