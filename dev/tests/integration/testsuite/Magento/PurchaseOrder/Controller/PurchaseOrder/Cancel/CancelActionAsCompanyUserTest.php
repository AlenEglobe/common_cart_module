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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
class CancelActionAsCompanyUserTest extends CancelAbstract
{
    /**
     * Test purchase order cancellation as company user.
     *
     * @param $currentUserEmail
     * @param $createdByUserEmail
     * @param $expectedHttpResponseCode
     * @param $expectedRedirect
     * @param string $expectedStatus
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider cancelActionAsCompanyUserDataProvider
     */
    public function testCancelActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $expectedHttpResponseCode,
        $expectedRedirect,
        $expectedStatus = PurchaseOrderInterface::STATUS_PENDING
    ) {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $objectManager->get(Session::class);

        // Log in as the current user
        $currentUser = $customerRepository->get($currentUserEmail);
        $session->loginById($currentUser->getId());

        // Get purchase order for current customer
        $purchaseOrder = $this->getPurchaseOrderForCustomer($createdByUserEmail);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $purchaseOrderRepository->save($purchaseOrder);

        // Verify purchase order status
        $purchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals(PurchaseOrderInterface::STATUS_PENDING, $purchaseOrder->getStatus());

        // Dispatch the request to cancel purchase order
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        self::assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());
        self::assertRedirect($this->stringContains($expectedRedirect));
        $postPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        self::assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        $session->logout();
    }

    /**
     * Data provider for various reject action scenarios for company users.
     *
     * @return array
     */
    public function cancelActionAsCompanyUserDataProvider()
    {
        return [
            'cancel_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => '',
                'expected_status' => PurchaseOrderInterface::STATUS_CANCELED,
            ],
            'cancel_subordinate_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => '',
                'expected_status' => PurchaseOrderInterface::STATUS_CANCELED,
            ],
            'cancel_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied',
            ],
        ];
    }
}
