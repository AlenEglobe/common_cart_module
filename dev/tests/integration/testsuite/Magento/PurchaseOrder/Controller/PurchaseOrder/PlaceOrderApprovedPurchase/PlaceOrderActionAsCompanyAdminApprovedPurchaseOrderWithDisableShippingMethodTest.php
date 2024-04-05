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

/**
 * Controller test class for the purchase order place order as company admin.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminApprovedPurchaseOrderWithDisableShippingMethodTest extends PlaceOrderApprovedPurchaseAbstract
{
    /**
     * Verify a purchase place order totals with disabled shipping method
     *
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @magentoConfigFixture current_store carriers/flatrate/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_shipping_method.php
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     */
    public function testPlaceOrderActionAsCompanyAdminApprovedPurchaseOrderWithDisableShippingMethod($status)
    {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);
        $id = $purchaseOrder->getEntityId();
        //change rate value
        $this->configWriter->save('carriers/flatrate/active', 0);
        //change shipping rate
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
            "Your Order <span class=\"no-link\">#test_order_1</span>",
            $sentMessage->getBody()->getParts()[0]->getRawContent()
        );
    }
}
