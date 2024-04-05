<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Controller test class for the checkout success page following purchase order creation.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class SuccessTest extends AbstractController
{
    const URI = 'purchaseorder/purchaseorder/success';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setup(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->checkoutSession = $objectManager->get(CheckoutSession::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the user sees a success page when a purchase order is successfully created.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testUserSeesSuccessPageWhenPurchaseOrderIsCreated()
    {
        $purchaseOrder = $this->getPurchaseOrderFromFixture();
        $this->checkoutSession->setCurrentPurchaseOrderId($purchaseOrder->getEntityId());

        $this->dispatch(self::URI);

        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertStringContainsString('Your Purchase Order request number is', $response->getBody());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $response->getBody());
    }

    /**
     * Test that the user sees a success page with a payment details message
     * when a purchase order is successfully created and online payment method is used.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testUserSeesSuccessPageWhenPurchaseOrderIsCreatedWithOnlinePayment()
    {
        $purchaseOrder = $this->getPurchaseOrderFromFixture();
        $purchaseOrder->setPaymentMethod(\Magento\Paypal\Model\Config::METHOD_BILLING_AGREEMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->checkoutSession->setCurrentPurchaseOrderId($purchaseOrder->getEntityId());

        $this->dispatch(self::URI);

        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertStringContainsString('Your Purchase Order request number is', $response->getBody());
        $message = 'You will be asked to enter your payment details after your purchase order has been approved.';
        $this->assertStringContainsString($message, $response->getBody());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $response->getBody());
    }

    /**
     * Test that the user is redirected to the cart when a purchase order was not created.
     */
    public function testUserIsRedirectedToCartWhenPurchaseOrderWasNotCreated()
    {
        $this->dispatch(self::URI);
        $this->assertRedirect($this->stringContains('checkout/cart'));
    }

    /**
     * Get the purchase order created by the fixture.
     *
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderFromFixture()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = reset($results);

        return $purchaseOrder;
    }
}
