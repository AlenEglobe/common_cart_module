<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\RoleInterfaceFactory;
use Magento\Company\Api\Data\TeamInterfaceFactory;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\ResourceModel\Structure\Tree as StructureTree;
use Magento\Company\Model\Structure;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\Action\Customer\Assign;

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

/** @var $configWriter WriterInterface */
$configWriter = $objectManager->get(WriterInterface::class);

/** @var $permissionManagement PermissionManagementInterface */
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);

$path = 'btob/website_configuration/company_active';
$configWriter->save($path, 1, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);

$cacheTypeList = $objectManager->get(TypeListInterface::class);
$cacheTypeList->cleanType('config');

/*
 * Create a merchant user to serve as the sales rep for the company.
 */
/** @var User $user */
$salesRep = $objectManager->create(User::class);
$salesRep->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);

/** @var $roleAssigner Assign */
$roleAssigner = $objectManager->get(Assign::class);

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

// Load the company we just created with its companyId populated
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteriaBuilder->addFilter('company_name', 'Magento2');
$searchCriteria = $searchCriteriaBuilder->create();
$results = $companyRepository->getList($searchCriteria)->getItems();
$company = reset($results);

/** @var RoleInterfaceFactory $role */
$roleFactory = Bootstrap::getObjectManager()->create(RoleInterfaceFactory::class);

/** @var RoleInterface $role */
$role = $roleFactory->create();
$role->setCompanyId($company->getId());
$role->setRoleName('custom company role');
$role->setPermissions($permissionManagement->populatePermissions(["Magento_Company::user_management"]));
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = Bootstrap::getObjectManager()->get(RoleRepositoryInterface::class);
$firstRole = $roleRepository->save($role);

/** @var RoleInterface $role */
$role = $roleFactory->create();
$role->setCompanyId($company->getId());
$role->setRoleName('new custom company role');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = Bootstrap::getObjectManager()->get(RoleRepositoryInterface::class);
$roleRepository->save($role);

/** @var StructureManager $structureManager */
$objectManager->removeSharedInstance(StructureTree::class);
$structureManager = $objectManager->create(StructureManager::class);
/** @var Structure $customerStructure */
$customerStructure = $structureManager->getStructureByCustomerId($adminCustomer->getId());
$levelZeroTargetId = (int)$customerStructure->getStructureId();

/*
 * Create a company team.
 */
$team = $teamDataFactory
    ->create()
    ->setName('Test team')
    ->setDescription('Test team description');

$teamRepository->create($team, $company->getId());

/**
 * Set structure
 */
$teamStructure = $structureManager->getStructureByTeamId($team->getId());
$structureManager->moveNode($teamStructure->getId(), $levelZeroTargetId);

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
                'job_title' => 'Sales Rep',
                'telephone' => '549583943048'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($levelOneCustomer, $encryptor->getHash('password', true));
$levelOneCustomer = $customerRepository->get('veronica.costello@example.com');
$roleAssigner->assignCustomerRole($levelOneCustomer, $firstRole->getId());

/*
 * Create another customer.
 */
$customer2 = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $customer2,
    [
        'firstname' => 'Firstname',
        'lastname' => 'Lastname',
        'email' => 'test@example.com',
        'website_id' => 1
    ],
    CustomerInterface::class
);
$customerRepository->save($customer2, $encryptor->getHash('password', true));
$customer2 = $customerRepository->get('test@example.com');
