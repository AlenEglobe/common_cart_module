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
use Magento\CompanyCredit\Api\CreditLimitRepositoryInterface;

/**
 * Controller test class for the purchase order place order as company admin.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsCompanyAdminApprovedPOWithPaymentOnAccountNotAllowedToExceedCreditLimitTest extends PlaceOrderApprovedPurchaseAbstract
{
    /**
     * Verify a place order failed by payment on account payment method with not allowed credit limit and balance = 0
     *
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @dataProvider convertablePurchaseOrderStatusDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_company_credit.php
     */
    public function testPlaceOrderActionAsCompanyAdminApprovedPOWithPaymentOnAccountNotAllowedToExceedCreditLimit(
        $status
    ) {
        $purchaseOrder = $this->getPurchaseOrder('admin@magento.com', 'customer@example.com', $status);
        $id = $purchaseOrder->getEntityId();
        //set credit limit to 0
        $creditLimit = $this->creditLimitManagement->getCreditByCompanyId($purchaseOrder->getCompanyId());
        $creditLimit->setBalance(0);
        $this->objectManager->get(CreditLimitRepositoryInterface::class)->save($creditLimit);
        $this->dispatch(self::URI . '/request_id/' . $id);
        // assert result
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($id);
        self::assertEquals(PurchaseOrderInterface::STATUS_ORDER_FAILED, $postPurchaseOrder->getStatus());
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_SUCCESS);
        $errorMessage = 'Payment On Account cannot be used for this order '
            . 'because your order amount exceeds your credit amount.';
        $this->assertSessionMessages(
            self::equalTo([(string)__($errorMessage)]),
            MessageInterface::TYPE_ERROR
        );
        $this->session->logout();
    }
}
