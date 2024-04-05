<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\Tax\Model\ResourceModel\TaxClass\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class.php');
Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group.php');

$taxClassCollection = Bootstrap::getObjectManager()->create(Collection::class);
$taxClass = $taxClassCollection->getLastItem();
$taxClassId = $taxClass->getId();
$customerGroupCollection = Bootstrap::getObjectManager()->create(GroupCollection::class);
$customerGroup = $customerGroupCollection->getLastItem();
$customerGroupId = $customerGroup->getId();

$sharedCatalog = Bootstrap::getObjectManager()->create(SharedCatalog::class);
$sharedCatalog->setName('shared catalog ' . time());
$sharedCatalog->setDescription('shared catalog description');
$sharedCatalog->setType(0);
$sharedCatalog->setCreatedBy(null);
$sharedCatalog->setTaxClassId($taxClassId);
$sharedCatalog->setCustomerGroupId($customerGroupId);
$sharedCatalog->save();
