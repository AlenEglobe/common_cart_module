<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\PurchaseOrder\Model\PurchaseOrderRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Indexer\TestCase;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Observer Test for match PO snapshot currency to quote
 *
 * @see \Magento\PurchaseOrder\Observer\ApplyPurchaseOrderSnapshotCurrency
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ApplyPurchaseOrderSnapshotCurrencyTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ApplyPurchaseOrderSnapshotCurrency
     */
    private $model;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var CompanyConfigRepositoryInterface
     */
    private $companyConfigRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagerInterface;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var mixed
     */
    private $request;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->model = $this->objectManager->get(ApplyPurchaseOrderSnapshotCurrency::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepository::class);
        $this->companyConfigRepository = $this->objectManager->get(CompanyConfigRepositoryInterface::class);
        $this->storeManagerInterface = $this->objectManager->get(StoreManagerInterface::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->request = $this->objectManager->get(RequestInterface::class);

        // Enable company functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);
    }

    /**
     * Test to confirm that quote currency is not matched with purchase order snapshot when fix is not applied.
     * Saving the quote without the 'purchaseOrderId' parameter will not run the Observer for updating currency.
     * Placing the order without the fix will result to invalid order totals and currency.
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_order_with_different_currency.php
     * @magentoDataFixture Magento/Store/_files/second_store_with_second_currency.php
     */
    public function testSecondCurrencyPurchaseOrderQuoteNotUpdatedCurrency(): void
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $this->session->loginById($customer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer($customer->getEmail());
        $snapshotQuote = $purchaseOrder->getSnapshotQuote();
        $this->updateQuoteToBaseCurrency($purchaseOrder);
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());

        $this->assertNotEquals($quote->getQuoteCurrencyCode(), $snapshotQuote->getQuoteCurrencyCode());
        $this->session->logout();
    }

    /**
     * Test for confirming the fix for matching the currency from purchase order snapshot to quote.
     * Saving the quote with 'purchaseOrderId' will run the Observer that updates the quote currency with purchase
     * order currency.
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_order_with_different_currency.php
     * @magentoDataFixture Magento/Store/_files/second_store_with_second_currency.php
     */
    public function testSecondPurchaseOrderQuoteObserverUpdatedCurrency(): void
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $this->session->loginById($customer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer($customer->getEmail());
        $this->updateQuoteToBaseCurrency($purchaseOrder);
        $snapshotQuote = $purchaseOrder->getSnapshotQuote();

        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $request = $this->request;
        $request->setParam('purchaseOrderId', $purchaseOrder->getEntityId());
        $this->quoteRepository->save($quote);

        $this->assertEquals($quote->getQuoteCurrencyCode(), $snapshotQuote->getQuoteCurrencyCode());
        $this->session->logout();
    }

    /**
     * Update purchase order quote to use base currency
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateQuoteToBaseCurrency(PurchaseOrderInterface $purchaseOrder)
    {
        $defaultStore = $this->storeManagerInterface->getStore('default');
        $this->storeManagerInterface->setCurrentStore($defaultStore);
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $quote->setQuoteCurrencyCode($defaultStore->getCurrentCurrency()->getCode());
    }

    /**
     * Get purchase order by customer email.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Enable/Disable configuration for the website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    private function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }
}
