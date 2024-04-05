<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->create(CustomerInterfaceFactory::class);
/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->create(CompanyInterfaceFactory::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = Bootstrap::getObjectManager()->get(DataObjectHelper::class);

// Create company one admin customer
$customerOne = $customerFactory->create();
$customerOne->setWebsiteId(1)
    ->setEmail('company-adminone@example.com')
    ->setFirstname('John 1')
    ->setLastname('Smith 1');
$customerOne = $customerRepository->save($customerOne, 'password');

// Create company one with admin
/** @var CompanyInterface $companyOne */
$companyOne = $companyFactory->create();

$companyOneData = [
    'status' => CompanyInterface::STATUS_APPROVED,
    'company_name' => 'Company of John Smith 1',
    'legal_name' => 'Company of John Smith Legal Name 1',
    'company_email' => 'supportone@example.com',
    'street' => 'Street 1',
    'city' => 'City 1',
    'country_id' => 'US',
    'region' => 'AL',
    'region_id' => 1,
    'postcode' => '22222',
    'telephone' => '2222222',
    'super_user_id' => $customerOne->getId(),
    'customer_group_id' => 1,
    'extension_attributes' => [
        'applicable_payment_method' => 2,
        'available_payment_methods' => ['companycredit', 'checkmo'],
        'use_config_settings' => 0,
    ],
];

$dataObjectHelper->populateWithArray($companyOne, $companyOneData, CompanyInterface::class);
$companyRepository->save($companyOne);

// Create company two admin customer
$customerTwo = $customerFactory->create();
$customerTwo->setWebsiteId(1)
    ->setEmail('company-admintwo@example.com')
    ->setFirstname('John 2')
    ->setLastname('Smith 2');
$customerTwo = $customerRepository->save($customerTwo, 'password');

// Create company two with admin
/** @var CompanyInterface $company */
$companyTwo = $companyFactory->create();

$companyTwoData = [
    'status' => CompanyInterface::STATUS_APPROVED,
    'company_name' => 'Company of John Smith 2',
    'legal_name' => 'Company of John Smith Legal Name 2',
    'company_email' => 'supporttwo@example.com',
    'street' => 'Street 2',
    'city' => 'City 2',
    'country_id' => 'US',
    'region' => 'AL',
    'region_id' => 1,
    'postcode' => '22222',
    'telephone' => '2222222',
    'super_user_id' => $customerTwo->getId(),
    'customer_group_id' => 1,
    'extension_attributes' => [
        'applicable_payment_method' => 2,
        'available_payment_methods' => ['companycredit', 'checkmo'],
        'use_config_settings' => 0,
    ],
];

$dataObjectHelper->populateWithArray($companyTwo, $companyTwoData, CompanyInterface::class);
$companyRepository->save($companyTwo);
