<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder;

use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrderAbstract;

/**
 * Controller test class for the purchase order place order.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderActionAsGuestUserTest extends PlaceOrderAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testPlaceOrderActionAsGuestUser()
    {
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect(self::stringContains('customer/account/login'));
    }
}
