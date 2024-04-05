<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

for ($customerId = 1; $customerId <= 5; $customerId++) {
    $customer = Bootstrap::getObjectManager()->create(\Magento\Customer\Model\Customer::class);
    /** @var Magento\Customer\Model\Customer $customer */
    $customer->setWebsiteId(1)
        ->setId($customerId)
        ->setEmail('email' . $customerId . '@companyquote.com')
        ->setPassword('password')
        ->setFirstname('John')
        ->setLastname('Smith');
    $customer->isObjectNew(true);
    $customer->save();
}
