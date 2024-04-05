<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group_rollback.php');

$registry = Bootstrap::getObjectManager()->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$sharedCatalogCollection = Bootstrap::getObjectManager()->create(Collection::class);

foreach ($sharedCatalogCollection as $sharedCatalog) {
    if ($sharedCatalog->getId() !== 1 && $sharedCatalog->getType() !== 1) {
        $sharedCatalog->delete();
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class_rollback.php');
