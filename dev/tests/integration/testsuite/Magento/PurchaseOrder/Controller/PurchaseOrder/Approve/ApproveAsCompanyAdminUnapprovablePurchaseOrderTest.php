<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Approve;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\MessageInterface;
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
class ApproveAsCompanyAdminUnapprovablePurchaseOrderTest extends ApproveAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider unapprovablePurchaseOrderStatusDataProvider
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testApproveAsCompanyAdminUnapprovablePurchaseOrder($status)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $session = $this->objectManager->get(Session::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus($status);
        $purchaseOrderRepository->save($purchaseOrder);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $message = 'Unable to approve purchase order. Purchase order '
            . $purchaseOrder->getIncrementId()
            . ' cannot be approved.';
        self::assertSessionMessages($this->equalTo([(string)__($message)]), MessageInterface::TYPE_ERROR);

        // Verify no approved message in the log
        $approved = $purchaseOrderLogRepository->getList(
            $searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $purchaseOrder->getEntityId())
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'approve')
                ->create()
        );
        self::assertEquals(0, $approved->getTotalCount());
        $session->logout();
    }

    /**
     * Data provider of purchase order statuses that do not allow approval.
     *
     * @return array
     */
    public function unapprovablePurchaseOrderStatusDataProvider()
    {
        return [
            [PurchaseOrderInterface::STATUS_CANCELED],
            [PurchaseOrderInterface::STATUS_REJECTED],
            [PurchaseOrderInterface::STATUS_ORDER_PLACED],
            [PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS],
        ];
    }
}
