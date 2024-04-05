<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\SharedCatalog\Api\ProductItemManagementInterface;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
 */
class SharedCatalogProductsLoaderTest extends TestCase
{
    /**
     * @var SharedCatalogProductsLoader
     */
    private $sharedCatalogProductsLoader;

    protected function setUp(): void
    {
        $this->sharedCatalogProductsLoader = Bootstrap::getObjectManager()
            ->create(SharedCatalogProductsLoader::class);
    }

    public function testGetAssignedProductsSkus()
    {
        $customerGroupId = 1;
        $expectedSkus = [
            'simple_product_1',
            'simple_product_2',
            'simple_product_3',
        ];

        $skus = $this->sharedCatalogProductsLoader->getAssignedProductsSkus($customerGroupId);
        $this->assertSame($expectedSkus, $skus);
    }

    public function testGetAssignedProductsIds()
    {
        $customerGroupId = 1;
        $expectedSkus = [
            'simple_product_1',
            'simple_product_2',
            'simple_product_3',
        ];

        $expectedIds = [];
        $productCollection = Bootstrap::getObjectManager()->create(ProductCollection::class);
        $productCollection->addFieldToFilter('sku', $expectedSkus);
        foreach ($productCollection->getItems() as $product) {
            $expectedIds[] = (int) $product->getId();
        }

        $ids = $this->sharedCatalogProductsLoader->getAssignedProductsIds($customerGroupId);
        $this->assertSame($expectedIds, $ids);
    }

    public function testGetUsedCustomerGroupIds()
    {
        $customerGroupIds = [
            ProductItemManagementInterface::CUSTOMER_GROUP_NOT_LOGGED_IN,
        ];
        $sharedCatalogManagement = Bootstrap::getObjectManager()->create(SharedCatalogManagementInterface::class);
        $sharedCatalog = $sharedCatalogManagement->getPublicCatalog();
        $customerGroupIds[] = (int) $sharedCatalog->getCustomerGroupId();

        $usedCustomerGroupIds = $this->sharedCatalogProductsLoader->getUsedCustomerGroupIds();
        $this->assertSame($customerGroupIds, $usedCustomerGroupIds);
    }
}
