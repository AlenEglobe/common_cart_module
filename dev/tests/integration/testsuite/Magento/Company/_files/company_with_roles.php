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
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Model\User;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/** @var CompanyRepositoryInterface $companyRepository */
$companyRepository = $objectManager->get(CompanyRepositoryInterface::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var RoleInterfaceFactory $roleFactory */
$roleFactory = $objectManager->get(RoleInterfaceFactory::class);

/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);

/** @var PermissionManagementInterface $permissionManagement */
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);

/** @var $configWriter WriterInterface */
$configWriter = $objectManager->get(WriterInterface::class);

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

/*
 * Create a customer to serve as the admin for the company.
 */
/** @var CustomerInterface $adminCustomer */
$adminCustomer = $customer = $objectManager->create(Customer::class);
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->get(CustomerRegistry::class);
$adminCustomer->setWebsiteId(1)
    ->setId(1)
    ->setEmail('customer@example.com')
    ->setPassword('password')
    ->setGroupId(1)
    ->setStoreId(1)
    ->setIsActive(1)
    ->setPrefix('Mr.')
    ->setFirstname('John')
    ->setMiddlename('A')
    ->setLastname('Smith')
    ->setSuffix('Esq.')
    ->setDefaultBilling(1)
    ->setDefaultShipping(1)
    ->setTaxvat('12')
    ->setGender(0);

$adminCustomer->isObjectNew(true);
$adminCustomer->save();
$customerRegistry->remove($adminCustomer->getId());
/** @var \Magento\JwtUserToken\Api\RevokedRepositoryInterface $revokedRepo */
$revokedRepo = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->get(\Magento\JwtUserToken\Api\RevokedRepositoryInterface::class);
$revokedRepo->saveRevoked(
    new \Magento\JwtUserToken\Api\Data\Revoked(
        \Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER,
        (int) $adminCustomer->getId(),
        time() - 3600 * 24
    )
);

/*
 * Create a company with the admin and sales rep created above.
 */
/** @var CompanyInterface $company */
$company = $companyFactory->create();
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
        'telephone' => '5555555555'
    ],
    CompanyInterface::class
);
$companyRepository->save($company);

/**
 * Load the company we just created with its companyId populated
 */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteriaBuilder->addFilter('company_name', 'Magento');
$searchCriteria = $searchCriteriaBuilder->create();
$results = $companyRepository->getList($searchCriteria)->getItems();
$company = reset($results);

/**
 *
 * Create roles
 */

/**
 * Create role
 */

/** @var RoleInterface $roleA */
$roleA = $roleFactory->create();
$roleA->setRoleName('Role A');
$roleA->setPermissions($permissionManagement->populatePermissions(
    ["Magento_Company::view", "Magento_Company::view_account"]
));
$roleA->setCompanyId($company->getId());
$roleRepository->save($roleA);

/**
 * Create role
 */

/** @var RoleInterface $roleB */
$roleB = $roleFactory->create();
$roleB->setRoleName('Role B');
$roleB->setPermissions($permissionManagement->populatePermissions(["Magento_Company::view"]));
$roleB->setCompanyId($company->getId());
$roleRepository->save($roleB);

/**
 * Create role
 */

/** @var RoleInterface $roleC */
$roleC = $roleFactory->create();
$roleC->setRoleName('Role C');
$roleC->setPermissions($permissionManagement->populatePermissions(["Magento_Company::view"]));
$roleC->setCompanyId($company->getId());
$roleRepository->save($roleC);
