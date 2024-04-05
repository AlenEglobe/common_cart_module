<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\RequisitionList\Model\RequisitionListItem;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\RequisitionList\Api\Data\RequisitionListItemInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterfaceFactory;
use Magento\RequisitionList\Model\RequisitionListRepository;

/**
 * @magentoDataFixture Magento/Customer/_files/customer.php
 * @magentoDataFixture Magento/ConfigurableProduct/_files/configurable_products.php
 * @magentoDataFixture Magento/RequisitionList/_files/list.php
 * @magentoDbIsolation disabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SaveHandler
     */
    private $saveHandler;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RequisitionListRepository
     */
    private $requisitionListRepository;

    /**
     * @var RequisitionListInterfaceFactory
     */
    private $requisitionListInterfaceFactory;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->saveHandler = $objectManager->create(SaveHandler::class);
        $this->productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $this->requisitionListRepository = $objectManager->create(
            RequisitionListRepositoryInterface::class
        );
        $this->requisitionListInterfaceFactory = $objectManager->get(RequisitionListInterfaceFactory::class);
        $this->stockRegistry = $objectManager->get(StockRegistryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
    }

    /**
     * Test Save requisition list updated_at date
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @return void
     */
    public function testUpdateAtRequisitionListUpdatedAt()
    {
        $requisitionList = $this->requisitionListInterfaceFactory->create([
            'data' => [
                'name' => 'Test List',
                'description' => 'Test list description',
                'customer_id' => $this->customerRepository->get('customer@example.com')->getId()
            ]
        ]);
        $this->requisitionListRepository->save($requisitionList);
        $prevUpdatedAt = $requisitionList->getUpdatedAt();

        $requisitionList->setName('New requisition test name');
        sleep(1);

        $this->requisitionListRepository->save($requisitionList);
        $requisitionList = $this->getRequisitionList();

        $this->assertNotEquals(
            $prevUpdatedAt,
            $requisitionList->getUpdatedAt(),
            'updated_at has not changed when Requisition List was updated'
        );
    }

    /**
     * Test Save requisition list item with configurable product
     *
     * @return void
     */
    public function testSaveItemConfigurableProduct()
    {
        $productSku = 'configurable';
        $requisitionListProductData = new DataObject(['sku' => $productSku]);
        $product = $this->productRepository->get($productSku);
        $requisitionListOptions = $this->resolveRequisitionListOptions($product);
        $message = $this->saveHandler->saveItem(
            $requisitionListProductData,
            $requisitionListOptions,
            0,
            $this->getRequisitionList()->getId()
        );
        $this->assertEquals(
            'Product Configurable Product has been added to the requisition list list name.',
            $message->render()
        );
    }

    /**
     * Test Save requisition list item with grouped product
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped_with_simple.php
     * @magentoDataFixture Magento/RequisitionList/_files/list.php
     * @magentoDbIsolation disabled
     *
     * @return void
     */
    public function testSaveItemGroupedProduct(): void
    {
        $productSku = 'grouped';
        $product = $this->productRepository->get($productSku);
        $requisitionListOptions = [
            'product' => $product->getId(),
            'item' => $product->getId(),
            'super_group' => [
                11 => 2,
                22 => 3,
            ]
        ];
        $requisitionListProductData = new DataObject(
            [
                'sku' => $productSku,
                'options' => $requisitionListOptions
            ]
        );
        $message = $this->saveHandler->saveItem(
            $requisitionListProductData,
            $requisitionListOptions,
            0,
            $this->getRequisitionList()->getId()
        );
        $this->assertEquals(
            'Product Grouped Product has been added to the requisition list list name.',
            $message->render()
        );
        $items = $this->getRequisitionList()->getItems();
        $qty = [];
        foreach ($items as $item) {
            $qty[$item->getSku()] = $item->getQty();
        }
        $this->assertEquals(['simple_11' => 2, 'simple_22' => 3], $qty);
    }

    /**
     * Test Save requisition list item without configurable options
     *
     * @return void
     */
    public function testSaveItemConfigurableProductException()
    {
        $productSku = 'configurable';
        $product = $this->productRepository->get($productSku);
        $requisitionListProductData = new DataObject(['sku' => $productSku]);
        $requisitionListOptions = ['product' => $product->getId(), 'options' => []];
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('You need to choose options for your item.');
        $this->saveHandler->saveItem(
            $requisitionListProductData,
            $requisitionListOptions,
            0,
            $this->getRequisitionList()->getId()
        );
    }

    /**
     * Test Save requisition list item with decimal qty
     *
     * @param bool $isQtyDecimal
     * @param int|float|bool $inputQty
     * @param float $expectedQty
     * @dataProvider saveItemDecimalQtyDataProvider
     */
    public function testSaveItemDecimalQty(bool $isQtyDecimal, $inputQty, float $expectedQty)
    {
        $productSku = 'configurable';
        $this->updateIsQtyDecimal($productSku, $isQtyDecimal);
        $requisitionList = $this->getRequisitionList();

        $requisitionListProductData = new DataObject(['sku' => $productSku]);
        if ($inputQty !== false) {
            $requisitionListProductData->setOptions(['qty' => $inputQty]);
        }
        $product = $this->productRepository->get($productSku);
        $requisitionListOptions = $this->resolveRequisitionListOptions($product);
        $message = $this->saveHandler->saveItem(
            $requisitionListProductData,
            $requisitionListOptions,
            0,
            $requisitionList->getId()
        );
        $this->assertEquals(
            'Product Configurable Product has been added to the requisition list list name.',
            $message->render()
        );

        $savedItem = $this->getRequisitionListLastItem($requisitionList);
        $this->assertEquals($expectedQty, (float)$savedItem->getQty());
    }

    /**
     * Data provider for testSaveItemDecimalQty
     *
     * @return array
     */
    public function saveItemDecimalQtyDataProvider(): array
    {
        return [
            ['isQtyDecimal' => false, 'inputQty' => 0, 'expectedQty' => 1.0],
            ['isQtyDecimal' => false, 'inputQty' => false, 'expectedQty' => 1.0],
            ['isQtyDecimal' => false, 'inputQty' => 2.5, 'expectedQty' => 2.0],
            ['isQtyDecimal' => true, 'inputQty' => 2.5, 'expectedQty' => 2.5],
        ];
    }

    /**
     * Load customer requisition list
     *
     * @return RequisitionListInterface
     */
    private function getRequisitionList(): RequisitionListInterface
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);
        $filter = $filterBuilder->setField(RequisitionListInterface::CUSTOMER_ID)->setValue(1)->create();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter]);
        $list = $this->requisitionListRepository->getList($searchCriteriaBuilder->create())->getItems();

        return array_pop($list);
    }

    /**
     * Get last item from customer requisition list
     *
     * @param RequisitionListInterface $requisitionList
     * @return RequisitionListItemInterface
     */
    private function getRequisitionListLastItem(RequisitionListInterface $requisitionList): RequisitionListItemInterface
    {
        $items = $requisitionList->getItems();

        return end($items);
    }

    /**
     * Retrieve configure option for requisition list
     *
     * @param ProductInterface $product
     * @return array
     */
    private function resolveRequisitionListOptions(ProductInterface $product): array
    {
        $requisitionListOptions = ['product' => $product->getId(), 'options' => []];
        $productOptions = $product->getTypeInstance()->getConfigurableOptions($product);
        foreach ($productOptions as $attributeId => $optionItems) {
            $requisitionListOptions['super_attribute'] =
                [
                    $attributeId => array_pop($optionItems)['value_index'],
                ];
        }

        return $requisitionListOptions;
    }

    /**
     * Update Is use qty decimal for poduct
     *
     * @param string $sku
     * @param bool $isQtyDecimal
     * @return void
     */
    private function updateIsQtyDecimal(string $sku, bool $isQtyDecimal): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsQtyDecimal($isQtyDecimal);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }
}
