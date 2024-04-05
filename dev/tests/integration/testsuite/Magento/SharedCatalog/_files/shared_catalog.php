<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class.php');
Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group.php');

/** @var \Magento\Tax\Model\ResourceModel\TaxClass\Collection $taxClassCollection */
$taxClassCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Tax\Model\ResourceModel\TaxClass\Collection::class);
/** @var \Magento\Tax\Model\ClassModel $taxClass */
$taxClass = $taxClassCollection->getLastItem();
$taxClassId = $taxClass->getId();
/** @var \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupCollection */
$customerGroupCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\ResourceModel\Group\Collection::class);
/** @var \Magento\Customer\Model\Group $customerGroup */
$customerGroup = $customerGroupCollection->getLastItem();
$customerGroupId = $customerGroup->getId();

/** @var $sharedCatalog SharedCatalog */
$sharedCatalog = Bootstrap::getObjectManager()->create(SharedCatalog::class);
$sharedCatalog->setName('shared catalog ' . time());
$sharedCatalog->setDescription('shared catalog description');
$sharedCatalog->setType(0);
$sharedCatalog->setCreatedBy(null);
$sharedCatalog->setTaxClassId($taxClassId);
$sharedCatalog->setCustomerGroupId($customerGroupId);
$sharedCatalog->setStoreId(0);
$sharedCatalog->save();
