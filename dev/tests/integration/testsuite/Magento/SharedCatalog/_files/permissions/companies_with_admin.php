<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SharedCatalog\Api\CompanyManagementInterface;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CustomerFactory $customerFactory */
$customerFactory = $objectManager->create(CustomerFactory::class);
/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->create(CompanyInterfaceFactory::class);
/** @var CompanyManagementInterface $companyManagement */
$companyManagement = $objectManager->create(CompanyManagementInterface::class);
/** @var SharedCatalogRepositoryInterface $sharedCatalogRepository */
$sharedCatalogRepository = $objectManager->create(SharedCatalogRepositoryInterface::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = Bootstrap::getObjectManager()->get(DataObjectHelper::class);

for ($i = 0; $i < 3; $i++) {
    //Create customer
    $customer = $customerFactory->create();
    /** @var Customer $customer */
    $customer->setWebsiteId(1)
        ->setId($i + 1)
        ->setEmail('admin@' . $i . 'company.com')
        ->setPassword('password')
        ->setGroupId(1)
        ->setStoreId(1)
        ->setIsActive(1)
        ->setPrefix('Mr.')
        ->setFirstname('John')
        ->setLastname('Smith ' . $i)
        ->setDefaultBilling(1)
        ->setDefaultShipping(1)
        ->setTaxvat('12')
        ->setGender(0);
    $customer->isObjectNew(true);
    $customer->save();

    // Create company with admin
    $company = $companyFactory->create();
    $companyData = [
        'status' => CompanyInterface::STATUS_APPROVED,
        'company_name' => 'Company ' . $i . ' of John Smith' . $i,
        'legal_name' => 'Company ' . $i . ' of John Smith Legal Name',
        'company_email' => 'support@' . $i . 'company.com',
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
            'available_payment_methods' => ['companycredit', 'checkmo'],
            'use_config_settings' => 0,
        ],
    ];
    $dataObjectHelper->populateWithArray($company, $companyData, CompanyInterface::class);
    $companyRepository->save($company);

    //Get shared catalog
    $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
    $searchCriteria = $searchCriteriaBuilder->addFilter('name', 'Company ' . $i . ' shared catalog')->create();
    $items = $sharedCatalogRepository->getList($searchCriteria)->getItems();
    /** @var SharedCatalogInterface $sharedCatalog */
    $sharedCatalog = reset($items);

    //Assign company to the matched shared catalog
    $companyManagement->assignCompanies($sharedCatalog->getId(), [$company]);
}
