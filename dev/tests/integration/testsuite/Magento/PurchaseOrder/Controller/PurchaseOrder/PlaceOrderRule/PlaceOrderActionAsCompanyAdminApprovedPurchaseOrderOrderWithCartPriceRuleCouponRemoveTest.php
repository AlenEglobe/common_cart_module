<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderRule;

use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderRuleAbstract;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\Framework\Message\MessageInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;

/**
 * Controller test class for the purchase order place order as company admin with price rules.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminApprovedPurchaseOrderOrderWithCartPriceRuleCouponRemoveTest extends PlaceOrderRuleAbstract
{
    /**
     * Verify a purchase place order totals with removed cart price rule rate with coupon
     *
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_coupon_applied.php
     */
    public function testPlaceOrderActionAsCompanyAdminApprovedPurchaseOrderOrderWithCartPriceRuleCouponRemove($status)
    {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);
        $id = $purchaseOrder->getEntityId();
        //remove applied rule
        $ruleRepository = $this->objectManager->get(RuleRepositoryInterface::class);
        $appliedRule = $ruleRepository->getById($purchaseOrder->getSnapshotQuote()->getAppliedRuleIds());
        $ruleRepository->deleteById($appliedRule->getRuleId());
        $this->dispatch(self::URI . '/request_id/' . $id);
        // assert result
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($id);
        self::assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        self::assertNotNull($postPurchaseOrder->getOrderId());
        self::assertNotNull($postPurchaseOrder->getOrderIncrementId());
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_ERROR);
        $successMessage = 'Successfully placed order #test_order_1 from purchase order #'
            . $postPurchaseOrder->getIncrementId()
            . '.';
        $this->assertSessionMessages(
            $this->equalTo([(string)__($successMessage)]),
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
            "Your Order <span class=\"no-link\">#test_order_1</span>",
            $sentMessage->getBody()->getParts()[0]->getRawContent()
        );
    }
}
