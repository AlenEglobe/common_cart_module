<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Block test class for purchase order items information.
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Items
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ItemsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Items
     */
    private $itemsBlock;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->itemsBlock = $objectManager->get(Items::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the items displayed for the Purchase Order are accurate.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider expectedItemsDataProvider
     * @param string $incrementId
     * @param array $expectedItems
     */
    public function testGetItems($incrementId, $expectedItems)
    {
        // Explicitly set the Purchase Order for the block
        $purchaseOrder = $this->getPurchaseOrderByIncrementId($incrementId);
        $this->itemsBlock->setPurchaseOrderById($purchaseOrder->getEntityId());

        /** @var QuoteCollection $actualItems */
        $actualItems = $this->itemsBlock->getItems();

        $this->assertEquals(count($expectedItems), $actualItems->count());

        foreach ($expectedItems as $expectedItem) {
            $actualItem = $actualItems->getItemByColumnValue('sku', $expectedItem['sku']);
            $this->assertNotNull($actualItem);
            $this->assertEquals($expectedItem['name'], $actualItem->getName());
            $this->assertEquals($expectedItem['qty'], $actualItem->getQty());
            $this->assertEquals($expectedItem['price'], $actualItem->getPrice());
        }
    }

    /**
     * Get the Purchase Order created by the fixture.
     *
     * @param string $incrementId
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderByIncrementId($incrementId)
    {
        $searchCriteria =  $this->searchCriteriaBuilder
           ->addFilter(PurchaseOrderInterface::INCREMENT_ID, $incrementId)
           ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        return reset($purchaseOrders);
    }

    /**
     * @return array
     */
    public function expectedItemsDataProvider()
    {
        return [
            [
                'purchase_order_increment_id' => '900000001',
                'expectedItems' => [
                    [
                        'sku' => 'virtual-product',
                        'name' => 'Virtual Product',
                        'price' => 10,
                        'qty' => 1
                    ]
                ]
            ]
        ];
    }
}
