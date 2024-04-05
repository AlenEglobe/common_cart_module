<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\RequisitionList;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;

/**
 * Get Requisition list id by Requisition list name
 */
class GetRequisitionListItemId
{
    /**
     * @var RequisitionListRepositoryInterface
     */
    private $repository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param RequisitionListRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        RequisitionListRepositoryInterface $repository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->repository = $repository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Get Requisition list id by name
     *
     * @param string $name
     * @param string $sku
     * @return int
     */
    public function execute(string $name, string $sku): int
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('name', $name, 'eq')
            ->create();
        $requisitionLists = $this->repository->getList($searchCriteria)->getItems();
        $itemId = 0;
        foreach ($requisitionLists as $requisitionList) {
            $requisitionListItems = $requisitionList->getItems();
            foreach ($requisitionListItems as $item) {
                if ($item->getSku() == $sku) {
                    $itemId = $item->getId();
                }
            }
        }
        return (int)$itemId;
    }
}
