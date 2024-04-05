<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\NegotiableQuote\Api\Data\HistoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class HistoryRepositoryInterfaceTest.
 * @package Magento\NegotiableQuote\Model
 */
class HistoryRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var HistoryRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(HistoryRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/NegotiableQuote/_files/history_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(HistoryInterface::LOG_DATA)
            ->setValue('log data 2')
            ->create();
        $filter2 = $filterBuilder->setField(HistoryInterface::LOG_DATA)
            ->setValue('log data 3')
            ->create();
        $filter3 = $filterBuilder->setField(HistoryInterface::LOG_DATA)
            ->setValue('log data 4')
            ->create();
        $filter4 = $filterBuilder->setField(HistoryInterface::LOG_DATA)
            ->setValue('log data 5')
            ->create();
        $filter5 = $filterBuilder->setField(HistoryInterface::IS_SELLER)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(HistoryInterface::AUTHOR_ID)
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
        $this->assertEquals('log data 3', $items[0][HistoryInterface::LOG_DATA]);
    }
}
