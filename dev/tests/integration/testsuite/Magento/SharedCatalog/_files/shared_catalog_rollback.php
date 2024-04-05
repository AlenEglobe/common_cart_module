<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group_rollback.php');

/** @var \Magento\Framework\Registry $registry */
$registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
$sharedCatalogCollection = Bootstrap::getObjectManager()
    ->create(\Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection::class);

foreach ($sharedCatalogCollection as $sharedCatalog) {
    if ($sharedCatalog->getId() != 1 && $sharedCatalog->getType() != 1) {
        $sharedCatalog->delete();
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class_rollback.php');
