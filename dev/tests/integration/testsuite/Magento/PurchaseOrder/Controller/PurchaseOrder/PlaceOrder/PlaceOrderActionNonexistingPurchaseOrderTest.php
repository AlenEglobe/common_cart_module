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
class PlaceOrderActionNonexistingPurchaseOrderTest extends PlaceOrderAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testPlaceOrderActionNonexistingPurchaseOrder()
    {
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . '5000');
        $this->assertRedirect(self::stringContains('company/accessdenied'));

        $this->session->logout();
    }
}
