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
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

// Create merchant users to serve as the sales reps for the companies.
$salesRepUsernames = [
    'Abby_Admin',
    'Carly_Admin',
    'Bobby_Admin',
];

foreach ($salesRepUsernames as $key => $salesRepUsername) {
    /** @var User $salesRep */
    $salesRepFirstName = str_replace('_Admin', '', $salesRepUsername);
    $salesRepEmail = $salesRepUsername . '@example.com';

    $salesRep = $objectManager->create(User::class);
    $salesRep->setUserName($salesRepUsername);
    $salesRep->setFirstName($salesRepFirstName);
    $salesRep->setLastName('Admin');
    $salesRep->setEmail($salesRepEmail);
    $salesRep->setPassword('password123');

    $salesRep->save();

    // Create a customer to serve as the admin for the company
    /** @var CustomerInterface $adminCustomer */
    $companyAdminEmail = "Company_Admin_Under_$salesRepFirstName@example.com";
    $adminCustomer = $customerFactory->create();
    $dataObjectHelper->populateWithArray(
        $adminCustomer,
        [
            'firstname' => 'Company Admin under',
            'lastname' => $salesRepFirstName,
            'email' => $companyAdminEmail,
            'website_id' => 1,
        ],
        CustomerInterface::class
    );
    $customerRepository->save($adminCustomer, 'password123');
    $adminCustomer = $customerRepository->get($companyAdminEmail);

    // Create a company with the admin and sales rep created above
    /** @var CompanyInterface $company */
    $company = $companyFactory->create();
    $dataObjectHelper->populateWithArray(
        $company,
        [
            'company_name' => "Company Under $salesRepFirstName",
            'status' => CompanyInterface::STATUS_APPROVED,
            'company_email' => $adminCustomer->getEmail(),
            'comment' => 'Comment',
            'super_user_id' => $adminCustomer->getId(),
            'sales_representative_id' => $salesRep->getId(),
            'customer_group_id' => 1,
            'country_id' => 'US',
            'region_id' => 1,
            'city' => 'City',
            'street' => '123 Street',
            'postcode' => 'Postcode',
            'telephone' => '5555555555'
        ],
        CompanyInterface::class
    );

    $companyRepository->save($company);
}
