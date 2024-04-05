<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Model\ResourceModel\Company\CollectionFactory;
use Magento\CompanyCredit\Action\ReimburseFacade;
use Magento\CompanyCredit\Model\CreditLimitManagement;
use Magento\CompanyCredit\Model\HistoryFactory;
use Magento\CompanyCredit\Model\ResourceModel\History as HistoryResource;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;

/**
 * @var ObjectManager $objectManager
*/
$objectManager = Bootstrap::getObjectManager();

/**
 * @var CustomerInterfaceFactory $customerFactory
*/
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/**
 * @var CustomerRepositoryInterface $customerRepository
*/
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/**
 * @var CompanyInterfaceFactory $companyFactory
*/
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/**
 * @var CompanyRepositoryInterface $companyRepository
*/
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/**
 * @var CollectionFactory $companyCollectionFactory
*/
$companyCollectionFactory = $objectManager->get(CollectionFactory::class);

/**
 * @var DataObjectHelper $dataObjectHelper
*/
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/**
 * @var $encryptor Encryptor
*/
$encryptor = $objectManager->get(Encryptor::class);

/**
 * @var $addressRepository AddressRepositoryInterface
*/
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);

/**
 * @var $addressDataFactory AddressInterfaceFactory
*/
$addressDataFactory = $objectManager->get(AddressInterfaceFactory::class);

/**
 * @var HistoryFactory $historyFactory
*/
$historyFactory = $objectManager->get(HistoryFactory::class);

/**
 * @var RoleCollectionFactory $roleCollectionFactory
*/
$roleCollectionFactory = $objectManager->get(RoleCollectionFactory::class);

/**
 * @var CreditLimitManagement $creditLimitManagement
*/
$creditLimitManagement = $objectManager->get(CreditLimitManagement::class);

/**
 * @var SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
*/
$searchCriteriaBuilderFactory = $objectManager->get(SearchCriteriaBuilderFactory::class);

$roleCollection = $roleCollectionFactory->create();
$roleCollection->addFieldToFilter('user_type', ['eq' => UserContextInterface::USER_TYPE_ADMIN]);
$role = $roleCollection->getFirstItem();

/*
 * Create the first merchant user
 */
$userName1 = 'salesRep1';
$salesRep1 = $objectManager->create(User::class);
$salesRep1->setRoleId($role->getId())
    ->setEmail('salesrep1@example.com')
    ->setFirstName('firstname')
    ->setLastName('lastname')
    ->setUserName($userName1)
    ->setPassword('123123qprwr')
    ->setIsActive(1);
$salesRep1 = $salesRep1->save();

/*
 * Create the second merchant user
 */
$userName2 = 'salesRep2';
$salesRep2 = $objectManager->create(User::class);
$salesRep2->setRoleId($role->getId())
    ->setEmail('salesrep2@example.com')
    ->setFirstName('newname')
    ->setLastName('secondname')
    ->setUserName($userName2)
    ->setPassword('123123qsfghjk')
    ->setIsActive(1);
$salesRep2 = $salesRep2->save();

/*
 * Create the first customer to serve as the admin for the company.
 */
$adminCustomer1 = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $adminCustomer1,
    [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john.doe1@example.com',
        'website_id' => 1,
    ],
    CustomerInterface::class
);
$customerRepository->save($adminCustomer1, $encryptor->getHash('Tyfghohigufk', true));

/*
 * Create a customer address for the first customer.
 */
$adminCustomerAddress1 = $addressDataFactory->create();
$adminCustomerAddress1->setFirstname('John')
    ->setLastname('Doe')
    ->setCountryId('US')
    ->setRegionId('4')
    ->setCity('City Name')
    ->setPostcode('7777')
    ->setCustomerId($customerRepository->get($adminCustomer1->getEmail())->getId())
    ->setStreet(['Line 1 Street', 'Line 2'])
    ->setTelephone('123123123');

$addressRepository->save($adminCustomerAddress1);
$adminCustomer1 = $customerRepository->get('john.doe1@example.com');

/*
 * Create the second customer to serve as the admin for the company.
 */
$adminCustomer2 = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $adminCustomer2,
    [
        'firstname' => 'Sam',
        'lastname' => 'Smith',
        'email' => 'sam.smith@example.com',
        'website_id' => 1,
    ],
    CustomerInterface::class
);
$customerRepository->save($adminCustomer2, $encryptor->getHash('yeibkxbOe3r', true));

/*
 * Create a customer address for the second customer.
 */
