<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Approve;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\PurchaseOrder\Controller\PurchaseOrder\ApproveAbstract;

/**
 * Controller test class for approving purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Approve
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ApproveActionNonexistingPurchaseOrderTest extends ApproveAbstract
{
    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testApproveActionNonexistingPurchaseOrder()
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $session = $this->objectManager->get(Session::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/5000');
        self::assertRedirect($this->stringContains('company/accessdenied'));

        $session->logout();
    }
}
