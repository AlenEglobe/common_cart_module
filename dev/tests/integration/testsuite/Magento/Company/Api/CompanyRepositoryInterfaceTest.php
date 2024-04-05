<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Company\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class CompanyRepositoryInterfaceTest.
 */
class CompanyRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CompanyRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
    }

    /**
     * Test backoffice company grid filtering, sorting, and pagination using search criteria passed to Company
     * repository
     *
     * Given 5 companies
     * When a search criteria is applied with a filter by status of APPROVED
     * And filter where company name is either one of 4 companies' names
     * And order by comment field descending
     * And set page number and page size both to 2
     * Then the total search result count (which does not take page size into account) is 3
     * And the result set count is 1
     * And the sole result is the company with highest alphabetical order for comment field (last result)
     *
     * @magentoDataFixture Magento/Company/_files/companies_for_search.php
     */
    public function testCompanyFilteringSortingPagination()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(CompanyInterface::NAME)
            ->setValue('company 2')
            ->create();
        $filter2 = $filterBuilder->setField(CompanyInterface::NAME)
            ->setValue('company 3')
            ->create();
        $filter3 = $filterBuilder->setField(CompanyInterface::NAME)
            ->setValue('company 4')
            ->create();
        $filter4 = $filterBuilder->setField(CompanyInterface::NAME)
            ->setValue('company 5')
            ->create();
        $filter5 = $filterBuilder->setField(CompanyInterface::STATUS)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(CompanyInterface::COMMENT)
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
        $this->assertEquals('company 4', $items[0][CompanyInterface::NAME]);
    }
}
