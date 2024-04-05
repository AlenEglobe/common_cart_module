<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionList;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$customerAccountManagement = $objectManager->get(AccountManagementInterface::class);
$customer = $customerAccountManagement->authenticate('customer@example.com', 'password');

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('Test - Requisition List');
$list->setCustomerId($customer->getId());
$list->setDescription('Test - Requisition List description');

$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);

$listRepository->save($list);

return $list;
