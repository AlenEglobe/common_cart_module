<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Model\User;

/** @var $configWriter WriterInterface */
$configWriter = Bootstrap::getObjectManager()->get(WriterInterface::class);

$path = 'btob/website_configuration/company_active';
$configWriter->save($path, 1, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);

$cacheTypeList = Bootstrap::getObjectManager()->get(TypeListInterface::class);
$cacheTypeList->cleanType('config');

$user = Bootstrap::getObjectManager()->create(User::class);
$user->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

/** @var $repository CustomerRepositoryInterface */
$repository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);
$customer = Bootstrap::getObjectManager()->create(Customer::class);
/** @var Customer $customer */
$customer->setWebsiteId(1)
    ->setEmail('customrole@company.com')
    ->setPassword('password')
    ->setFirstname('John')
    ->setLastname('Smith');
$customer->isObjectNew(true);
$customer->save();
$customer = $repository->get('customrole@company.com');

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
/** @var CompanyInterface $company */
$company = Bootstrap::getObjectManager()->create(CompanyInterface::class);
$company->setCompanyName('Company with role');
$company->setStatus(CompanyInterface::STATUS_APPROVED);
$company->setCompanyEmail('customrole@company.com');
$company->setComment('comment');
$company->setSuperUserId($customer->getId());
$company->setSalesRepresentativeId($user->getId());
$company->setCustomerGroupId(1);
$company->setCountryId('TV');
$company->setCity('City');
$company->setStreet(['avenue, 30']);
$company->setPostcode('postcode');
$company->setTelephone('123456');
$companyRepository->save($company);
$company = $companyRepository->get($company->getId());
$company->getExtensionAttributes()->getQuoteConfig()->setIsQuoteEnabled(true);
$companyRepository->save($company);

/** @var RoleInterface $role */
$role = Bootstrap::getObjectManager()->create(RoleInterface::class);
$role->setCompanyId($company->getId());
$role->setRoleName('custom company role');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = Bootstrap::getObjectManager()->get(RoleRepositoryInterface::class);
$roleRepository->save($role);
