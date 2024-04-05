<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\CancelAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Controller test class for cancelling purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class CancelActionAsNonCompanyUserTest extends CancelAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testCancelActionAsNonCompanyUser()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $objectManager->get(Session::class);

        $nonCompanyUser = $customerRepository->get('customer@example.com');
        $session->loginById($nonCompanyUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains('noroute'));
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());

        $session->logout();
    }
}
