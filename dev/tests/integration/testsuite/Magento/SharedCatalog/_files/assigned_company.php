<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

$customer = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\Data\CustomerInterface::class);
$customer->setWebsiteId(1)
    ->setEmail('email1@companyquote.com')
    ->setFirstname('John')
    ->setLastname('Smith');
$customerRepository = Bootstrap::getObjectManager()->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$customer = $customerRepository->save($customer, 'password');

$quote = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\CartInterface::class);
$quote->setCustomer($customer);
$quote->setStoreId(1);
$quoteRepository = Bootstrap::getObjectManager()->get(\Magento\Quote\Api\CartRepositoryInterface::class);
$quoteRepository->save($quote);

$companyFactory = Bootstrap::getObjectManager()->create(\Magento\Company\Api\Data\CompanyInterfaceFactory::class);
$company = $companyFactory->create(
    [
        'data' => [
            'status' => \Magento\Company\Api\Data\CompanyInterface::STATUS_APPROVED,
            'company_name' => 'Company 1',
            'legal_name' => 'Company legal name 1',
            'company_email' => 'email1@domain.com',
            'street' => 'Street 1',
            'city' => 'City1',
            'country_id' => 'US',
            'region' => 'AL',
            'region_id' => 1,
            'postcode' => '22222',
            'telephone' => '2222222',
            'super_user_id' => $customer->getId(),
            'customer_group_id' => 1
        ]
    ]
);
$companyRepository = Bootstrap::getObjectManager()->get(\Magento\Company\Api\CompanyRepositoryInterface::class);
$companyRepository->save($company);

$sharedCatalogManagement = Bootstrap::getObjectManager()->create(
    \Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class
);
$publicCatalog = $sharedCatalogManagement->getPublicCatalog();
$companyManagement = Bootstrap::getObjectManager()->create(
    \Magento\SharedCatalog\Api\CompanyManagementInterface::class
);
$companyManagement->assignCompanies($publicCatalog->getId(), [$company]);
