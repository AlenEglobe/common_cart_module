<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;

/**
 * Get Requisition list id by Requisition list name
 */
class GetRequisitionList
{
    /**
     * @var RequisitionListRepositoryInterface
     */
    private $requisitionListRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * GetRequisitionList constructor
     *
     * @param RequisitionListRepositoryInterface $requisitionListRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     */
    public function __construct(
        RequisitionListRepositoryInterface $requisitionListRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->requisitionListRepository = $requisitionListRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Get Requisition list id by name
     *
     * @param string $name
     * @return int
     */
    public function execute(string $name): int
    {
        $filters[] = $this->filterBuilder
            ->setField('name')
            ->setConditionType('eq')
            ->setValue($name)
            ->create();

        $this->searchCriteriaBuilder->addFilters($filters);
        $searchCriteria = $this->searchCriteriaBuilder->create()->setPageSize(1);
        $searchResults = $this->requisitionListRepository->getList($searchCriteria)->getItems();
        $listId = 0;
        foreach ($searchResults as $key => $value) {
            if ($value->getId()) {
                $listId = (int)$key;
            }
        }

        return $listId;
    }
}
