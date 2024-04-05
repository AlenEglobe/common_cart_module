<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Payment\Helper\Data as PaymentData;

/**
 * Controller test class for approving purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Approve
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ApproveAbstract extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    public const URI = 'purchaseorder/purchaseorder/approve';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Get all purchase orders for the given customer.
     *
     * @param string $customerEmail
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllPurchaseOrdersForCustomer(string $customerEmail) : array
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        $customer = $customerRepository->get($customerEmail);
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $purchaseOrderRepository->getList($searchCriteria)->getItems();

        return $purchaseOrders;
    }

    /**
     * Get expected purchase order status based on payment method
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     * @throws LocalizedException
     */
    public function getExpectedPurchaseOrderApprovedStatus(PurchaseOrderInterface $purchaseOrder)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $paymentData = $objectManager->get(PaymentData::class);

        $paymentMethodInstance = $paymentData->getMethodInstance($purchaseOrder->getPaymentMethod());

        return ($paymentMethodInstance->isOffline())
            ? PurchaseOrderInterface::STATUS_APPROVED
            : PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT;
    }
}
