<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\OfflinePayments\Model\Checkmo;
use Magento\Paypal\Model\Config;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\PurchaseOrder\ViewModel\PurchaseOrder\Success as SuccessViewModel;

/**
 * Abstract base class for Success Block tests
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Success
 *
 * @magentoAppArea frontend
 */
class SuccessAbstract extends TestCase
{
    /**
     * Get purchase order by increment id
     *
     * @param int $incrementId
     * @return mixed
     */
    public function getPurchaseOrderByIncrementId(int $incrementId)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

        $searchCriteria = $searchCriteriaBuilder->addFilter('increment_id', $incrementId)->create();
        return current($purchaseOrderRepository->getList($searchCriteria)->getItems());
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && 0 !== strpos($property->getDeclaringClass()->getName(), 'PHPUnit')) {
                $property->setAccessible(true);
                $property->setValue($this, null);
            }
        }
    }
}
