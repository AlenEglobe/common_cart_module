<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model\ResourceModel;

use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\Framework\DB\Ddl\Sequence as DdlSequence;

/**
 * Purchase order resource model test.
 * Use Indexer\TestCase for isolation due to DDL statements.
 */
class PurchaseOrderTest extends \Magento\TestFramework\Indexer\TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var \Magento\PurchaseOrder\Model\ResourceModel\PurchaseOrder
     */
    private $purchaseOrderResource;

    /**
     * @var AppResource
     */
    private $appResource;

    /**
     * @var DdlSequence
     */
    private $ddlSequence;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->ddlSequence = $this->objectManager->get(DdlSequence::class);
        $this->appResource = $this->objectManager->get(AppResource::class);
        $this->quoteRepositoryMock = $this->getMockBuilder(\Magento\Quote\Api\CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMockForAbstractClass();

        $this->purchaseOrderResource = $this->objectManager->create(
            \Magento\PurchaseOrder\Model\ResourceModel\PurchaseOrder::class,
            [
                'quoteRepository' => $this->quoteRepositoryMock
            ]
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Store/_files/core_second_third_fixturestore.php
     */
    public function testReserveIncrementId()
    {
        $quoteMock1 = $this->getMockBuilder(\Magento\Quote\Api\Data\CartInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStoreId'])
            ->getMockForAbstractClass();
        $quoteMock2 = clone $quoteMock1;
        $quoteMock3 = clone $quoteMock1;
        /** @var \Magento\Store\Model\Store $store1 */
        $store1Id = $this->objectManager->get(\Magento\Store\Model\Store::class)->load('default', 'code')->getId();
        $store2Id = $this->objectManager->get(\Magento\Store\Model\Store::class)->load('secondstore', 'code')->getId();
        $store3Id = $this->objectManager->get(\Magento\Store\Model\Store::class)->load('thirdstore', 'code')->getId();
        // Force sequence init in default area. Default store sequence initialized on setup.
        $this->initSequence([$store2Id, $store3Id]);
        $quoteMock1->expects($this->any())->method('getStoreId')->willReturn($store1Id);
        $quoteMock2->expects($this->any())->method('getStoreId')->willReturn($store2Id);
        $quoteMock3->expects($this->any())->method('getStoreId')->willReturn($store3Id);
        $this->quoteRepositoryMock->expects($this->any())->method('get')->willReturnMap(
            [
                [1, ['*'], $quoteMock1],
                [2, ['*'], $quoteMock2],
                [3, ['*'], $quoteMock3],
            ]
        );
        $incId1 = $this->purchaseOrderResource->reserveIncrementId(1);
        $incId2 = $this->purchaseOrderResource->reserveIncrementId(2);
        $incId3 = $this->purchaseOrderResource->reserveIncrementId(3);
        $this->assertDoesNotMatchRegularExpression("/^{$store1Id}[0]+\d+$/", $incId1);
        $this->assertMatchesRegularExpression("/^{$store2Id}[0]+\d+$/", $incId2);
        $this->assertMatchesRegularExpression("/^{$store3Id}[0]+\d+$/", $incId3);
    }

    /**
     * Force initialize sequence tables for purchase orders.
     *
     * @param array $storeIds
     */
    private function initSequence($storeIds = [])
    {
        foreach ($storeIds as $storeId) {
            $sequenceConfig = $this->objectManager->get(\Magento\SalesSequence\Model\Config::class);
            $sequenceBuilder = $this->objectManager->get(\Magento\SalesSequence\Model\Builder::class);
            $sequenceBuilder->setPrefix($sequenceConfig->get('prefix'))
                ->setSuffix($sequenceConfig->get('suffix'))
                ->setStartValue($sequenceConfig->get('startValue'))
                ->setStoreId($storeId)
                ->setStep($sequenceConfig->get('step'))
                ->setWarningValue($sequenceConfig->get('warningValue'))
                ->setMaxValue($sequenceConfig->get('maxValue'))
                ->setEntityType('purchase_order')->create();
            $connection = $this->appResource->getConnection('default');
            // force create tables as original builder supposed to do
            if (!$connection->isTableExists($this->getSequenceName('purchase_order', $storeId))) {
                $connection->query(
                    $this->ddlSequence->getCreateSequenceDdl(
                        $this->getSequenceName('purchase_order', $storeId),
                        $sequenceConfig->get('startValue')
                    )
                );
            }
        }
    }

    /**
     * Returns sequence table name.
     *
     * @param string $entityType
     * @param int $storeId
     * @return string
     */
    private function getSequenceName($entityType, $storeId)
    {
        return $this->appResource->getTableName(
            sprintf(
                'sequence_%s_%s',
                $entityType,
                $storeId
            )
        );
    }
}
