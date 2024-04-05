<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Company\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Company\Api\Data\StructureInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test for StructureRepository.
 */
class StructureRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Company\Model\StructureRepository
     */
    private $repository;

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(\Magento\Company\Model\StructureRepository::class);
    }

    /**
     * Test storefront company structure filtering, sorting, and pagination using search criteria passed to Company
     * Structure repository
     *
     * Given a company structure consisting of 5 items
     * When a search criteria is applied with a filter where path name is either one of 4 paths' names
     * And order by position field descending
     * And set page number and page size both to 2
     * Then the total search result count (which does not take page size into account) is 3
     * And the result set count is 1
     * And the sole result is the company with lowest numerical value for position field (last result)
     *
     * @magentoDataFixture Magento/Company/_files/structure_for_search.php
     * @return void
     */
    public function testCompanyStructureFilteringSortingPagination()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(StructureInterface::PATH)
            ->setValue('item 2')
            ->create();
        $filter2 = $filterBuilder->setField(StructureInterface::PATH)
            ->setValue('item 3')
            ->create();
        $filter3 = $filterBuilder->setField(StructureInterface::PATH)
            ->setValue('item 4')
            ->create();
        $filter4 = $filterBuilder->setField(StructureInterface::PATH)
            ->setValue('item 5')
            ->create();
        $filter5 = $filterBuilder->setField(StructureInterface::LEVEL)
            ->setValue(0)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(StructureInterface::POSITION)
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
        $this->assertEquals('item 4', $items[0][StructureInterface::PATH]);
    }
}
