<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model\Price;

use Magento\Catalog\Api\Data\TierPriceInterface;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Exception\LocalizedException;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\SharedCatalog\Test\Fixture\AssignTierPricesToProduct;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog as SharedCatalogFixture;
use Magento\Store\Test\Fixture\Group as StoreGroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class TierPriceFetcherTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var TierPriceFetcher
     */
    private $tierPriceFetcher;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->tierPriceFetcher = $this->objectManager->create(TierPriceFetcher::class);
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/SharedCatalog/_files/shared_catalog_products_with_tier_price.php
     * @dataProvider fetchDataProvider
     * @param array $skuList
     * @param int $tierPricesCount
     * @throws LocalizedException
     */
    public function testFetch(array $skuList, int $tierPricesCount)
    {
        $sharedCatalogManagement = $this->objectManager->get(SharedCatalogManagementInterface::class);
        $sharedCatalog = $sharedCatalogManagement->getPublicCatalog();

        /** @var TierPriceInterface[] $tierPrices */
        $tierPrices = \iterator_to_array($this->tierPriceFetcher->fetch($sharedCatalog, $skuList));
        $this->assertCount($tierPricesCount, $tierPrices);
        foreach ($tierPrices as $tierPrice) {
            $this->assertInstanceOf(TierPriceInterface::class, $tierPrice);
            $this->assertTrue(\in_array($tierPrice->getSku(), $skuList, true));
        }
    }

    /**
     * @return array
     */
    public function fetchDataProvider(): array
    {
        return [
            [
                ['simple_product_1'],
                2,
            ],
            [
                ['simple_product_1', 'simple_product_2'],
                3,
            ],
            [
                ['simple_product_3'],
                0,
            ],
            [
                $this->generateLongSkuList(),
                3,
            ],
        ];
    }

    /**
     * Generate SKU list containing quotation mark
     *
     * @return array
     */
    private function generateLongSkuList() : array
    {
        $result = ['simple_product_1', 'simple_product_2', 'simple_product_3'];
        for ($i=4; $i<=1000; $i++) {
            $character = (rand(0, 10) === 5) ? '"' : '_';
            $sku = 'simple_product' . $character . (string)$i;
            $result[] = $sku;
        }
        return $result;
    }

    /**
     * Test to fetch shared catalog > tier prices in multi-website environment
     *
     * @throws LocalizedException
     * @return void
     */
    #[
        AppArea('adminhtml'),
        DbIsolation(false),
        Config(Data::XML_PATH_PRICE_SCOPE, Data::PRICE_SCOPE_WEBSITE),
        DataFixture(WebsiteFixture::class, as: 'website2'),
        DataFixture(StoreGroupFixture::class, ['website_id' => '$website2.id$'], 'store_group2'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$store_group2.id$'], 'store2'),
        DataFixture(ProductFixture::class, ['website_ids' => [1, '$website2.id$' ]], 'product'),
        DataFixture(SharedCatalogFixture::class, as: 'shared_catalog2'),
        DataFixture(
            AssignTierPricesToProduct::class,
            [
                'product_id' => '$product.id$',
                'shared_catalog_id' => '$shared_catalog2.id$',
                'prices' => [
                    [
                        'qty' => 3,
                        'website_id' => 0,
                        'value' => 12,
                        'value_type' => TierPriceInterface::PRICE_TYPE_FIXED,
                    ],
                    [
                        'qty' => 5,
                        'website_id' => 1,
                        'value' => 11,
                        'value_type' => TierPriceInterface::PRICE_TYPE_FIXED,
                    ],
                    [
                        'qty' => 7,
                        'website_id' => '$website2.id$',
                        'value' => 10,
                        'value_type' => TierPriceInterface::PRICE_TYPE_FIXED,
                    ]
                ]
            ],
            'assigned_tier_price'
        )
    ]
    public function testFetchMultiWebsiteTierPrice(): void
    {
        $sharedCatalog = $this->fixtures->get('shared_catalog2');
        $product = $this->fixtures->get('product');
        $tierPrice = $this->fixtures->get('assigned_tier_price');

        $expectedTierPrices = [];
        foreach ($tierPrice->getPrices() as $tp) {
            $expectedTierPrices[$product->getSku()][] =
            [
                'qty' => (int) $tp['qty'],
                'website_id' => (int) $tp['website_id'],
                'price' => (int) $tp['value'],
                'value_type' => $tp['value_type']
            ];
        }

        $fetchTierPrices = $this->tierPriceFetcher->fetch($sharedCatalog, [$product->getSku()]);
        $actualTierPrices = [];
        foreach ($fetchTierPrices as $price) {
            $actualTierPrices[$price->getSku()][] = [
                'qty' => (int) $price->getQuantity(),
                'website_id' => (int) $price->getWebsiteId(),
                'price' => (int) $price->getPrice(),
                'value_type' => $price->getPriceType()
            ];
        }

        $this->assertEqualsCanonicalizing($expectedTierPrices, $actualTierPrices);
    }
}
