<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Setup\Fixtures\FixturesAsserts;

/**
 * Performs assertion that generated shared catalogs are valid
 * after running setup:performance:generate-fixtures command
 */
class SharedCatalogAssert
{
    /**
     * @var \Magento\SharedCatalog\Model\Repository
     */
    private $sharedCatalogRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param \Magento\SharedCatalog\Model\Repository $sharedCatalogRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        \Magento\SharedCatalog\Model\Repository $sharedCatalogRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->sharedCatalogRepository = $sharedCatalogRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Asserts that generated shared catalogs are valid
     * Checks only for shared catalog count for now
     *
     * @return bool
     * @throws \AssertionError
     */
    public function assert()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $sharedCatalogs = $this->sharedCatalogRepository->getList($searchCriteria)->getItems();

        if (18 !== count($sharedCatalogs)) {
            throw new \AssertionError('Shared catalogs amount is wrong');
        }

        return true;
    }
}
