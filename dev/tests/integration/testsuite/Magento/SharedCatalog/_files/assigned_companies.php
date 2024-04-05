<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\ResourceModel\Company\Collection;
use Magento\Company\Model\ResourceModel\Company\CollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/companies.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Collection $collection */
$collection = $objectManager->get(CollectionFactory::class)->create();
$companies = $collection->addFieldToFilter('company_name', ['like' => 'Company'])->getItems();
/** @var \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection $sharedCatalogCollection */
$sharedCatalogCollection = $objectManager->create(
    \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection::class
);
$sharedCatalog = $sharedCatalogCollection->getLastItem();
/** @var \Magento\SharedCatalog\Api\SharedCatalogManagementInterface $sharedCatalogManagement */
$sharedCatalogManagement = $objectManager->create(
    \Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class
);
$publicCatalog = $sharedCatalogManagement->getPublicCatalog();
/** @var \Magento\SharedCatalog\Api\CompanyManagementInterface $companyManagement */
$companyManagement = $objectManager->create(
    \Magento\SharedCatalog\Api\CompanyManagementInterface::class
);
$companyManagement->assignCompanies($sharedCatalog->getId(), $companies);
$companyManagement->assignCompanies($publicCatalog->getId(), $companies);
