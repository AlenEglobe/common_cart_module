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

/** @var $company \Magento\Company\Model\Company */
$companyCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Company\Collection::class);
foreach ($companyCollection as $company) {
    $company->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
