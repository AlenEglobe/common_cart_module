<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Company\Model\ResourceModel\Company\Collection as CompanyCollection;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var CompanyCollection $companyCollection */
$companyCollection = Bootstrap::getObjectManager()->create(CompanyCollection::class);
$companyCollection->addFieldToFilter('company_name', ['like' => '%Company%']);
foreach ($companyCollection as $company) {
    $company->delete();
}

/** @var CustomerCollection $customerCollection */
$customerCollection = Bootstrap::getObjectManager()->create(CustomerCollection::class);
$customerCollection->addAttributeToFilter('firstname', ['like' => '%John%']);
foreach ($customerCollection as $customer) {
    $customer->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
