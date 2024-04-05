<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

// Create a three-level company structure without Magento_NegotiableQuote::view_quotes_sub permission

use Magento\Company\Api\Data\CompanyCustomerInterfaceFactory;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\RoleInterfaceFactory;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Action\Customer\Assign;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Structure\Tree as StructureTree;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Encryption\Encryptor;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/company_customer.php');

$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
/** @var Encryptor $encryptor */
$encryptor = $objectManager->get(Encryptor::class);
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@companyquote.com', 'company_email');

$managerCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $managerCustomer,
    [
        'firstname' => 'Manager',
        'lastname' => 'Smith',
        'email' => 'companymanager@example.com',
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
$customerRepository->save($managerCustomer, $encryptor->getHash('password', true));
$managerCustomer = $customerRepository->get('companymanager@example.com');

/** @var PermissionManagementInterface $permissionManagement */
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);
/** @var RoleInterfaceFactory $roleFactory */
$roleFactory = $objectManager->create(RoleInterfaceFactory::class);

/** @var RoleInterface $role */
$role = $roleFactory->create();
$role->setCompanyId($company->getId());
$role->setRoleName('manager role without Magento_NegotiableQuote::view_quotes_sub permission');
$role->setPermissions($permissionManagement->populatePermissions([
    "Magento_Company::index",
    "Magento_NegotiableQuote::all",
    "Magento_NegotiableQuote::view_quotes"
]));
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$role = $roleRepository->save($role);

/** @var Assign $roleAssigner */
$roleAssigner = $objectManager->get(Assign::class);
$roleAssigner->assignCustomerRole($managerCustomer, $role->getId());

$customer = $customerRepository->get('customercompany22@example.com');

$objectManager->removeSharedInstance(StructureTree::class);
/** @var StructureManager $structureManager */
$structureManager = $objectManager->create(StructureManager::class);

$managerStructure = $structureManager->getStructureByCustomerId($managerCustomer->getId());
$customerStructure = $structureManager->getStructureByCustomerId($customer->getId());
$structureManager->moveNode($customerStructure->getId(), $managerStructure->getId(), true);
