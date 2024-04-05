<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderAbstract;

/**
 * Controller test class for the purchase order place order.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsOtherCompanyAdminTest extends PlaceOrderAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testPlaceOrderActionAsOtherCompanyAdmin()
    {
        $otherCompanyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('company-admin@example.com');
        $this->session->loginById($otherCompanyAdmin->getId());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect(self::stringContains('company/accessdenied'));

        $this->session->logout();
    }
}
