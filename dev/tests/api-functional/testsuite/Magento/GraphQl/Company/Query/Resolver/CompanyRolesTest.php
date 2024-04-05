<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query\Resolver;

use Magento\Company\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test company roles resolver
 */
class CompanyRolesTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var RoleCollectionFactory
     */
    private $roleCollectionFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->fixtures = DataFixtureStorageManager::getStorage();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->roleCollectionFactory = $objectManager->get(RoleCollectionFactory::class);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRoles(): void
    {
        $query = <<<QUERY
{
  company {
    roles (pageSize:10, currentPage: 1) {
      items {
        name
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

        $response = $this->executeQuery($query);
        foreach ($response['company']['roles']['items'] as $item) {
            $this->validateAclResource($item['permissions']);
        }
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        DataFixture(Customer::class, ['email' => 'john.doe@example.com' ], as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(
            Role::class,
            [
                'company_id' => '$company.entity_id$',
                'role_name' => 'New 1'
            ],
            'role_administrator'
        ),
        DataFixture(
            Role::class,
            [
                'company_id' => '$company.entity_id$',
                'role_name' => 'New 2'
            ],
            'role_administrator'
        ),
    ]
    public function testCompanyRolesPageInfo()
    {
        $pageSize = 1;
        $currentPage = 1;
        $query = <<<QUERY
        query{
            company{
                roles (pageSize:$pageSize, currentPage: $currentPage) {
                    items {
                    name
                    }
                    total_count
                    page_info {
                      total_pages
                      current_page
                    }
                }
             }
        }
QUERY;
        $response = $this->executeQuery($query);
        $company = $this->fixtures->get('company');
        $companyRoles = $this->roleCollectionFactory->create()
            ->addFieldToFilter('company_id', $company->getId())
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);
        $companyRolesTotalCount = $companyRoles->getSize();
        $totalPages = (int)ceil($companyRolesTotalCount / $pageSize);
        $this->assertEquals($companyRolesTotalCount, $response['company']['roles']['total_count']);
        $this->assertEquals($totalPages, $response['company']['roles']['page_info']['total_pages']);
    }

    /**
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
     * @param $query
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery($query)
    {
        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }
}
