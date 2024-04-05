<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\RequisitionList\Model\RequisitionList;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\RequisitionList\Api\Data\RequisitionListItemInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class ItemsTest.
 * @package Magento\RequisitionList\Model\RequisitionList
 */
class ItemsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Items
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(Items::class);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/RequisitionList/_files/list_items_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(RequisitionListItemInterface::SKU)
            ->setValue('item 2')
            ->create();
        $filter2 = $filterBuilder->setField(RequisitionListItemInterface::SKU)
            ->setValue('item 3')
            ->create();
        $filter3 = $filterBuilder->setField(RequisitionListItemInterface::SKU)
            ->setValue('item 4')
            ->create();
        $filter4 = $filterBuilder->setField(RequisitionListItemInterface::SKU)
            ->setValue('item 5')
            ->create();
        $filter5 = $filterBuilder->setField(RequisitionListItemInterface::QTY)
            ->setConditionType('lt')
            ->setValue(5)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(RequisitionListItemInterface::OPTIONS)
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
        $this->assertEquals('item 4', $items[0][RequisitionListItemInterface::SKU]);
    }
}
