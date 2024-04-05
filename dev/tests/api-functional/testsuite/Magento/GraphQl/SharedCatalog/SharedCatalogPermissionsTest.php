<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\SharedCatalog;

use Magento\SharedCatalog\Test\Fixture\AssignProductsCategory;
use Magento\Catalog\Test\Fixture\Category;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\Company\Test\Fixture\Company;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
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
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionsIndexer;
use Magento\TestFramework\ObjectManager;
use Magento\Framework\GraphQl\Query\Uid;

/**
 * Filter category list test
 */
class SharedCatalogPermissionsTest extends GraphQlAbstract
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
        DataFixture(Category::class, as: 'parent'),
        DataFixture(
            Category::class,
            [
                'parent_id' => '$parent.id$'
            ],
            as: 'category'
        ),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
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
                    '$simple_10.id$',
                    '$configurable.id$'
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
        DataFixture(
            AssignCategorySharedCatalog::class,
            [
                'category' => '$parent$',
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
                'category_id' => '$parent.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_ALLOW
            ]
        )
    ]
    public function testCategoryListCountRightChildrenAndProducts()
    {
        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        /** @var \Magento\Catalog\Model\Category $parentCategory */
        $parentCategory = DataFixtureStorageManager::getStorage()->get('parent');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        /** @var \Magento\Catalog\Model\Product $product01 */
        $product01= DataFixtureStorageManager::getStorage()->get('product_01');

        /** @var \Magento\Catalog\Model\Product $simple10 */
        $simple10= DataFixtureStorageManager::getStorage()->get('simple_10');

        /** @var \Magento\Catalog\Model\Product $configurable */
        $configurable= DataFixtureStorageManager::getStorage()->get('configurable');

        $response = $this->graphQlQuery(
            $this->getListQuery($parentCategory->getEntityId()),
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute($companyAdmin->getEmail())
        );

        $expected = [
            "categoryList" => [
                [
                    "uid" => (new Uid)->encode($parentCategory->getEntityId()),
                    "name" => $parentCategory->getName(),
                    "product_count"=> 4,
                    "children_count"=> "1",
                    "children" => [
                        [
                            "product_count" => 4,
                            "name" => $category->getName(),
                            "products" => [
                                "items" => [
                                    [
                                        "sku"=> $configurable->getSku(),
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
                                        "sku"=> $simple10->getSku(),
                                        "name" => $simple10->getName(),
                                    ],
                                    [
                                        "sku"=> $product01->getSku(),
                                        "name" => $product01->getName(),
                                    ],
                                    [
                                        "sku"=> $product00->getSku(),
                                        "name" => $product00->getName(),
                                    ]
                                ],
                            ],
                        ]
                    ],
                    "products"=> [
                        "items" => [
                            ["sku" => $configurable->getSku()],
                            ["sku" => $simple10->getSku()],
                            ["sku" => $product01->getSku()],
                            ["sku" => $product00->getSku()]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);
    }

    /**
     * Get category list query with children and products_count
     *
     * @param string $categoryId
     * @return string
     */
    private function getListQuery(string $categoryId): string
    {
        return <<<QUERY
{
  categoryList(filters: {ids: {eq: "{$categoryId}"}}) {
    uid
    name
    product_count
    children_count
    children{
      product_count
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
    products{
      items{
        sku
      }
    }
  }
}
QUERY;
    }
}
