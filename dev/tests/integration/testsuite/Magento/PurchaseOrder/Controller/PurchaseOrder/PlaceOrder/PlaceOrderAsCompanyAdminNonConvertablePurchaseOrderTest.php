<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderAbstract;
use Magento\Framework\Message\MessageInterface;

/**
 * Controller test class for the purchase order place order.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderAsCompanyAdminNonConvertablePurchaseOrderTest extends PlaceOrderAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider unconvertablePurchaseOrderStatusDataProvider
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testPlaceOrderAsCompanyAdminNonConvertablePurchaseOrder($status)
    {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);

        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $message = 'Order cannot be placed with purchase order #' . $purchaseOrder->getIncrementId() . '.';
        $this->assertSessionMessages(self::equalTo([(string)__($message)]), MessageInterface::TYPE_ERROR);
        $this->session->logout();
    }

    /**
     * Data provider of purchase order statuses that do not allow approval.
     *
     * @return array[]
     */
    public function unconvertablePurchaseOrderStatusDataProvider()
    {
        return [
            [PurchaseOrderInterface::STATUS_PENDING],
            [PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED],
            [PurchaseOrderInterface::STATUS_CANCELED],
            [PurchaseOrderInterface::STATUS_REJECTED],
            [PurchaseOrderInterface::STATUS_ORDER_PLACED],
            [PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS],
            [PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT],
        ];
    }
}
