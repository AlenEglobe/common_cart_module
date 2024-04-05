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
use Magento\Framework\Message\MessageInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\ApproveAbstract;
use Magento\PurchaseOrder\Model\Comment;
use Magento\PurchaseOrder\Model\CommentManagement;
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
class ApproveAsCompanyAdminApprovedPurchaseOrderTest extends ApproveAbstract
{
    /**
     * Data provider of purchase order payment methods
     *
     * @return array
     */
    public function paymentMethodsDataProvider()
    {
        return [
            'Offline Payment Method' => ['checkmo'],
            'Online Payment Method' => ['paypal_express'],
        ];
    }

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testApproveAsCompanyAdminApprovedPurchaseOrder($paymentMethod)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $this->objectManager->get(Session::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $purchaseOrderRepository->save($purchaseOrder);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        self::assertSessionMessages(
            $this->equalTo([(string)__('Purchase order has been successfully approved.')]),
            MessageInterface::TYPE_SUCCESS
        );
        $session->logout();
    }

    /**
     * Verify a company admin approving a purchase with a comment
     *
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testApproveActionAsCompanyAdminWithCommentPurchaseOrder($paymentMethod)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $this->objectManager->get(Session::class);
        $commentManagement = $this->objectManager->get(CommentManagement::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaserEmail = 'customer@example.com';
        $purchaseOrder = $this->getPurchaseOrderForCustomer($purchaserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED);
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $purchaseOrderRepository->save($purchaseOrder);

        // Approve the purchase order
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParams([
            'comment' => 'Approval granted'
        ]);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Assert the Purchase Order is now approved
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($postPurchaseOrder);
        self::assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        // Verify the comment was added to the Purchase Order
        $comments = $commentManagement->getPurchaseOrderComments($purchaseOrder->getEntityId());
        self::assertEquals(1, $comments->getSize());
        /** @var Comment $comment */
        $comment = $comments->getFirstItem();
        self::assertEquals('Approval granted', $comment->getComment());
        self::assertEquals($companyAdmin->getId(), $comment->getCreatorId());

        $session->logout();
    }

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testApproveActionAsGuestUser($paymentMethod)
    {
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $purchaseOrderRepository->save($purchaseOrder);
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains('customer/account/login'));
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

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testApproveActionAsNonCompanyUser($paymentMethod)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $session = $this->objectManager->get(Session::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        $nonCompanyUser = $customerRepository->get('customer@example.com');
        $session->loginById($nonCompanyUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $purchaseOrderRepository->save($purchaseOrder);
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains('noroute'));
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

        $session->logout();
    }

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/unapprovable_and_approvable_purchase_orders.php
     */
    public function testMassApproveAsCompanyAdminPurchaseOrders($paymentMethod)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $session = $this->objectManager->get(Session::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaserEmail = 'customer@example.com';

        $purchaseOrders = $this->getAllPurchaseOrdersForCustomer($purchaserEmail);

        $purchaseOrdersIds = [];
        foreach ($purchaseOrders as $purchaseOrder) {
            $purchaseOrdersIds[] = $purchaseOrder->getEntityId();
            $purchaseOrder->setPaymentMethod($paymentMethod);
            $purchaseOrderRepository->save($purchaseOrder);
        }
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParams([
            'selected' => $purchaseOrdersIds,
            'namespace' => 'require_my_approval_purchaseorder_listing'
        ]);
        $this->dispatch(self::URI);

        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::ENTITY_ID, $purchaseOrdersIds, 'in')
            ->create();
        $postPurchaseOrders = $purchaseOrderRepository->getList($searchCriteria)->getItems();

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->getStatus() === PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED ||
                $purchaseOrder->getStatus() === PurchaseOrderInterface::STATUS_PENDING
            ) {
                $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($purchaseOrder);

                self::assertEquals(
                    $expectedStatus,
                    $postPurchaseOrders[$purchaseOrder->getId()]->getStatus()
                );
                $message = '2 Purchase Orders have been successfully approved';

                self::assertSessionMessages(
                    $this->equalTo([(string)__($message)]),
                    MessageInterface::TYPE_SUCCESS
                );
            } else {
                self::assertEquals(
                    $purchaseOrder->getStatus(),
                    $postPurchaseOrders[$purchaseOrder->getId()]->getStatus()
                );
                self::assertSessionMessages(
                    $this->isEmpty(),
                    MessageInterface::TYPE_ERROR
                );
            }
        }
        $session->logout();
    }

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testApproveActionAsOtherCompanyAdmin($paymentMethod)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $session = $this->objectManager->get(Session::class);
        $purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);

        $nonCompanyUser = $customerRepository->get('company-admin@example.com');
        $session->loginById($nonCompanyUser->getId());
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $purchaseOrderRepository->save($purchaseOrder);
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains('company/accessdenied'));
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

        $session->logout();
    }
}
