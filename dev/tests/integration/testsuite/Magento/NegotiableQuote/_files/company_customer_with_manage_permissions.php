<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\RoleInterfaceFactory;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Action\Customer\Assign;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/company_customer.php');

/** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
$objectManager = Bootstrap::getObjectManager();

/** @var PermissionManagementInterface $permissionManagement */
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);
/** @var RoleInterfaceFactory $role */
$roleFactory = $objectManager->create(RoleInterfaceFactory::class);

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@companyquote.com', 'company_email');

/** @var RoleInterface $role */
$role = $roleFactory->create();
$role->setCompanyId($company->getId());
$role->setRoleName('role with Magento_NegotiableQuote::manage permission');
$role->setPermissions($permissionManagement->populatePermissions([
    "Magento_Company::index",
    "Magento_NegotiableQuote::all",
    "Magento_NegotiableQuote::view_quotes",
    "Magento_NegotiableQuote::manage"
]));
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$role = $roleRepository->save($role);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
/** @var $roleAssigner Assign */
$roleAssigner = $objectManager->get(Assign::class);

$customer = $customerRepository->get('customercompany22@example.com');
$roleAssigner->assignCustomerRole($customer, $role->getId());
