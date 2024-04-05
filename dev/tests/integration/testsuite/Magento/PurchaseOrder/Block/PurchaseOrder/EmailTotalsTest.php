<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\LayoutInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Block test class for email specific purchase order totals information.
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\EmailTotals
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class EmailTotalsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var EmailTotals
     */
    private $emailTotalsBlock;

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
        /** @var LayoutInterface $layout */
        $layout = $objectManager->create(LayoutInterface::class);
        $layout->getUpdate()->load('email_purchaseorder_details');
        $layout->generateXml();
        $layout->generateElements();
        $this->emailTotalsBlock = $layout->getBlock('purchase.order.email.totals');
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the totals displayed for the Purchase Order email are accurate.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider expectedEmailTotalsDataProvider
     * @param string $incrementId
     * @param array $expectedEmailTotals
     * @throws NoSuchEntityException
     */
    public function testGetEmailTotals($incrementId, $expectedEmailTotals)
    {
        // Explicitly set the Purchase Order for the block
        $purchaseOrder = $this->getPurchaseOrderByIncrementId($incrementId);
        $this->emailTotalsBlock->setPurchaseOrderById($purchaseOrder->getEntityId());

        /** @var QuoteCollection $actualEmailTotals */
        $actualEmailTotals = $this->emailTotalsBlock->getEmailTotals();

        $this->assertEquals(count($expectedEmailTotals), count($actualEmailTotals));

        foreach ($expectedEmailTotals as $expectedTotalCode => $expectedTotal) {
            $actualTotal = $actualEmailTotals[$expectedTotalCode] ?? null;
            $this->assertNotNull($actualTotal);
            $this->assertEquals($expectedTotal, $actualTotal->toArray());
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
    public function expectedEmailTotalsDataProvider()
    {
        return [
            [
                'purchase_order_increment_id' => '900000001',
                'expectedEmailTotals' => [
                    'subtotal' => [
                        'value' => 10,
                        'label' => __('Subtotal'),
                        'class' => ''
                    ],
                    'grand_total' => [
                        'code' => EmailTotals::TOTAL_GRAND_TOTAL,
                        'field' => 'grand_total',
                        'strong' => true,
                        'value' => 10,
                        'label' => __('Grand Total')
                    ],
                    'gift_card' => [
                        'code' => EmailTotals::TOTAL_GIFT_CARD,
                        'block_name' => 'purchase.order.totals.giftcards'
                    ]
                ]
            ]
        ];
    }
}
