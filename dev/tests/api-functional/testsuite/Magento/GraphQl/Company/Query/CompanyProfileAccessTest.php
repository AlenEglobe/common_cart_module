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
 * Test company profile resources access
 */
class CompanyProfileAccessTest extends GraphQlAbstract
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
    public function testCompanyViewAccess(): void
    {
        $this->markTestSkipped();
        $deniedPermissions = [
            'Magento_Company::view',
            'Magento_Company::view_account',
            'Magento_Company::edit_account',
            'Magento_Company::view_address',
            'Magento_Company::edit_address',
            'Magento_Company::contacts',
            'Magento_Company::payment_information',
        ];

        $query = <<<QUERY
{
  company {
    name
    legal_address {
      city
    }
    company_admin {
      addresses {
        city
        firstname
        lastname
      }
    }
    sales_representative {
      email
    }
    payment_methods
  }
}
QUERY;

        $this->verifyResourceAccess($deniedPermissions, $query);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyViewAccountAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::view_account',
            'Magento_Company::edit_account',
        ];

        $query = <<<QUERY
{
  company {
    name
  }
}
QUERY;

        $this->verifyResourceAccess($deniedPermissions, $query);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyViewAddressAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::view_address',
            'Magento_Company::edit_address',
        ];

        $query = <<<QUERY
{
  company {
    legal_address {
      city
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
    public function testCompanyContactsAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::contacts'
        ];

        $query = <<<QUERY
{
  company {
    company_admin {
      addresses {
        city
        firstname
        lastname
      }
    }
    sales_representative {
      email
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
    public function testCompanyPaymentInformationAccess(): void
    {
        $deniedPermissions = [
            'Magento_Company::payment_information'
        ];

        $query = <<<QUERY
{
  company {
    payment_methods
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
