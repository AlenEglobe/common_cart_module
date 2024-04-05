<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionListItem;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var AccountManagementInterface $accountManagment */
$customerAccountManagement = $objectManager->get(AccountManagementInterface::class);
$customer = $customerAccountManagement->authenticate('customer@example.com', 'password');

/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
/** @var FilterBuilder $filterBuilder */
$filterBuilder = $objectManager->get(FilterBuilder::class);
/** @var RequisitionListRepository $requisitionListRepository */
$requisitionListRepository = $objectManager->get(RequisitionListRepository::class);

$filters[] = $filterBuilder
    ->setField('name')
    ->setConditionType('eq')
    ->setValue('list two')
    ->create();

$searchCriteriaBuilder->addFilters($filters);
$searchCriteria = $searchCriteriaBuilder->create();
$searchResults = $requisitionListRepository->getList($searchCriteria)->getItems();

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);

/** @var RequisitionListItem $requisitionListItemRepository */
$requisitionListItemRepository = $objectManager->create(RequisitionListItem::class);
foreach ($searchResults as $key => $value) {
    if ($value->getId()) {
        $requisitionList = $listRepository->deleteById($value->getId());
    }
}
