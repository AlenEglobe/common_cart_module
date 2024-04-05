<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\CatalogPermissions\Model\Permission;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

//Add allow permission for parent category
/** @var Permission $permission */
$permission = $objectManager->create(Permission::class);
$permission->setWebsiteId(1)
    ->setCategoryId(2)
    ->setCustomerGroupId(null)
    ->setGrantCatalogCategoryView(Permission::PERMISSION_ALLOW)
    ->setGrantCatalogProductPrice(Permission::PERMISSION_ALLOW)
    ->setGrantCheckoutItems(Permission::PERMISSION_ALLOW)
    ->save();
