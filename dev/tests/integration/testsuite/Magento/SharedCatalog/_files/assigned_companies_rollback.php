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

/** @var \Magento\Company\Model\ResourceModel\Company\Collection $companyCollection */
$companyCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Model\ResourceModel\Company\Collection::class);
$companies = $companyCollection->getItems();
foreach (array_slice($companies, -5) as $company) {
    $company->delete();
}

/** @var $customer \Magento\Customer\Model\Customer*/
$customer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
    \Magento\Customer\Model\Customer::class
);
for ($customerId = 1; $customerId <= 5; $customerId++) {
    $customer->load($customerId);
    if ($customer->getId()) {
        $customer->delete();
    }
}
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
