<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Request\Http;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Model\HistoryManagementInterface;
use Magento\NegotiableQuote\Model\NegotiableQuoteRepository;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\CancelAbstract;
use Magento\PurchaseOrder\Model\Comment;
use Magento\PurchaseOrder\Model\CommentManagement;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Controller test class for cancelling purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class CancelCancellablePurchaseOrderTest extends CancelAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_order_using_negotiable_quote.php
     * @dataProvider cancellablePurchaseOrderStatusDataProvider
     */
    public function testCancelPurchaseOrderCreatedFromNegotiableQuote($status)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $objectManager->get(Session::class);
        $negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepository::class);
        $negotiableQuoteHistory = $objectManager->get(HistoryManagementInterface::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus($status);
        $purchaseOrderRepository->save($purchaseOrder);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_CANCELED, $postPurchaseOrder->getStatus());
        // fetching negotiable quote (has same ID as the regular quote attached to Purchase Order)
        $negotiableQuote = $negotiableQuoteRepository->getById($purchaseOrder->getQuoteId());
        self::assertEquals(NegotiableQuoteInterface::STATUS_CLOSED, $negotiableQuote->getStatus());
        $quoteHistory = $negotiableQuoteHistory->getQuoteHistory($purchaseOrder->getQuoteId());
        /** @var ExtensibleDataInterface $logEntry */
        $logEntry = array_shift($quoteHistory);
        $logEntryData = json_decode($logEntry->getLogData(), true);
        self::assertEquals(NegotiableQuoteInterface::STATUS_CLOSED, $logEntryData['status']['new_value']);
        self::assertEquals(0, $logEntry->getAuthorId());
        $session->logout();
    }

    /**
     * Verify a company admin cancelling a purchase with a comment
     *
     * @dataProvider cancellablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCancelActionAsCompanyAdminWithCommentPurchaseOrder($status)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $objectManager->get(Session::class);
        $commentManagement = $objectManager->get(CommentManagement::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaserEmail = 'customer@example.com';
        $purchaseOrder = $this->getPurchaseOrderForCustomer($purchaserEmail);
        $purchaseOrder->setStatus($status);
        $purchaseOrderRepository->save($purchaseOrder);

        // Cancel the purchase order
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParams([
            'comment' => 'A cancellation comment'
        ]);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Assert the Purchase Order is now cancelled
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_CANCELED, $postPurchaseOrder->getStatus());

        // Verify the comment was added to the Purchase Order
        $comments = $commentManagement->getPurchaseOrderComments($purchaseOrder->getEntityId());
        self::assertEquals(1, $comments->getSize());
        /** @var Comment $comment */
        $comment = $comments->getFirstItem();
        self::assertEquals('A cancellation comment', $comment->getComment());
        self::assertEquals($companyAdmin->getId(), $comment->getCreatorId());

        $session->logout();
    }

    /**
     * Data provider of purchase order statuses that allow cancellation.
     *
     * @return array
     */
    public function cancellablePurchaseOrderStatusDataProvider()
    {
        return [
            'Approval Required' => [PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED],
            'Approved - Pending Payment' => [PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT],
            'Pending' => [PurchaseOrderInterface::STATUS_PENDING],
            'Approved - Order Failed' => [PurchaseOrderInterface::STATUS_ORDER_FAILED]
        ];
    }
}
