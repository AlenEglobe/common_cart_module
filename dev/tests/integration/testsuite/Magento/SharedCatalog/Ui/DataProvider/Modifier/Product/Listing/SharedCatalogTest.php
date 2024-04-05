<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Ui\DataProvider\Modifier\Product\Listing;

use Magento\Framework\ObjectManagerInterface;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Tests \Magento\SharedCatalog\Ui\DataProvider\Modifier\Product\Listing\SharedCatalog.
 *
 * @magentoDbIsolation enabled
 */
class SharedCatalogTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var SharedCatalogManagementInterface
     */
    private $sharedCatalogManagement;

    /**
     * @var SharedCatalog
     */
    private $sharedCatalogModifier;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->sharedCatalogManagement = $this->objectManager->get(SharedCatalogManagementInterface::class);
        $this->sharedCatalogModifier = $this->objectManager->get(SharedCatalog::class);
    }

    /**
     * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     *
     * @return void
     */
    public function testModifyDataProductAssignedToSharedCatalog(): void
    {
        $data = [
            'items' => [
                ['sku' => 'simple_product_1'],
                ['sku' => 'simple_product_2'],
                ['sku' => 'simple_product_3'],
            ],
        ];

        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();
        $expectedData = [
            'items' => [
                ['sku' => 'simple_product_1', 'shared_catalog' => [$sharedCatalog->getId()]],
                ['sku' => 'simple_product_2', 'shared_catalog' => [$sharedCatalog->getId()]],
                ['sku' => 'simple_product_3', 'shared_catalog' => [$sharedCatalog->getId()]],
            ],
        ];

        $actualData = $this->sharedCatalogModifier->modifyData($data);

        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     *
     * @return void
     */
    public function testModifyDataProductNotAssignedToSharedCatalog(): void
    {
        $data = [
            'items' => [
                ['sku' => 'simple'],
            ],
        ];

        $expectedData = [
            'items' => [
                ['sku' => 'simple', 'shared_catalog' => ''],
            ],
        ];

        $actualData = $this->sharedCatalogModifier->modifyData($data);

        $this->assertEquals($expectedData, $actualData);
    }
}
