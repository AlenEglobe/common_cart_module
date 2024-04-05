<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\CompanyRepositoryInterface;

$user = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\User\Model\User::class);
$user->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

$customersData = [
    [
        'customer' => [
            'email' => 'email@companyquote.com',
            'password' => 'password',
            'firstname' => 'John',
            'lastname' => 'Smith'
        ],
        'company' => [
            'company_name' => 'company quote',
            'company_email' => 'email@companyquote.com',
            'comment' => 'comment',
            'country_id' => 'TV',
            'city' => 'City',
            'street' => ['avenue', '30'],
            'postcode' => 'postcode',
            'telephone' => '123456',
        ],
        'credit_limit' => [
            'credit_limit' => 100,
            'balance' => -50,
        ]
    ],
    [
        'customer' => [
            'email' => 'email@companysecondquote.com',
            'password' => 'password',
            'firstname' => 'John',
            'lastname' => 'Doe'
        ],
        'company' => [
            'company_name' => 'Company second quote',
            'company_email' => 'email@companysecondquote.com',
            'comment' => 'second company',
            'country_id' => 'TV',
            'city' => 'CityCity',
            'street' => ['Street', '15'],
            'postcode' => 'post123',
            'telephone' => '24680',
        ],
        'credit_limit' => [
            'credit_limit' => 200,
            'balance' => 20,
        ]
    ],
    [
        'customer' => [
            'email' => 'email@quote.com',
            'password' => 'password',
            'firstname' => 'Michael',
            'lastname' => 'Doe'
        ],
        'company' => [
            'company_name' => 'Quote',
            'company_email' => 'email@quote.com',
            'comment' => 'Quote company',
            'country_id' => 'TV',
            'city' => 'District',
            'street' => ['Streetway', '11'],
            'postcode' => '123123',
            'telephone' => '226688',
        ],
        'credit_limit' => [
            'credit_limit' => 500,
            'balance' => 50,
        ]
    ]
];

$companyRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(CompanyRepositoryInterface::class);


foreach ($customersData as $customerData) {
    $customer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
        ->create(\Magento\Customer\Model\Customer::class);
    /** @var Magento\Customer\Model\Customer $customer */
    $customer->setWebsiteId(1);
    $customer->addData($customerData['customer']);
    $customer->save();

    /** @var CompanyInterface $company */
    $company = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(CompanyInterface::class);
    $company->setStatus(CompanyInterface::STATUS_APPROVED);
    $company->setSuperUserId($customer->getId());
    $company->setCustomerGroupId(1);
    $company->setSalesRepresentativeId($user->getId());
    $company->addData($customerData['company']);

    $companyRepository->save($company);
    $company = $companyRepository->get($company->getId());
    $company->getExtensionAttributes()->getQuoteConfig()->setIsQuoteEnabled(true);
    $companyRepository->save($company);

    /** @var $creditLimit \Magento\CompanyCredit\Model\CreditLimit */
    $creditLimitManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
        ->create(\Magento\CompanyCredit\Model\CreditLimitManagement::class);
    $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
    $creditLimit->addData($customerData['credit_limit']);
    $creditLimit->save();
}
