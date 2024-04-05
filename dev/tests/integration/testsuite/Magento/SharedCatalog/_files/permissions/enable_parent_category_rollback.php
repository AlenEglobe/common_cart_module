<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\CatalogPermissions\Model\ResourceModel\Permission\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Framework\Registry;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var Collection $permissionCollection */
$permissionCollection = $objectManager->create(Collection::class);
$permissionCollection->addFieldToFilter('category_id', 2);
foreach ($permissionCollection as $permission) {
    $permission->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
