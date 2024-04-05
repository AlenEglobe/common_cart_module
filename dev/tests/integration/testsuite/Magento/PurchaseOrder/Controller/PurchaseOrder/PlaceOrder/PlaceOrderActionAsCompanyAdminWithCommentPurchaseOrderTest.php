<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderAbstract;
use Magento\PurchaseOrder\Model\Comment;
use Magento\PurchaseOrder\Model\CommentManagement;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Controller test class for the purchase order place order.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminWithCommentPurchaseOrderTest extends PlaceOrderAbstract
{
    /**
     * Verify a company admin can place order with a comment
     *
     * @param string $status
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testPlaceOrderActionAsCompanyAdminWithCommentPurchaseOrder($status)
    {
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaserEmail = 'customer@example.com';
        $purchaseOrder = $this->getPurchaseOrderForCustomer($purchaserEmail);
        $purchaseOrder->setStatus($status);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);

        // Place the order against the Purchase Order
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParams([
            'comment' => 'This is our test comment'
        ]);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Assert the Purchase Order is now approved
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());

        // Verify the comment was added to the Purchase Order
        $comments = $this->commentManagement->getPurchaseOrderComments($purchaseOrder->getEntityId());
        self::assertEquals(1, $comments->getSize());
        /** @var Comment $comment */
        $comment = $comments->getFirstItem();
        self::assertEquals('This is our test comment', $comment->getComment());
        self::assertEquals($companyAdmin->getId(), $comment->getCreatorId());

        $this->session->logout();
    }
}
