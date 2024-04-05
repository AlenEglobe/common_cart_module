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

/** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
$repository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$customer = Bootstrap::getObjectManager()->create(\Magento\Customer\Model\Customer::class);
/** @var Magento\Customer\Model\Customer $customer */
$customer
    ->setWebsiteId(1)
    ->setId(1)
    ->setEmail('email@companyquote.com')
    ->setPassword('password')
    ->setFirstname('John')
    ->setLastname('Smith');
$customer->isObjectNew(true);
$customer->save();
/** @var \Magento\JwtUserToken\Api\RevokedRepositoryInterface $revokedRepo */
$revokedRepo = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->get(\Magento\JwtUserToken\Api\RevokedRepositoryInterface::class);
$revokedRepo->saveRevoked(
    new \Magento\JwtUserToken\Api\Data\Revoked(
        \Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER,
        (int) $customer->getId(),
        time() - 3600 * 24
    )
);
$customer = $repository->get('email@companyquote.com');

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
/** @var CompanyInterface $company */
$company = Bootstrap::getObjectManager()->create(CompanyInterface::class);
$company->setCompanyName('company quote');
$company->setStatus(CompanyInterface::STATUS_APPROVED);
$company->setCompanyEmail('email@companyquote.com');
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
$company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
$companyRepository->save($company);
