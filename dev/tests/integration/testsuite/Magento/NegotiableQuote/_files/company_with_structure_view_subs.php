<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

// Create a three-level company structure with a manager with Magento_NegotiableQuote::view_quotes_sub permission

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\RoleInterfaceFactory;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Action\Customer\Assign;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/company_with_structure_no_view_subs.php');

$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
$managerCustomer = $customerRepository->get('companymanager@example.com');

$companyId = $managerCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

/** @var PermissionManagementInterface $permissionManagement */
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);
/** @var RoleInterfaceFactory $roleFactory */
$roleFactory = $objectManager->create(RoleInterfaceFactory::class);

/** @var RoleInterface $role */
$role = $roleFactory->create();
$role->setCompanyId($companyId);
$role->setRoleName('manager role with Magento_NegotiableQuote::view_quotes_sub permission');
$role->setPermissions($permissionManagement->populatePermissions([
    "Magento_Company::index",
    "Magento_NegotiableQuote::all",
    "Magento_NegotiableQuote::view_quotes",
    "Magento_NegotiableQuote::view_quotes_sub"
]));
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$role = $roleRepository->save($role);

/** @var Assign $roleAssigner */
$roleAssigner = $objectManager->get(Assign::class);
$roleAssigner->assignCustomerRole($managerCustomer, $role->getId());
