<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;

/** @var Registry $registry */
$registry = Bootstrap::getObjectManager()->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$customerCollection = Bootstrap::getObjectManager()->create(Collection::class);
foreach ($customerCollection as $customer) {
    $customer->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
