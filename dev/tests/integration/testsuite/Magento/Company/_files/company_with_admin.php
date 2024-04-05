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
use Magento\Framework\Encryption\Encryptor;

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
/** @var $encryptor Encryptor */
$encryptor = $objectManager->get(Encryptor::class);

// Create company admin customer
$customer = $customerFactory->create();
$customer->setWebsiteId(1)
    ->setEmail('company-admin@example.com')
    ->setFirstname('John')
    ->setLastname('Smith');
$customer = $customerRepository->save($customer, $encryptor->getHash('password', true));

// Create company with admin
/** @var CompanyInterface $company */
$company = $companyFactory->create();

$companyData = [
    'status' => CompanyInterface::STATUS_APPROVED,
    'company_name' => 'Company of John Smith',
    'legal_name' => 'Company of John Smith Legal Name',
    'company_email' => 'support@example.com',
    'street' => 'Street 1',
    'city' => 'City1',
    'country_id' => 'US',
    'region' => 'AL',
    'region_id' => 1,
    'postcode' => '22222',
    'telephone' => '2222222',
    'super_user_id' => $customer->getId(),
    'customer_group_id' => 1,
    'extension_attributes' => [
        'applicable_payment_method' => 2,
        'available_payment_methods' => ['companycredit', 'checkmo', 'paypal_express'],
        'use_config_settings' => 0,
    ],
];

$dataObjectHelper->populateWithArray($company, $companyData, CompanyInterface::class);
$companyRepository->save($company);
