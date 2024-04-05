<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$customerAccountManagement = $objectManager->get(AccountManagementInterface::class);
$customer = $customerAccountManagement->authenticate('customer@example.com', 'password');

$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$filterBuilder = $objectManager->get(FilterBuilder::class);
$requisitionListRepository = $objectManager->get(RequisitionListRepository::class);

$filters[] = $filterBuilder
    ->setField('name')
    ->setConditionType('eq')
    ->setValue('Test - Requisition List Two')
    ->create();

$searchCriteriaBuilder->addFilters($filters);
$searchCriteria = $searchCriteriaBuilder->create();
$searchResults = $requisitionListRepository->getList($searchCriteria)->getItems();

$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
foreach ($searchResults as $key => $value) {
    if ($value->getId()) {
        $listRepository->deleteById($value->getId());
    }
}
