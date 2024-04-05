<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/quote.php');
$user = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\User\Model\User::class);
$user->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

/** @var $quote \Magento\Quote\Model\Quote */
$quote = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Quote\Model\Quote::class);
$quote->load('test01', 'reserved_order_id');

$customer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\Customer::class);
/** @var Magento\Customer\Model\Customer $customer */
$customer->setWebsiteId(1)
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
$customerRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$companyRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(CompanyRepositoryInterface::class);
/** @var CompanyInterface $company */
$company = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(CompanyInterface::class);
$company->setCompanyName('company quote');
$company->setStatus(CompanyInterface::STATUS_APPROVED);
$company->setCompanyEmail('email@companyquote.com');
$company->setComment('comment');
$company->setSuperUserId($customer->getId());
$company->setCustomerGroupId(1);
$company->setSalesRepresentativeId($user->getId());
$company->setCountryId('US');
$company->setRegionId(1);
$company->setCity('City');
$company->setStreet(['avenue, 30']);
$company->setPostcode('postcode');
$company->setTelephone('123456');
$companyRepository->save($company);
$company = $companyRepository->get($company->getId());
$company->getExtensionAttributes()->getQuoteConfig()->setIsQuoteEnabled(true);
$companyRepository->save($company);

/** @var $creditLimit \Magento\CompanyCredit\Model\CreditLimit */
$creditLimitManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\CompanyCredit\Model\CreditLimitManagement::class);
$creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
$creditLimit->setCreditLimit(100);
$creditLimit->setBalance(-50);
$creditLimit->save();

$createdCustomer = $customerRepository->getById($customer->getId());
$quote->setCustomer($createdCustomer)->setCustomerIsGuest(false)->save();

foreach ($quote->getAllAddresses() as $address) {
    $address->setCustomerId($createdCustomer->getId())->save();
}

/** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
$quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Quote\Model\QuoteIdMaskFactory::class)
    ->create();
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setDataChanges(true);
$quoteIdMask->save();
