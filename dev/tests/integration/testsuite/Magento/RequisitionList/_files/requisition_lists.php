<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\AccountManagementInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionList;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$customerAccountManagement = $objectManager->get(AccountManagementInterface::class);
$customer = $customerAccountManagement->authenticate('customer@example.com', 'password');

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('List 10');
$list->setCustomerId($customer->getId());
$list->setDescription('List 10');

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
$listRepository->save($list);

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('List 11');
$list->setCustomerId($customer->getId());
$list->setDescription('List 11');

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
$listRepository->save($list);

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('List 12');
$list->setCustomerId($customer->getId());
$list->setDescription('List 12');

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
$listRepository->save($list);

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('List 13');
$list->setCustomerId($customer->getId());
$list->setDescription('List 13');

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
$listRepository->save($list);
