<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderApprovedPurchase;

use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderApprovedPurchaseAbstract;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\Framework\Message\MessageInterface;
use Magento\CompanyCredit\Api\CreditLimitRepositoryInterface;

/**
 * Controller test class for the purchase order place order as company admin.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminApprovedPOWithPaymentOnAccountAllowedToExceedCreditLimitTest extends PlaceOrderApprovedPurchaseAbstract
{
    /**
     * Verify place order totals ordered by payment on account payment method with exceed credit limit and balance = 0
     *
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_company_credit_with_credit_limit.php
     */
    public function testPlaceOrderActionAsCompanyAdminApprovedPOWithPaymentOnAccountAllowedToExceedCreditLimit($status)
    {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);
        $id = $purchaseOrder->getEntityId();
        //set credit limit to 0
        $creditLimit = $this->creditLimitManagement->getCreditByCompanyId($purchaseOrder->getCompanyId());
        $creditLimit->setBalance(0);
        $this->objectManager->get(CreditLimitRepositoryInterface::class)->save($creditLimit);
        $this->dispatch(self::URI . '/request_id/' . $id);
        // assert result
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($id);
        self::assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        self::assertNotNull($postPurchaseOrder->getOrderId());
        self::assertNotNull($postPurchaseOrder->getOrderIncrementId());
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_ERROR);
        $successMessage = 'Successfully placed order #test_order_with_virtual_product from purchase order #'
            . $postPurchaseOrder->getIncrementId()
            . '.';
        $this->assertSessionMessages(
            self::equalTo([(string)__($successMessage)]),
            MessageInterface::TYPE_SUCCESS
        );

        $order = $this->objectManager->get(OrderRepositoryInterface::class)->get($postPurchaseOrder->getOrderId());
        self::assertEquals($order->getIncrementId(), $postPurchaseOrder->getOrderIncrementId());
        self::assertEquals($order->getGrandTotal(), $purchaseOrder->getSnapshotQuote()->getGrandTotal());
        $this->session->logout();

        // Assert email notification
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        self::assertStringContainsString('order confirmation', $sentMessage->getSubject());
        self::assertStringContainsString(
            'Thank you for your order from ',
            $sentMessage->getBody()->getParts()[0]->getRawContent()
        );
        self::assertStringContainsString(
            "Your Order <span class=\"no-link\">#test_order_with_virtual_product</span>",
            $sentMessage->getBody()->getParts()[0]->getRawContent()
        );
    }
}
