<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Company;
use Magento\Company\Model\ResourceModel\Company\Collection as CompanyCollection;
use Magento\Company\Model\ResourceModel\Structure\Collection as StructureCollection;
use Magento\Company\Model\ResourceModel\Team\Collection as TeamCollection;
use Magento\Company\Model\Structure;
use Magento\Company\Model\Team;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Cache\TypeListInterface;

/** @var Registry $registry */
$registry = Bootstrap::getObjectManager()->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var CustomerCollection $customerCollection */
$customerCollection = Bootstrap::getObjectManager()->get(CustomerCollection::class);

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

/** @var StructureCollection $companyCollection */
$structureCollection = Bootstrap::getObjectManager()->create(StructureCollection::class);

/** @var $company Structure */
foreach ($structureCollection as $structure) {
    $structure->delete();
}

/** @var TeamCollection $companyCollection */
$structureCollection = Bootstrap::getObjectManager()->create(TeamCollection::class);

/** @var $company Team */
foreach ($structureCollection as $structure) {
    $structure->delete();
}

/** @var $configWriter WriterInterface */
$configWriter = Bootstrap::getObjectManager()->get(WriterInterface::class);

$path = 'btob/website_configuration/company_active';
$configWriter->save($path, 0, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
$cacheTypeList = Bootstrap::getObjectManager()->get(TypeListInterface::class);
$cacheTypeList->cleanType('config');

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
