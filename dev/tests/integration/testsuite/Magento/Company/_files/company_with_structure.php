<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\TeamInterfaceFactory;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\ResourceModel\Company\Collection;
use Magento\Company\Model\ResourceModel\Structure\Tree as StructureTree;
use Magento\CompanyCredit\Model\CreditLimit;
use Magento\CompanyCredit\Model\CreditLimitManagement;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Encryption\Encryptor;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;
use Magento\CompanyCredit\Action\ReimburseFacade;
use Magento\CompanyCredit\Model\HistoryFactory;
use Magento\CompanyCredit\Model\ResourceModel\History as HistoryResource;
use Magento\Framework\Registry;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/** @var Collection $companyCollection */
$companyCollection = $objectManager->get(Collection::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var $encryptor Encryptor */
$encryptor = $objectManager->get(Encryptor::class);

/** @var $addressRepository AddressRepositoryInterface */
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);

/** @var $addressDataFactory AddressInterfaceFactory */
$addressDataFactory = $objectManager->get(AddressInterfaceFactory::class);

/** @var $teamRepository TeamRepositoryInterface */
$teamRepository = $objectManager->get(TeamRepositoryInterface::class);

/** @var $teamDataFactory TeamInterfaceFactory */
$teamDataFactory = $objectManager->get(TeamInterfaceFactory::class);

/** @var ReimburseFacade $reimburseFacade */
$reimburseFacade = $objectManager->get(ReimburseFacade::class);

/** @var HistoryFactory $historyFactory */
$historyFactory = $objectManager->get(HistoryFactory::class);

/** @var HistoryResource $historyResource */
$historyResource = $objectManager->get(HistoryResource::class);

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
$dataObjectHelper->populateWithArray(
    $adminCustomer,
    [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john.doe@example.com',
        'website_id' => 1,
    ],
    CustomerInterface::class
);
$customerRepository->save($adminCustomer, $encryptor->getHash('password', true));

/*
 * Create a customer address.
 */
$adminCustomerAddress = $addressDataFactory->create();
$adminCustomerAddress->setFirstname('John')
    ->setLastname('Doe')
    ->setCountryId('US')
    ->setRegionId('4')
    ->setCity('City Name')
    ->setPostcode('7777')
    ->setCustomerId($customerRepository->get($adminCustomer->getEmail())->getId())
    ->setStreet(['Line 1 Street', 'Line 2'])
    ->setTelephone('123123123');

$addressRepository->save($adminCustomerAddress);
$adminCustomer = $customerRepository->get('john.doe@example.com');

/*
 * Create a company with the admin and sales rep created above.
 */
$company = $companyFactory->create();

/**
 * Clean up
 */
foreach ($companyCollection->getItems() as $item) {
    $item->delete();
}

$dataObjectHelper->populateWithArray(
    $company,
    [
        'company_name' => 'Magento',
        'status' => CompanyInterface::STATUS_APPROVED,
        'company_email' => 'company@example.com',
        'comment' => 'Comment',
        'super_user_id' => $adminCustomer->getId(),
        'sales_representative_id' => $salesRep->getId(),
        'customer_group_id' => 1,
        'country_id' => 'US',
        'region_id' => 1,
        'city' => 'City',
        'street' => '123 Street',
        'postcode' => 'Postcode',
        'telephone' => '5555555555',
        'extension_attributes' => [
            'applicable_payment_method' => 2,
            'available_payment_methods' => ['companycredit', 'checkmo'],
            'use_config_settings' => 0,
        ],
    ],
    CompanyInterface::class
);
$companyRepository->save($company);

/*
 * Create a company team.
 */
$team = $teamDataFactory
    ->create()
    ->setName('Test team')
    ->setDescription('Test team description');

$teamRepository->create($team, $company->getId());

// Load the company we just created with its companyId populated
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteriaBuilder->addFilter('company_name', 'Magento');
$searchCriteria = $searchCriteriaBuilder->create();
$results = $companyRepository->getList($searchCriteria)->getItems();
$company = reset($results);

/*
 * Create a customer one level below the company admin in the company hierarchy.
 */
$levelOneCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $levelOneCustomer,
    [
        'firstname' => 'Veronica',
        'lastname' => 'Costello',
        'email' => 'veronica.costello@example.com',
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
$customerRepository->save($levelOneCustomer, $encryptor->getHash('password', true));
$levelOneCustomer = $customerRepository->get('veronica.costello@example.com');

/*
 * Create a customer two levels below the company admin in the company hierarchy.
 */
$levelTwoCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $levelTwoCustomer,
    [
        'firstname' => 'Alex',
        'lastname' => 'Smith',
        'email' => 'alex.smith@example.com',
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
$customerRepository->save($levelTwoCustomer, $encryptor->getHash('password', true));
$levelTwoCustomer = $customerRepository->get('alex.smith@example.com');

/*
 * Move the levelTwoCustomer so that they are a subordinate of the levelOneCustomer.
 */
/** @var StructureManager $structureManager */
$objectManager->removeSharedInstance(StructureTree::class);
$structureManager = $objectManager->create(StructureManager::class);

$levelOneCustomerStructure = $structureManager->getStructureByCustomerId($levelOneCustomer->getId());
$levelTwoCustomerStructure = $structureManager->getStructureByCustomerId($levelTwoCustomer->getId());
$structureManager->moveNode($levelTwoCustomerStructure->getId(), $levelOneCustomerStructure->getId(), true);

/** @var $creditLimit CreditLimit */
$creditLimitManagement = $objectManager
    ->get(CreditLimitManagement::class);
$creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
$creditLimit->setCreditLimit(100);
$creditLimit->setBalance(-50);
$creditLimit->save();

$companyCredit = $reimburseFacade->execute(
    $company->getId(),
    20,
    'test comment',
    '12345'
);

$history = $historyFactory->create();

$historyResource->load($history, $companyCredit->getId(), 'company_credit_id');

$history->setUserId($salesRep->getId());
$history->setUserType(UserContextInterface::USER_TYPE_ADMIN);

$historyResource->save($history);

$registry->unregister('company');
$registry->register('company', $company);

$registry->unregister('levelOneCustomer');
$registry->register('levelOneCustomer', $levelOneCustomer);

$registry->unregister('levelTwoCustomer');
$registry->register('levelTwoCustomer', $levelTwoCustomer);
