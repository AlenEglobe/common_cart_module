<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem;

use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Controller\PurchaseOrder\AddItemAbstract;

/**
 * Controller test class for adding purchase order items to the shopping cart.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class AddItemActionAsGuestUserTest extends AddItemAbstract
{
    /**
     * Test that a guest user is redirected to the login page.
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testAddItemActionAsGuestUser()
    {
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        self::assertRedirect($this->stringContains('customer/account/login'));
    }
}
