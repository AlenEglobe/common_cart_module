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
use Magento\NegotiableQuote\Api\Data\CommentInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class CommentRepositoryInterfaceTest.
 * @package Magento\NegotiableQuote\Model
 */
class CommentRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CommentRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(CommentRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/NegotiableQuote/_files/comments_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(CommentInterface::COMMENT)
            ->setValue('comment 2')
            ->create();
        $filter2 = $filterBuilder->setField(CommentInterface::COMMENT)
            ->setValue('comment 3')
            ->create();
        $filter3 = $filterBuilder->setField(CommentInterface::COMMENT)
            ->setValue('comment 4')
            ->create();
        $filter4 = $filterBuilder->setField(CommentInterface::COMMENT)
            ->setValue('comment 5')
            ->create();
        $filter5 = $filterBuilder->setField(CommentInterface::IS_DECLINE)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(CommentInterface::CREATOR_ID)
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
        $this->assertEquals('comment 3', $items[0][CommentInterface::COMMENT]);
    }
}