$adminCustomerAddress2 = $addressDataFactory->create();
$adminCustomerAddress2->setFirstname('Sam')
    ->setLastname('Smith')
    ->setCountryId('US')
    ->setRegionId('4')
    ->setCity('City Name')
    ->setPostcode('8888')
    ->setCustomerId($customerRepository->get($adminCustomer2->getEmail())->getId())
    ->setStreet(['Line 1 Street', 'Line 2'])
    ->setTelephone('123123128');

$addressRepository->save($adminCustomerAddress2);
$adminCustomer2 = $customerRepository->get('sam.smith@example.com');

/*
 * Create the first company with the admin and sales rep created above.
 */
$company1 = $companyFactory->create();

/**
 * Clean up
 */
$companyCollection = $companyCollectionFactory->create();
foreach ($companyCollection->getItems() as $item) {
    $item->delete();
}

$dataObjectHelper->populateWithArray(
    $company1,
    [
        'company_name' => 'Roga i koputa',
        'status' => CompanyInterface::STATUS_APPROVED,
        'company_email' => 'rogaikoputa.company@example.com',
        'comment' => 'Comment',
        'super_user_id' => $adminCustomer1->getId(),
        'sales_representative_id' => $salesRep1->getId(),
        'customer_group_id' => 1,
        'country_id' => 'US',
        'region_id' => 1,
        'city' => 'City',
        'street' => '1234 Street',
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
$companyRepository->save($company1);

/*
 * Create the second company with the admin and sales rep created above.
 */
$company2 = $companyFactory->create();

$dataObjectHelper->populateWithArray(
    $company2,
    [
        'company_name' => 'Galera',
        'status' => CompanyInterface::STATUS_APPROVED,
        'company_email' => 'galera.company@example.com',
        'comment' => 'Comment',
        'super_user_id' => $adminCustomer2->getId(),
        'sales_representative_id' => $salesRep2->getId(),
        'customer_group_id' => 1,
        'country_id' => 'US',
        'region_id' => 1,
        'city' => 'City',
        'street' => '12345 Street',
        'postcode' => 'Postcode',
        'telephone' => '6666666666',
        'extension_attributes' => [
            'applicable_payment_method' => 2,
            'available_payment_methods' => ['companycredit', 'checkmo'],
            'use_config_settings' => 0,
        ],
    ],
    CompanyInterface::class
);
$companyRepository->save($company2);

/*
 * Load the first company with its companyId populated
 */
$searchCriteriaBuilder1 = $searchCriteriaBuilderFactory->create();
$searchCriteriaBuilder1->addFilter('company_name', 'Roga i koputa');
$searchCriteria1 = $searchCriteriaBuilder1->create();
$results1 = $companyRepository->getList($searchCriteria1)->getItems();
$company1 = reset($results1);

$creditLimit1 = $creditLimitManagement->getCreditByCompanyId($company1->getId());
$creditLimit1->setCreditLimit(100);
$creditLimit1->setBalance(-50);
$creditLimit1->save();

$reimburseFacade1 = $objectManager->create(ReimburseFacade::class);
$companyCredit1 = $reimburseFacade1->execute(
    $company1->getId(),
    20,
    'test comment',
    '12345'
);

$history1 = $historyFactory->create();
$historyResource1 = $objectManager->create(HistoryResource::class);
$historyResource1->load($history1, $companyCredit1->getId(), 'company_credit_id');
$history1->setUserId($salesRep1->getId());
$history1->setUserType(UserContextInterface::USER_TYPE_ADMIN);
$historyResource1->save($history1);

/*
 * Load the second company with its companyId populated
 */
$searchCriteriaBuilder2 = $searchCriteriaBuilderFactory->create();
$searchCriteriaBuilder2->addFilter('company_name', 'Galera');
$searchCriteria2 = $searchCriteriaBuilder2->create();
$results2 = $companyRepository->getList($searchCriteria2)->getItems();
$company2 = reset($results2);

$creditLimit2 = $creditLimitManagement->getCreditByCompanyId($company2->getId());
$creditLimit2->setCreditLimit(1000);
$creditLimit2->setBalance(1000);
$creditLimit2->save();

$reimburseFacade2 = $objectManager->create(ReimburseFacade::class);
$companyCredit2 = $reimburseFacade2->execute(
    $company2->getId(),
    500,
    'test comment 2',
    '12345'
);

$history2 = $historyFactory->create();
$historyResource2 = $objectManager->create(HistoryResource::class);
$historyResource2->load($history2, $companyCredit2->getId(), 'company_credit_id');
$history2->setUserId($salesRep2->getId());
$history2->setUserType(UserContextInterface::USER_TYPE_ADMIN);
$historyResource2->save($history2);
