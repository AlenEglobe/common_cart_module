<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Plugin\Catalog\Model\ResourceModel\Product\Indexer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Company\Test\Fixture\CustomerGroup as CustomerGroupFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation disabled
 */
class BaseFinalPricePluginTest extends TestCase
{

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * Connection adapter
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connectionMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->resource = $objectManager->get(ResourceConnection::class);
        $this->connectionMock = $this->resource->getConnection();
    }

    #[
        Config('btob/website_configuration/sharedcatalog_active', 1),
        Config('btob/website_configuration/direct_products_price_assigning', 1),
        DataFixture(CustomerGroupFixture::class, as: 'customer_group'),
        DataFixture(ProductFixture::class, as: 'simple'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$simple.id$',
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
    ]
    public function testAfterGetQuery()
    {
        /** @var CustomerGroup $customerGroup */
        $customerGroup = DataFixtureStorageManager::getStorage()->get('customer_group');

        $select = $this->connectionMock->select()->from(
            $this->resource->getTableName('shared_catalog_product_item')
        );
        $sharedCatalogProductItemCount = count($this->connectionMock->fetchAll($select));
        $select = $this->connectionMock->select()->from(
            ['cpip' => $this->resource->getTableName('catalog_product_index_price')]
        )->where('cpip.customer_group_id = ?', $customerGroup->getId());
        $catalogProductIndexPriceCount = count($this->connectionMock->fetchAll($select));
        $this->assertEquals($sharedCatalogProductItemCount, $catalogProductIndexPriceCount);
    }
    
    #[
        Config('btob/website_configuration/sharedcatalog_active', 1),
        Config('btob/website_configuration/direct_products_price_assigning', 1),
        DataFixture(ProductFixture::class, as: 'simple2'),
        DataFixture(CustomerGroupFixture::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(ProductFixture::class, as: 'simple1'),
    ]
    public function testPriceIndexerTableWithAndWithoutSharedCatalogAssignment()
    {
        /** @var Product $product1 */
        $product1 = DataFixtureStorageManager::getStorage()->get('simple1');
        /** @var Product $product2 */
        $product2 = DataFixtureStorageManager::getStorage()->get('simple2');
        /** @var CustomerGroup $customerGroup */
        $customerGroup = DataFixtureStorageManager::getStorage()->get('customer_group');

        $select = $this->connectionMock->select()->from(
            $this->resource->getTableName('catalog_product_index_price'),
            'entity_id'
        )->where(
            'entity_id = ?',
            $product1->getEntityId()
        )->where('customer_group_id = ?', $customerGroup->getId());
        $quoteRow = $this->connectionMock->fetchRow($select);
        $this->assertEquals($product1->getEntityId(), (string)$quoteRow['entity_id']);

        $select = $this->connectionMock->select()->from(
            $this->resource->getTableName('catalog_product_index_price'),
            'entity_id'
        )->where(
            'entity_id = ?',
            $product2->getEntityId()
        )->where('customer_group_id = ?', $customerGroup->getId());
        $this->assertFalse($this->connectionMock->fetchRow($select));
    }
}
