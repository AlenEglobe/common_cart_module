<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

$bootstrap = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
/** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
$repository = $bootstrap->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$customer = $bootstrap->create(\Magento\Customer\Model\Customer::class);
$customer
    ->setWebsiteId(1)
    ->setId(30)
    ->setEmail('customernocompany@example.com')
    ->setPassword('password')
    ->setFirstname('John')
    ->setLastname('Smith');
$customer->isObjectNew(true);
$customer->save();
