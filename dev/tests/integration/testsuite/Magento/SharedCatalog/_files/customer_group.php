<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Tax\Model\ResourceModel\TaxClass\Collection $taxClassCollection */
$taxClassCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Tax\Model\ResourceModel\TaxClass\Collection::class);
/** @var \Magento\Tax\Model\ClassModel $taxClass */
$taxClass = $taxClassCollection->getLastItem();
$taxClassId = $taxClass->getId();

/** @var \Magento\Customer\Model\ResourceModel\GroupRepository $groupRepository */
$groupRepository = Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\ResourceModel\GroupRepository::class);

/** @var \Magento\Customer\Api\Data\GroupInterfaceFactory $customerGroup */
$groupFactory = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\Data\GroupInterfaceFactory::class);
$group = $groupFactory->create(
    [
        'data' => [
            'code' => 'customer group ' . time(),
            'tax_class_id' => $taxClassId
        ]
    ]
);
$groupRepository->save($group);
