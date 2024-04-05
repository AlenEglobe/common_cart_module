<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel;

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
class CancelActionAsGuestUserTest extends CancelAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testCancelActionAsGuestUser()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        self::assertEquals(302, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains('customer/account/login'));
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());
    }
}
