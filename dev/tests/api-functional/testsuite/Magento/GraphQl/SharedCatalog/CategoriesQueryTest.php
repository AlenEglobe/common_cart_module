<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\SharedCatalog;

use Magento\Catalog\Test\Fixture\Category;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionsIndexer;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\Company\Test\Fixture\Company;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\SharedCatalog\Test\Fixture\AssignProductsCategory;
use Magento\SharedCatalog\Test\Fixture\AssignCategory as AssignCategorySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignCompany as AssignCompanySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\ObjectManager;

/**
 * Filter categories query test
 */
class CategoriesQueryTest extends GraphQlAbstract
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
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
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
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
        DataFixture(ProductFixture::class, as: 'product_02'),
        DataFixture(ProductFixture::class, as: 'simple_10'),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$simple_10$']],
            'configurable'
        ),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => [
                    '$product_00$',
                    '$product_01$',
                    '$product_02$',
                    '$simple_10$',
                    '$configurable$'
                ],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$product_00.id$',
                    '$product_01.id$',
                    '$product_02.id$',
                    '$simple_10.id$',
                    '$configurable.id$'
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
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

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        /** @var \Magento\Catalog\Model\Product $product01 */
        $product01= DataFixtureStorageManager::getStorage()->get('product_01');

        /** @var \Magento\Catalog\Model\Product $product02 */
        $product02= DataFixtureStorageManager::getStorage()->get('product_02');

        /** @var \Magento\Catalog\Model\Product $simple10 */
        $simple10= DataFixtureStorageManager::getStorage()->get('simple_10');

        /** @var \Magento\Catalog\Model\Product $configurable */
        $configurable= DataFixtureStorageManager::getStorage()->get('configurable');

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        $response = $this->graphQlQuery(
            $this->getQuery($category->getName()),
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute($companyAdmin->getEmail())
        );

        $expected = [
            "categories" => [
                "items" => [
                    [
                        "id" => (int)$category->getId(),
                        "name" => $category->getName(),
                        "products"=> [
                            "items" => [
                                [
                                    "sku" => $configurable->getSku(),
                                    "name" => $configurable->getName(),
                                    "variants" => [
                                        [
                                            "product" => [
                                                "sku"=> $simple10->getSku()
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    "sku" => $simple10->getSku(),
                                    "name" => $simple10->getName()
                                ],
                                [
                                    "sku" => $product02->getSku(),
                                    "name" => $product02->getName()
                                ],
                                [
                                    "sku" => $product01->getSku(),
                                    "name" => $product01->getName()
                                ],
                                [
                                    "sku" => $product00->getSku(),
                                    "name" => $product00->getName()
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);
    }

    /**
     * Get categories query
     *
     * @param string $catalogName
     * @return string
     */
    private function getQuery(string $catalogName): string
    {
        return <<<QUERY
{
  categories(filters: {name: {match: "{$catalogName}"}}){
    items {
      id
      name
      products{
        items{
          sku
          name
          ... on ConfigurableProduct {
            variants {
              product {
                sku
              }
            }
          }
        }
      }
    }
  }
}
QUERY;
    }
}
