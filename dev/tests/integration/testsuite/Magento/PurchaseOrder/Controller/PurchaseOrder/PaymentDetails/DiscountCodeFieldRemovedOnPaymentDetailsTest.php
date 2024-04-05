<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PaymentDetails;

use Magento\Customer\Model\CustomerRegistry;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PaymentDetailsAbstract;

/**
 * Controller test class for the purchase order payment details page.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class DiscountCodeFieldRemovedOnPaymentDetailsTest extends PaymentDetailsAbstract
{
    /**
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_with_purchase_orders_and_online_payment_method_used.php
     */
    public function testDiscountCodeFieldRemovedOnPaymentDetails()
    {
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        // Log in as the current user
        $purchaseOrderId = $this->getPurchaseOrderForCustomer('john.doe@example.com')->getEntityId();
        $currentUser = $this->objectManager->get(CustomerRegistry::class)->retrieveByEmail('john.doe@example.com');

        $this->getRequest()->setParam('purchaseOrderId', $purchaseOrderId);
        $this->session->setCustomerAsLoggedIn($currentUser);

        $purchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($purchaseOrderId);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);

        // Dispatch the request to the view payment details page for the desired purchase order
        $this->dispatch(self::URI . '/purchaseOrderId/' . $purchaseOrderId);

        $component = json_encode('Magento_SalesRule/js/view/payment/discount-messages');
        $this->assertStringNotContainsString(
            $component,
            $this->getResponse()->getBody()
        );
    }
}
