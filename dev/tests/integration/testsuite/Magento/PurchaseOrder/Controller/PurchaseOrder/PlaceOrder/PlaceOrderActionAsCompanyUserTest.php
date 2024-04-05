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
class PlaceOrderActionAsCompanyUserTest extends PlaceOrderAbstract
{
    /**
     * @param string $currentUserEmail
     * @param string $createdByUserEmail
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @dataProvider placeOrderActionAsCompanyUserDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testPlaceOrderActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $expectedHttpResponseCode,
        $expectedRedirect
    ) {
        // Log in as the current user
        $currentUser = $this->objectManager->get(CustomerRepositoryInterface::class)->get($currentUserEmail);
        $this->session->loginById($currentUser->getId());

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer($createdByUserEmail);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        self::assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect(self::stringContains($expectedRedirect));

        $this->session->logout();
    }

    /**
     * Data provider for various place order action scenarios for company users.
     *
     * @return array
     */
    public function placeOrderActionAsCompanyUserDataProvider()
    {
        return [
            'place_order_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'place_order_subordinate_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'place_order_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ]
        ];
    }
}
