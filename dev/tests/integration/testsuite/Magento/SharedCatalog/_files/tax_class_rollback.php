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

/** @var \Magento\Tax\Model\ClassModel $taxClass */
$taxClassCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Tax\Model\ResourceModel\TaxClass\Collection::class);
foreach ($taxClassCollection as $taxClass) {
    if ($taxClass->getClassId() != 2 && $taxClass->getClassId() != 3) {
        $taxClass->delete();
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
