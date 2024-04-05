<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Ui\Component\Listing\Column;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\ResourceModel\PurchaseOrder\Grid\Collection;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Store\StoreManager;

/**
 * Test class for the price column in the purchase order listing UI component.
 *
 * @see \Magento\PurchaseOrder\Ui\Component\Listing\Column\Price
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PriceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Price
     */
    private $priceColumn;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var Session
     */
    private $session;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->priceColumn = $objectManager->get(Price::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->quoteRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);
        $this->storeManager = $objectManager->get(StoreManager::class);

        // Set the name of the column as specified in the UI component xml file
        $this->priceColumn->setData('name', 'grand_total');
    }

    /**
     * Test that the correct currency symbol is used when formatting the purchase order price for the grid.
     *
     * This should be based on the currency used by the store where the purchase order was created
     * rather than the current store currency.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Store/_files/second_store_with_second_currency.php
     */
    public function testCurrencySymbolForPrice()
    {
        $adminCustomerId = $this->customerRepository->get('john.doe@example.com')->getId();

        $this->session->loginById($adminCustomerId);

        // Associate the company admin's purchase order to the second store and change its currency to euros
        $purchaseOrder = $this->getPurchaseOrderByCreatorId($adminCustomerId);
        $this->associatePurchaseOrderToSecondStore($purchaseOrder);

        /** @var Collection $purchaseOrderCollection */
        $purchaseOrderCollection = Bootstrap::getObjectManager()->create(Collection::class);
        $dataSource['data'] = $purchaseOrderCollection->load()->toArray();
        $dataSource = $this->priceColumn->prepareDataSource($dataSource);

        // Assert that the purchase order price is displayed using the currency symbol relative to the quote
        foreach ($dataSource['data']['items'] as $purchaseOrderRow) {
            $expectedCurrencySymbol = $purchaseOrderRow['creator_id'] === $adminCustomerId ? '€' : '$';
            $this->assertStringStartsWith($expectedCurrencySymbol, $purchaseOrderRow['grand_total']);
        }

        $this->session->logout();
    }

    /**
     * Get the purchase order created by the specified creator id.
     *
     * @param int $creatorId
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderByCreatorId(int $creatorId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('creator_id', $creatorId)
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        return reset($purchaseOrders);
    }

    /**
     * Associate the specified purchase order to a second store.
     *
     * This store is created via a fixture and uses euros as its currency.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function associatePurchaseOrderToSecondStore(PurchaseOrderInterface $purchaseOrder)
    {
        // Set the current store to the one created in the fixture
        $secondStore = $this->storeManager->getStore('fixture_second_store');
        $this->storeManager->setCurrentStore($secondStore);

        // Use the second store's currency for this purchase order's quote
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $quote->setStoreId($secondStore->getId());
        $quote->setQuoteCurrencyCode($secondStore->getCurrentCurrency()->getCode());
        $purchaseOrder->setSnapshotQuote($quote);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $quote->save();

        // Set the current store back to the default
        $defaultStore = $this->storeManager->getStore('default');
        $this->storeManager->setCurrentStore($defaultStore);
    }
}
