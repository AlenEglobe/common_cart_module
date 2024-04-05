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
class ApproveActionAsCompanyUserTest extends ApproveAbstract
{
    /**
     * @param string $currentUserEmail
     * @param string $createdByUserEmail
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @dataProvider approveActionAsCompanyUserDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testApproveActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $expectedHttpResponseCode,
        $expectedRedirect,
        $expectedStatus = PurchaseOrderInterface::STATUS_PENDING
    ) {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $session = $this->objectManager->get(Session::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        // Log in as the current user
        $currentUser = $customerRepository->get($currentUserEmail);
        $session->loginById($currentUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer($createdByUserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $purchaseOrderRepository->save($purchaseOrder);
        $purchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        self::assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains($expectedRedirect));
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

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
     * Data provider for various approve action scenarios for company users.
     *
     * @return array
     */
    public function approveActionAsCompanyUserDataProvider()
    {
        return [
            'approve_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied',
            ],
            'approve_subordinate_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied',
            ],
            'approve_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied',
            ],
        ];
    }
}
