<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Api;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\TierPriceStorageInterface;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class PriceManagementInterfaceTest extends TestCase
{
    /**
     * @var SharedCatalogInterface
     */
    private $publicSharedCatalog;

    /**
     * @var TierPriceStorageInterface
     */
    private $tierPriceStorage;

    /**
     * @var PriceManagementInterface
     */
    private $priceManagement;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $sharedCatalogManagement = Bootstrap::getObjectManager()->get(SharedCatalogManagementInterface::class);
        $this->publicSharedCatalog = $sharedCatalogManagement->getPublicCatalog();
        $this->tierPriceStorage = Bootstrap::getObjectManager()->create(TierPriceStorageInterface::class);

        $this->priceManagement = Bootstrap::getObjectManager()->create(PriceManagementInterface::class);
    }

    /**
     * @magentoDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     * @dataProvider saveDataProvider
     * @param array $skuList
     * @param int $tierPricesCount
     * @param array ...$tierPrices
     */
    public function testSaveProductTierPrices(array $skuList, int $tierPricesCount, array ...$tierPrices)
    {
        $productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $productsTierPrices = [];
        foreach ($skuList as $sku) {
            $product = $productRepository->get($sku);
            $productsTierPrices[$product->getId()] = $tierPrices;
        }

        $tierPrices = $this->tierPriceStorage->get($skuList);
        $this->assertEmpty($tierPrices);

        $this->priceManagement->saveProductTierPrices($this->publicSharedCatalog, $productsTierPrices);
        $tierPrices = $this->tierPriceStorage->get($skuList);
        $this->assertCount($tierPricesCount, $tierPrices);
    }

    /**
     * @magentoDataFixture Magento/SharedCatalog/_files/shared_catalog_products_with_tier_price.php
     * @depends testSaveProductTierPrices
     * @dataProvider deleteDataProvider
     * @param array $skuList
     * @param int $beforeCount
     */
    public function testDeleteProductTierPrices(array $skuList, int $beforeCount)
    {
        $tierPrices = $this->tierPriceStorage->get($skuList);
        $this->assertCount($beforeCount, $tierPrices);

        $this->priceManagement->deleteProductTierPrices($this->publicSharedCatalog, $skuList);
        $tierPrices = $this->tierPriceStorage->get($skuList);
        $this->assertEmpty($tierPrices);
    }

    /**
     * @return array
     */
    public function saveDataProvider(): array
    {
        return [
            [
                ['simple_product_1'],
                2,
                [
                    'qty' => 1,
                    'website_id' => 0,
                    'value' => 9,
                ],
            ],
            [
                ['simple_product_1'],
                4,
                [
                    'qty' => 1,
                    'website_id' => 0,
                    'value' => 9,
                ],
                [
                    'qty' => 5,
                    'website_id' => 0,
                    'percentage_value' => 50,
                ],
            ],
            [
                ['simple_product_1', 'simple_product_2'],
                4,
                [
                    'qty' => 1,
                    'website_id' => 0,
                    'value' => 9,
                ],
            ],
            [
                ['simple_product_1', 'simple_product_2'],
                8,
                [
                    'qty' => 1,
                    'website_id' => 0,
                    'value' => 9,
                ],
                [
                    'qty' => 5,
                    'website_id' => 0,
                    'percentage_value' => 50,
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function deleteDataProvider(): array
    {
        return [
            [
                ['simple_product_2'],
                2,
            ],
            [
                ['simple_product_1'],
                4,
            ],
            [
                ['simple_product_1', 'simple_product_2'],
                6,
            ],
            [
                ['simple_product_3'],
                0,
            ],
        ];
    }
}
