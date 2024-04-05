<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
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
class AddItemActionAsNonCompanyUserTest extends AddItemAbstract
{
    /**
     * Test that a user who is not affiliated with a company is redirected to a 'noroute' page.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testAddItemActionAsNonCompanyUser()
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $session = $this->objectManager->get(Session::class);

        $nonCompanyUser = $customerRepository->get('customer@example.com');

        $session->loginById($nonCompanyUser->getId());
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        self::assertRedirect($this->stringContains('noroute'));

        $session->logout();
    }
}
