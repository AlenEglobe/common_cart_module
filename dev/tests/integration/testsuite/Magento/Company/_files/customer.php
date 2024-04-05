<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Customer\Model\Customer;

$objectManager = Bootstrap::getObjectManager();
/** @var Customer $customer */
$customer = $objectManager->create(Customer::class);
$customer->setWebsiteId(1)
    ->setEmail('company_related@company.com')
    ->setPassword('password')
    ->setFirstname('John')
    ->setLastname('Smith');
$customer->isObjectNew(true);
$customer->save();
