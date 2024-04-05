<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Api\Data\TeamInterfaceFactory;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Customer;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var StructureManager $structureManager */
$structureManager = $objectManager->get(StructureManager::class);

/** @var TeamInterfaceFactory $teamFactory */
$teamFactory = $objectManager->get(TeamInterfaceFactory::class);

/** @var TeamRepositoryInterface $teamRepository */
$teamRepository = $objectManager->get(TeamRepositoryInterface::class);

/** @var EncryptorInterface $encryptor */
$encryptor = $objectManager->get(EncryptorInterface::class);

/*
 * Create a merchant user to serve as the sales rep for the company.
 */
/** @var User $user */
$salesRep = $objectManager->create(User::class);
$salesRep->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

/*
 * Create a customer to serve as the admin for the company.
 */
/** @var CustomerInterface $adminCustomer */
$adminCustomer = $customerFactory->create();
$adminCustomer->setWebsiteId(1)
    ->setEmail('customer@example.com')
    ->setGroupId(1)
    ->setStoreId(1)
    ->setPrefix('Mr.')
    ->setFirstname('John')
    ->setMiddlename('A')
    ->setLastname('Smith')
    ->setSuffix('Esq.')
    ->setDefaultBilling(1)
    ->setDefaultShipping(1)
    ->setTaxvat('12')
    ->setGender(0);
$adminCustomer = $customerRepository->save($adminCustomer, $encryptor->hash('password'));
/*
 * Create a company with the admin and sales rep created above.
 */
/** @var CompanyInterface $company */
$company = $companyFactory->create();
$dataObjectHelper->populateWithArray(
    $company,
    [
        'company_name' => 'Magento2',
        'status' => CompanyInterface::STATUS_APPROVED,
        'company_email' => 'company2@example.com',
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

/**
 * Load the company we just created with its companyId populated
 */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteriaBuilder->addFilter('company_name', 'Magento2');
$searchCriteria = $searchCriteriaBuilder->create();
$results = $companyRepository->getList($searchCriteria)->getItems();
$company = reset($results);

/**
 *
 * Create teams
 */
/** @var \Magento\Company\Model\Structure $customerStructure */
$customerStructure = $structureManager->getStructureByCustomerId($adminCustomer->getId());
$levelZeroTargetId = (int)$customerStructure->getStructureId();

/** @var TeamInterface $teamA */
$teamA = $teamFactory->create();
$dataObjectHelper->populateWithArray(
    $teamA,
    [
        'name' => 'Team A (level 1)',
        'description' => 'Level 1 Team'
    ],
    TeamInterface::class
);

/**
 * Create team
 */
$teamRepository->create($teamA, $company->getId());

/**
 * Set structure for Team A
 */
$teamAStructure = $structureManager->getStructureByTeamId($teamA->getId());
$structureManager->moveNode($teamAStructure->getId(), $levelZeroTargetId);

/** @var TeamInterface $teamB */
$teamB = $teamFactory->create();
$dataObjectHelper->populateWithArray(
    $teamB,
    [
        'name' => 'Team B (level 1)',
        'description' => 'Level 1 Team'
    ],
    TeamInterface::class
);

/**
 * Create team
 */
$teamRepository->create($teamB, $company->getId());

/**
 * Set structure for Team B
 */
$teamBStructure = $structureManager->getStructureByTeamId($teamB->getId());
$structureManager->moveNode($teamBStructure->getId(), $levelZeroTargetId);

/*
 * Create a customer one level below the company admin in the company hierarchy.
 */
$levelOneCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $levelOneCustomer,
    [
        'firstname' => 'Veronica',
        'lastname' => 'Tailor',
        'email' => 'veronica.tailor@example.com',
        'website_id' => 1,
        'extension_attributes' => [
            'company_attributes' => [
                'company_id' => $company->getId(),
                'status' => 1,
                'job_title' => 'Sales Rep'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($levelOneCustomer, $encryptor->hash('password'));

/*
 * Create another customer one level below the company admin in the company hierarchy.
 */
$levelTwoCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $levelTwoCustomer,
    [
        'firstname' => 'Alex',
        'lastname' => 'Tailor',
        'email' => 'alex.tailor@example.com',
        'website_id' => 1,
        'extension_attributes' => [
            'company_attributes' => [
                'company_id' => $company->getId(),
                'status' => 1,
                'job_title' => 'Sales Rep'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($levelTwoCustomer, $encryptor->hash('password'));
