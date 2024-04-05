<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\PurchaseOrder\Plugin\Quote\Model;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyPoConfigRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderLogRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Test class for QuoteManagement plugin.
 *
 * @see \Magento\PurchaseOrder\Plugin\Quote\Model\QuoteManagementPlugin
 *
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteManagementPluginTest extends TestCase
{
    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CompanyPoConfigRepositoryInterface
     */
    private $companyPoConfigRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var PurchaseOrderLogRepositoryInterface
     */
    private $purchaseOrderLogRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = ObjectManager::getInstance();
        $this->quoteManagement = $objectManager->get(QuoteManagement::class);
        $this->customerSession = $objectManager->get(CustomerSession::class);
        $this->customerRepository = $objectManager->get(CustomerRepository::class);
        $this->quoteRepository = $objectManager->get(QuoteRepository::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->companyPoConfigRepository = $objectManager->get(CompanyPoConfigRepositoryInterface::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->purchaseOrderLogRepository = $objectManager->get(PurchaseOrderLogRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        // Enable company functionality at the website level
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality at the website level
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);
    }

    /**
     * Enable/Disable the configuration at the website level.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    private function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = ObjectManager::getInstance()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Enable/Disable purchase order functionality for the provided company.
     *
     * @param CompanyInterface $company
     * @param bool $isEnabled
     */
    private function setCompanyPurchaseOrderConfig(CompanyInterface $company, bool $isEnabled)
    {
        $companyConfig = $this->companyPoConfigRepository->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $this->companyPoConfigRepository->save($companyConfig);
    }

    /**
     * Get a company by name.
     *
     * @param string $companyName
     * @return CompanyInterface
     */
    private function getCompanyByName(string $companyName)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('company_name', $companyName)
            ->create();
        $items = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($items);

        return $company;
    }

    /**
     * Get a purchase order by its creator.
     *
     * @param int $customerId
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderByCreator(int $customerId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('creator_id', $customerId)
            ->create();
        $items = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = reset($items);

        return $purchaseOrder;
    }

    /**
     * Test that after a quote is submitted, the newly created order is linked to the associated purchase order.
     *
     * This should only be performed if the following are true:
     * 1. This quote originated from a purchase order.
     * 2. This purchase order used a deferred payment method on creation.
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/checkmo/active 1
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider afterSubmitDataProvider
     * @param string $purchaseOrderStatus
     * @param string $initialPaymentMethod
     * @param string $finalPaymentMethod
     * @param bool $expectUpdate
     */
    public function testAfterSubmitLinksPurchaseOrderToOrder(
        string $purchaseOrderStatus,
        string $initialPaymentMethod,
        string $finalPaymentMethod,
        bool $expectUpdate
    ) {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as a default company user
        $customer = $this->customerRepository->get('alex.smith@example.com');
        $this->customerSession->loginById($customer->getId());

        // Get the purchase order created by this company user
        $purchaseOrder = $this->getPurchaseOrderByCreator($customer->getId());

        // Simulate the deferred payment method used on initial checkout
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $quote->getPayment()->setMethod($initialPaymentMethod);
        $purchaseOrder->setSnapshotQuote($quote);
        $purchaseOrder->setPaymentMethod($initialPaymentMethod);

        // Simulate approval for this purchase order
        $purchaseOrder->setStatus($purchaseOrderStatus);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Simulate the payment method used on final checkout
        $quote->getPayment()->setMethod($finalPaymentMethod);
        $this->quoteRepository->save($quote);

        // Invoke the method-in-test
        $order = $this->quoteManagement->submit($quote);

        // Load the purchase order from the database to assert the changes
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());

        // Load the history logs for this purchase order for the 'place_order' activity
        $placeOrderLogs = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $purchaseOrder->getEntityId())
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'place_order')
                ->create()
        );

        // Assert the payment methods on the purchase order and final order
        $this->assertEquals($initialPaymentMethod, $purchaseOrder->getPaymentMethod());
        $this->assertEquals($finalPaymentMethod, $order->getPayment()->getMethod());

        // Assert that the purchase order was updated with the newly created order when expected
        if ($expectUpdate) {
            $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $purchaseOrder->getStatus());
            $this->assertEquals($order->getId(), $purchaseOrder->getOrderId());
            $this->assertEquals($order->getIncrementId(), $purchaseOrder->getOrderIncrementId());
            $this->assertEquals(1, $placeOrderLogs->getTotalCount());
        } else {
            $this->assertNotEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $purchaseOrder->getStatus());
            $this->assertNull($purchaseOrder->getOrderId());
            $this->assertNull($purchaseOrder->getOrderIncrementId());
            $this->assertEquals(0, $placeOrderLogs->getTotalCount());
        }
    }

    /**
     * Data provider for the afterSubmit test.
     *
     * @return array
     */
    public function afterSubmitDataProvider()
    {
        return [
            /*
             * Verify that a purchase order created with a deferred payment is correctly updated after final checkout.
             *
             * The final payment method doesn't matter as long as we use a deferred payment on initial checkout.
             * We use a non-deferred payment method for testing purposes in order to successfully place the order.
             */
            'deferredPaymentMethod' => [
                'purchase_order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'initial_payment_method' => 'paypal_express',
                'final_payment_method' => 'checkmo',
                'expect_update' => true
            ],
            /*
             * Purchase orders using non-deferred payment methods shouldn't require a final checkout.
             * Regardless, verify that the plugin doesn't perform any updates.
             */
            'nonDeferredPaymentMethod' => [
                'purchase_order_status' => PurchaseOrderInterface::STATUS_APPROVED,
                'initial_payment_method' => 'checkmo',
                'final_payment_method' => 'checkmo',
                'expect_update' => false
            ]
        ];
    }
}
