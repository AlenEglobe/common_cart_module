<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Framework\Registry $registry */
$registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var \Magento\Customer\Model\Group $customerGroup */
$customerGroupCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\ResourceModel\Group\Collection::class);
$baseCustomerGroupIds = [0 => 0, 1 => 1, 2 => 2, 3 => 3];

foreach ($customerGroupCollection as $customerGroup) {
    if (!isset($baseCustomerGroupIds[$customerGroup->getId()])) {
        $customerGroup->delete();
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
