<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\RequisitionList\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class RequisitionListRepositoryInterfaceTest.
 * @package Magento\RequisitionList\Api
 */
class RequisitionListRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RequisitionListRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(RequisitionListRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/two_customers.php
     * @magentoDataFixture Magento/RequisitionList/_files/lists_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(RequisitionListInterface::NAME)
            ->setValue('list 2')
            ->create();
        $filter2 = $filterBuilder->setField(RequisitionListInterface::NAME)
            ->setValue('list 3')
            ->create();
        $filter3 = $filterBuilder->setField(RequisitionListInterface::NAME)
            ->setValue('list 4')
            ->create();
        $filter4 = $filterBuilder->setField(RequisitionListInterface::NAME)
            ->setValue('list 5')
            ->create();
        $filter5 = $filterBuilder->setField(RequisitionListInterface::CUSTOMER_ID)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(RequisitionListInterface::DESCRIPTION)
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
        $this->assertEquals('list 4', $items[0][RequisitionListInterface::NAME]);
    }
}
