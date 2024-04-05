<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\SharedCatalog;

use Magento\Catalog\Test\Fixture\Category;
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionsIndexer;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\SharedCatalog\Test\Fixture\AssignCategory as AssignCategorySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignCompany as AssignCompanySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\ObjectManager;

/**
 * Filter category list test
 */
class CategoryListTest extends GraphQlAbstract
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * Response needs to have exact count and category by name
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(
            AssignCategorySharedCatalog::class,
            [
                'category' => '$category$',
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
        DataFixture(
            AssignCompanySharedCatalog::class,
            [
                'company' => '$company$',
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
        DataFixture(
            Permission::class,
            [
                'category_id' => '$category.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_DENY,
                'grant_checkout_items' => PermissionModel::PERMISSION_DENY
            ]
        )
    ]
    public function testCategoriesReturnedForCompany()
    {
        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        $response = $this->graphQlQuery(
            $this->getQuery($category->getName()),
            [],
            '',
            /** @var \Magento\Customer\Model\Customer $customer */
            $this->objectManager->get(GetCustomerAuthenticationHeader::class)->execute($companyAdmin->getEmail())
        );

        $this->assertCount(1, $response['categoryList']);
        $this->assertEquals($category->getName(), $response['categoryList'][0]['name']);
    }

    /**
     * Get category list query
     *
     * @param string $catalogName
     * @return string
     */
    private function getQuery(string $catalogName): string
    {
        return <<<QUERY
{
  categoryList(filters: {name: {match: "{$catalogName}"}}){
    id
    name
  }
}
QUERY;
    }
}
