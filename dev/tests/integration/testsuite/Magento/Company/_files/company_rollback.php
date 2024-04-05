<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Company;
use Magento\Company\Model\ResourceModel\Company\Collection as CompanyCollection;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;

/** @var Registry $registry */
$registry = Bootstrap::getObjectManager()->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var CustomerCollection $customerCollection */
$customerCollection = Bootstrap::getObjectManager()->create(CustomerCollection::class);

/** @var Customer $customer */
foreach ($customerCollection as $customer) {
    $customer->delete();
}

/** @var CompanyCollection $companyCollection */
$companyCollection = Bootstrap::getObjectManager()->create(CompanyCollection::class);

/** @var $company Company */
foreach ($companyCollection as $company) {
    $company->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
