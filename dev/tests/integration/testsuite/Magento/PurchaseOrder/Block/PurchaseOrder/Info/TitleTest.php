<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder\Info;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Block test class for purchase order title information.
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Info\Title
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class TitleTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Title
     */
    private $titleBlock;

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
        $this->titleBlock = $objectManager->get(Title::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the name of the creator of the Purchase Order is correctly displayed.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testGetCreatorName()
    {
        // Explicitly set the Purchase Order for the block
        $purchaseOrder = $this->getPurchaseOrderByIncrementId('900000001');
        $this->titleBlock->setPurchaseOrderById($purchaseOrder->getEntityId());

        $actualCreatorName = $this->titleBlock->getCreatorName();
        $this->assertEquals('John Smith', $actualCreatorName);
    }

    /**
     * Get the Purchase Order created by the fixture.
     *
     * @param $incrementId
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
}
