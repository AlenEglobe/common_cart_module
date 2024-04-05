<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Approve;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\ApproveAbstract;
use Magento\PurchaseOrder\Model\PurchaseOrderLogRepositoryInterface;

/**
 * Controller test class for approving purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Approve
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ApproveActionGetRequestTest extends ApproveAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testApproveActionGetRequest()
    {
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        self::assert404NotFound();
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());

        // Verify no approved message in the log
        $approved = $purchaseOrderLogRepository->getList(
            $searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $purchaseOrder->getEntityId())
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'approve')
                ->create()
        );
        self::assertEquals(0, $approved->getTotalCount());
    }
}
