<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Framework\Registry $registry */
$registry = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var $company \Magento\Company\Model\Company */
$companyCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Company\Collection::class);
foreach ($companyCollection as $company) {
    $company->delete();
}

$structureCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Structure\Collection::class);
foreach ($structureCollection as $structure) {
    $structure->delete();
}

$roleCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Role\Collection::class);
foreach ($roleCollection as $role) {
    $role->delete();
}

$teamCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Team\Collection::class);
foreach ($teamCollection as $team) {
    $team->delete();
}

$customerCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\ResourceModel\Customer\Collection::class);
foreach ($customerCollection as $customer) {
    $customer->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
