<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\TestFramework\Helper\Bootstrap;

$user = Bootstrap::getObjectManager()->create(\Magento\User\Model\User::class);
$user->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
/** @var Magento\Customer\Model\Customer $customer */
$customer = Bootstrap::getObjectManager()->create(\Magento\Customer\Model\Customer::class);
$customer
    ->setWebsiteId(1)
    ->setEmail('email@secondcompany.com')
    ->setPassword('password')
    ->setFirstname('Jim')
    ->setLastname('Smith');
$customer->isObjectNew(true);
$customer->save();
$customer = $customerRepository->get('email@secondcompany.com');

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
/** @var CompanyInterface $company */
$company = Bootstrap::getObjectManager()->create(CompanyInterface::class);
$company->setCompanyName('second company');
$company->setStatus(CompanyInterface::STATUS_APPROVED);
$company->setCompanyEmail('email@secondcompany.com');
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
