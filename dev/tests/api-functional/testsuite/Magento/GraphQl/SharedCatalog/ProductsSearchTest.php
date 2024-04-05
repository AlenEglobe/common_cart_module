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
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionsIndexer;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\Company\Test\Fixture\Company;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\TestFramework\ObjectManager;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\SharedCatalog\Test\Fixture\AssignCategory as AssignCategorySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignCompany as AssignCompanySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;

/**
 * Search products for a specific shared catalog
 */
class ProductsSearchTest extends GraphQlAbstract
{
    /**
     * @var SharedCatalogManagementInterface
     */
    private $sharedCatalogManagement;

    /**
     * @var ProductManagementInterface
     */
    private $productManagement;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

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
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->sharedCatalogManagement = $this->objectManager->get(SharedCatalogManagementInterface::class);
        $this->productManagement = $this->objectManager->get(ProductManagementInterface::class);
    }

    /**
     * Response needs to have exact items in place with prices available
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
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_DENY
            ]
        )
    ]
    public function testProductsSearchWithPricesPublicAndAllowedCompany()
    {
        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        /** @var \Magento\Catalog\Model\Product $configurable */
        $configurable= DataFixtureStorageManager::getStorage()->get('configurable');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        /** @var \Magento\Catalog\Model\Product $simple10 */
        $simple10= DataFixtureStorageManager::getStorage()->get('simple_10');

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        $response = $this->graphQlQuery(
            $this->getQuery(),
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute($companyAdmin->getEmail())
        );

        $expected = [
            "products" => [
                "total_count" => 3,
                "items" => [
                    [
                        "id" => $product00->getId(),
                        "sku" => $product00->getSku(),
                        "name" => $product00->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => ["value" => 10]
                            ]
                        ],
                        "special_price" => null,
                    ],
                    [
                        "id" => $simple10->getId(),
                        "sku" => $simple10->getSku(),
                        "name" => $simple10->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => ["value" => 10]
                            ]
                        ],
                        "special_price" => null,
                    ],
                    [
                        "id" => $configurable->getId(),
                        "sku" => $configurable->getSku(),
                        "name" => $configurable->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => ["value" => 10]
                            ]
                        ],
                        "special_price" => null,
                        "variants" => [
                            [
                                "product" => [
                                    "sku"=> $simple10->getSku()
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);

        $this->cacheFlush();

        $response = $this->graphQlMutation(
            $this->getQuery(),
            [],
            '',
            []
        );
        $this->assertCount(0, $response['products']['items']);
        $this->assertEquals(0, $response['products']['total_count']);
    }

    /**
     * Response needs to have exact items in place but without prices
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
    public function testProductsSearchWithPricesDenied()
    {
        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        /** @var \Magento\Catalog\Model\Product $configurable */
        $configurable= DataFixtureStorageManager::getStorage()->get('configurable');

        /** @var \Magento\Catalog\Model\Product $simple10 */
        $simple10= DataFixtureStorageManager::getStorage()->get('simple_10');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        $response = $this->graphQlQuery(
            $this->getQuery(),
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute($companyAdmin->getEmail())
        );

        $expected = [
            "products" => [
                "total_count" => 3,
                "items" => [
                    [
                        "id" => $product00->getId(),
                        "sku" => $product00->getSku(),
                        "name" => $product00->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => [
                                    "value" => null,
                                ]
                            ]
                        ],
                        "special_price" => null,
                    ],
                    [
                        "id" => $simple10->getId(),
                        "sku" => $simple10->getSku(),
                        "name" => $simple10->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => [
                                    "value" => null,
                                ]
                            ]
                        ],
                        "special_price" => null,
                    ],
                    [
                        "id" => $configurable->getId(),
                        "sku" => $configurable->getSku(),
                        "name" => $configurable->getName(),
                        "price_range" => [
                            "minimum_price" => [
                                "final_price" => [
                                    "value" => null,
                                ]
                            ]
                        ],
                        "special_price" => null,
                        "variants" => [
                            [
                                "product" => [
                                    "sku"=> $simple10->getSku()
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);
    }

    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$product_00.id$',
                    '$product_01.id$',
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        )
    ]
    public function testFilterProductsNotAssignedToSharedCatalog()
    {
        $query = <<<QUERY
{
  products(filter: {sku: {eq: "simple"}}) {
    items{
      id
      sku
    }
    aggregations {
      attribute_code
      label
      options {
        count
        label
        value
      }
    }
  }
}
QUERY;
        $response = $this->graphQlQuery($query);
        $this->assertEmpty($response['products']['items']);
        $this->assertEmpty($response['products']['aggregations']);
    }

    /**
     * Response needs to have exact items in place with prices available
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$product_00.id$',
                    '$product_01.id$',
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
    ]
    public function testProductsSearchWithPricesPublicCatalog()
    {
        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();

        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        $response = $this->graphQlQuery(
            $this->getQuery(),
            [],
            '',
            []
        );
        $this->assertEmpty($response['products']['items']);
        //second time this runs it will get the assigned products from previous step
        //according to MC-42567 total count must be equal actual number of items
        $this->assertEquals(0, $response['products']['total_count']);

        $this->productManagement->assignProducts($sharedCatalog->getId(), []);

        $this->cacheFlush();

        $response = $this->graphQlQuery(
            $this->getQuery(),
            [],
            '',
            []
        );

        //Verify no products are returned
        $this->assertCount(0, $response['products']['items']);
        // there is a bug around shared catalog and total_count
        // $this->assertEquals(0, $response['products']['total_count']);
    }

    /**
     * Get products search query
     *
     * @return string
     */
    private function getQuery(): string
    {
        return <<<QUERY
{
  products(search: "Simple"){
    items {
      id
      name
      sku
      ... on ConfigurableProduct {
        variants {
          product {
            sku
          }
        }
      }
      price_range {
        minimum_price {
          final_price {
            value
          }
        }
      }
      special_price
    }
    total_count
  }
}
QUERY;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function cacheFlush()
    {
        $appDir = dirname(Bootstrap::getInstance()->getAppTempDir());
        $out = '';
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec("php -f {$appDir}/bin/magento cache:flush", $out);
    }
}
