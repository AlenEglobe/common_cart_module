<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model\Payment;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController as AbstractTestCase;

/**
 * Deferred payment strategy test to validate payment method.
 *
 * @see \Magento\PurchaseOrder\Model\Payment\DeferredPaymentStrategyInterface
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeferredPaymentStrategyTest extends AbstractTestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var DeferredPaymentStrategyInterface
     */
    private $deferredPaymentStrategy;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->deferredPaymentStrategy = $this->objectManager->get(DeferredPaymentStrategyInterface::class);
    }

    /**
     * Test that online payment methods are treated as deferred.
     *
     * There are several exceptions:
     * - Free payment methods being online should not be treated as deferred.
     * - Amazon Pay being offline should be treated as deferred.
     *
     * @dataProvider paymentMethodsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     *
     * @param string $methodCode
     * @param bool $expectedIsDeferred
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testIsDeferredPayment(string $methodCode, bool $expectedIsDeferred): void
    {
        $purchaserEmail = 'veronica.costello@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrder->setPaymentMethod($methodCode);
        $isDeferred = $this->deferredPaymentStrategy->isDeferredPayment($purchaseOrder);

        $this->assertEquals(
            $expectedIsDeferred,
            $isDeferred,
        );
    }

    /**
     * Data provider for testIsDeferredPayment
     *
     * @return array
     */
    public function paymentMethodsDataProvider(): array
    {
        return [
            [
                'payment_method_code' => 'payflow_link',
                'is_deferred' => true
            ],
            [
                'payment_method_code' => 'purchaseorder',
                'is_deferred' => false
            ],
            [
                'payment_method_code' => 'paypal_express',
                'is_deferred' => true
            ],
            [
                'payment_method_code' => 'checkmo',
                'is_deferred' => false
            ],
            [
                'payment_method_code' => 'amazon_payment',
                'is_deferred' => true
            ],
            [
                'payment_method_code' => 'free',
                'is_deferred' => false
            ]
        ];
    }

    /**
     * Get purchase order for the customer.
     *
     * @param int $customerId
     * @return PurchaseOrderInterface
     */
    private function getCustomerFirstPurchaseOrder(int $customerId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customerId)
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        return array_shift($purchaseOrders);
    }
}
