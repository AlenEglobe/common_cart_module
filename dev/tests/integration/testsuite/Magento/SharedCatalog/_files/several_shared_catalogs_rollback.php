<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Registry;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group_rollback.php');

/** @var Registry $registry */
$registry = Bootstrap::getObjectManager()->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var Collection $sharedCatalogCollection */
$sharedCatalogCollection = Bootstrap::getObjectManager()
    ->create(Collection::class);

$sharedCatalogCollection->addFieldToFilter('description', ['like' => '%MASS%']);

foreach ($sharedCatalogCollection as $sharedCatalog) {
    $sharedCatalog->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class_rollback.php');
