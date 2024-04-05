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
class CancelActionNonexistingPurchaseOrderTest extends CancelAbstract
{
    /**
     * Test cancellation of unexistent purchase order.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testCancelActionNonexistingPurchaseOrder()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $session = $objectManager->get(Session::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/5000');
        self::assertRedirect($this->stringContains('company/accessdenied'));

        $session->logout();
    }
}
