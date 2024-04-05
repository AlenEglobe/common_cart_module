<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SharedCatalog\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\SharedCatalog\Api\Data\ProductItemInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class ProductItemRepositoryInterfaceTest.
 * @package Magento\SharedCatalog\Api
 */
class ProductItemRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ProductItemRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(ProductItemRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/SharedCatalog/_files/product_items_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(ProductItemInterface::SKU)
            ->setValue('sku 2')
            ->create();
        $filter2 = $filterBuilder->setField(ProductItemInterface::SKU)
            ->setValue('sku 3')
            ->create();
        $filter3 = $filterBuilder->setField(ProductItemInterface::SKU)
            ->setValue('sku 4')
            ->create();
        $filter4 = $filterBuilder->setField(ProductItemInterface::SKU)
            ->setValue('sku 5')
            ->create();
        $filter5 = $filterBuilder->setField(ProductItemInterface::CUSTOMER_GROUP_ID)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(ProductItemInterface::SKU)
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);

        $searchCriteriaBuilder->addFilters([$filter1, $filter2, $filter3, $filter4]);
        $searchCriteriaBuilder->addFilters([$filter5]);
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->setPageSize(2);
        $searchCriteriaBuilder->setCurrentPage(2);

        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);

        $this->assertEquals(3, $searchResult->getTotalCount());
        $items = array_values($searchResult->getItems());
        $this->assertCount(1, $items);
        $this->assertEquals('sku 2', $items[0][ProductItemInterface::SKU]);
    }
}
