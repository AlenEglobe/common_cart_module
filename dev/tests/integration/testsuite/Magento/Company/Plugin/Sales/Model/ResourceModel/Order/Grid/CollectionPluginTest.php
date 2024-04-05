<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Plugin\Sales\Model\ResourceModel\Order\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoDataFixture Magento/Company/_files/company_order.php
 */
class CollectionPluginTest extends TestCase
{
    /**
     * @var OrderGridCollection
     */
    private $orderGridCollection;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->orderGridCollection = Bootstrap::getObjectManager()
            ->create(OrderGridCollection::class);
    }

    public function testLoad(): void
    {
        $this->orderGridCollection->addFieldToFilter('company_name', 'Magento');
        $this->orderGridCollection->load();
        $items = $this->orderGridCollection->getItems();
        $this->assertCount(1, $items);
    }

    public function testCount(): void
    {
        $this->orderGridCollection->addFieldToFilter('company_name', 'Magento');
        $count = $this->orderGridCollection->count();
        $this->assertEquals(1, $count);
    }

    public function testGetSize(): void
    {
        $this->orderGridCollection->addFieldToFilter('company_name', 'Magento');
        $size = $this->orderGridCollection->getSize();
        $this->assertEquals(1, $size);
    }
}
