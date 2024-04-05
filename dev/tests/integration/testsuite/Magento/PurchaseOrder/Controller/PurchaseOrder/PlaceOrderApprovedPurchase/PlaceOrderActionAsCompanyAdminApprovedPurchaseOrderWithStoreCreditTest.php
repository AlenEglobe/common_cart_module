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
use Magento\Framework\Message\MessageInterface;

/**
 * Controller test class for the purchase order place order as company admin.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminApprovedPurchaseOrderWithStoreCreditTest extends PlaceOrderApprovedPurchaseAbstract
{
    /**
     * Verify a purchase place order totals with customer store credit = 0
     *
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_customer_balance.php
     */
    public function testPlaceOrderActionAsCompanyAdminApprovedPurchaseOrderWithStoreCredit($status)
    {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);
        $id = $purchaseOrder->getEntityId();
        //set customer balance to 0
        $customerBalance = $this->customerBalanceFactory->create()
            ->load($purchaseOrder->getSnapshotQuote()->getCustomer()->getId(), 'customer_id');
        $customerBalance->setAmount(0)->save();
        $this->dispatch(self::URI . '/request_id/' . $id);
        // assert result
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($id);
        self::assertEquals(PurchaseOrderInterface::STATUS_ORDER_FAILED, $postPurchaseOrder->getStatus());
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_SUCCESS);
        $errorMessage = 'You do not have enough store credit to complete this order.';
        $this->assertSessionMessages(
            self::equalTo([(string)__($errorMessage)]),
            MessageInterface::TYPE_ERROR
        );
        $this->session->logout();
    }
}
