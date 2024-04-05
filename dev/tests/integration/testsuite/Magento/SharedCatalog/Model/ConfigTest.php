<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\CatalogPermissions\Model\Indexer\Category as CategoryPermissionsIndexer;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Area;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Config\Model\Config;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation disabled
 * @magentoDataFixture Magento/CatalogPermissions/_files/category_products.php
 * @magentoDataFixture Magento/Catalog/_files/second_product_simple.php
 */
class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function testToggleSharedCatalog()
    {
        $products = $this->getProducts();
        $this->assertCount(3, $products);
        $categories = $this->getCategories();
        $this->assertCount(4, $categories);

        $this->toggleSharedCatalog(1);
        $products = $this->getProducts();
        $this->assertEmpty($products);
        $categories = $this->getCategories();
        $this->assertEmpty($categories);

        $this->toggleSharedCatalog(0);
        $products = $this->getProducts();
        $this->assertCount(3, $products);
        $categories = $this->getCategories();
        $this->assertCount(4, $categories);
    }

    /**
     * @param int $value
     * @return void
     */
    private function toggleSharedCatalog(int $value)
    {
        Bootstrap::getInstance()->reinitialize();
        Bootstrap::getInstance()->loadArea(Area::AREA_ADMINHTML);

        $config = Bootstrap::getObjectManager()->create(Config::class);
        $config->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, $value);
        $config->save();

        $consumerFactory = Bootstrap::getObjectManager()->get(ConsumerFactory::class);
        $categoryPermissionsUpdater = $consumerFactory->get('sharedCatalogUpdateCategoryPermissions');
        $categoryPermissionsUpdater->process(100);
        $priceUpdater = $consumerFactory->get('sharedCatalogUpdatePrice');
        $priceUpdater->process(100);

        $indexerRegistry = Bootstrap::getObjectManager()->create(IndexerRegistry::class);
        $indexerRegistry->get(CategoryPermissionsIndexer::INDEXER_ID)->reindexAll();

        Bootstrap::getInstance()->reinitialize();
        Bootstrap::getInstance()->loadArea(Area::AREA_FRONTEND);
    }

    /**
     * @return \Magento\Catalog\Model\Product[]
     */
    private function getProducts(): array
    {
        $productCollection = Bootstrap::getObjectManager()->create(ProductCollection::class);
        $products = $productCollection->getItems();

        return $products;
    }

    /**
     * @return \Magento\Catalog\Model\Category[]
     */
    private function getCategories(): array
    {
        $categoryCollection = Bootstrap::getObjectManager()->create(CategoryCollection::class);
        $categoryCollection->addIsActiveFilter();
        $categories = $categoryCollection->getItems();

        return $categories;
    }
}
