<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Service\V1;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Test\Fixture\Category;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\CategoryManagementInterface;
use Magento\SharedCatalog\Api\ProductItemRepositoryInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Tests for shared catalog products actions (assign, unassign, getting).
 */
class ProductManagementTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogProductManagementV1';

    private const SERVICE_VERSION = 'V1';

    /**
     * @var CategoryManagementInterface
     */
    private $categoryManagement;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryManagement = $this->objectManager->get(CategoryManagementInterface::class);
    }

    /**
     * Check list of product SKUs in the selected shared catalog.
     *
     * @return void
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
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
        DataFixture(Category::class, as: 'category'),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(ProductFixture::class, as: 'product_01'),
        DataFixture(ProductFixture::class, as: 'product_02'),
        DataFixture(ProductFixture::class, as: 'simple_10'),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$product_00.id$',
                    '$product_01.id$',
                    '$product_02.id$',
                    '$simple_10.id$',
                ],
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
    public function testGetProducts()
    {
        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

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

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/products', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getProducts',
            ],
        ];
        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

        $respProductSkus = $this->_webApiCall($serviceInfo, ['id' => $sharedCatalog->getId()]);
        $expectedResult = [
            $product00->getSku(),
            $product01->getSku(),
            $product02->getSku(),
            $simple10->getSku()
        ];
        $this->assertEquals($respProductSkus, $expectedResult, 'List of products is wrong.');
    }

    /**
     * Test assign products to shared catalog.
     *
     * @return void
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
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
        DataFixture(Category::class, as: 'category'),
        DataFixture(ProductFixture::class, as: 'product_00'),
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
    public function testAssignProducts()
    {
        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/assignProducts', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'assignProducts',
            ],
        ];

        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

        $products = $this->getProducts([$product00->getSku()]);

        $params = [
            'id' => $sharedCatalog->getId(),
            'products' => $this->prepareItems($products, 'sku')
        ];
        $resp = $this->_webApiCall($serviceInfo, $params);
        $this->assertTrue($resp);
        $assignedProductSkus = $this->retrieveAssignedProductSkus($sharedCatalog->getCustomerGroupId());
        $this->assertEquals(
            $this->prepareItems($products, 'sku'),
            $this->prepareItems($assignedProductSkus, 'sku'),
            'Products are not assigned.'
        );
    }

    /**
     * Test unassigned products from shared catalog.
     *
     * @return void
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
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
        DataFixture(Category::class, as: 'category'),
        DataFixture(ProductFixture::class, as: 'product_00'),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => ['$product_00.id$'],
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
    public function testUnassignProducts()
    {
        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        /** @var \Magento\Catalog\Model\Product $product00 */
        $product00= DataFixtureStorageManager::getStorage()->get('product_00');

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/unassignProducts', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'unassignProducts',
            ],
        ];
        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);
        $products = $this->getProducts([$product00->getSku()]);

        $params = [
            'id' => $sharedCatalog->getId(),
            'products' => $this->prepareItems($products, 'sku')
        ];
        $resp = $this->_webApiCall($serviceInfo, $params);
        $this->assertTrue($resp);
        $assignProductSkus = $this->retrieveAssignedProductSkus($sharedCatalog->getCustomerGroupId());
        $this->assertEmpty($assignProductSkus);
    }

    /**
     * Test unassign product with invalid sku from shared catalog.
     *
     * @return void
     * magentoApiDataFixture Magento/SharedCatalog/_files/shared_catalog.php
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
    ]
    public function testUnassignProductsWithInvalidSku()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Requested product doesn\'t exist: %sku.');

        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/unassignProducts', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'unassignProducts',
            ],
        ];

        $params = [
            'id' => $sharedCatalog->getId(),
            'products' => [['sku' => 'nonexistent']]
        ];
        $this->_webApiCall($serviceInfo, $params);
    }

    /**
     * Retrieve assigned products sku.
     *
     * @param int $customerGroupId
     * @return array
     */
    private function retrieveAssignedProductSkus($customerGroupId)
    {
        $builder = $this->objectManager->get(
            SearchCriteriaBuilder::class
        );
        $builder->addFilter('customer_group_id', $customerGroupId);
        $products = $this->objectManager->create(ProductItemRepositoryInterface::class);
        return $products->getList($builder->create())->getItems();
    }

    /**
     * Get products.
     *
     * @return DataObject[]
     */
    private function getProducts(array $productSku)
    {
        /** @var Collection $collection */
        $collection = $this->objectManager->create(Collection::class);
        $collection->addAttributeToFilter('sku', ['in' => $productSku]);

        return $collection->getItems();
    }
}
