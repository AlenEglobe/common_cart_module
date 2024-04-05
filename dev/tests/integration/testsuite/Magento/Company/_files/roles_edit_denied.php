<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Api\RoleManagementInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Permission\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
$roleManagement = $objectManager->get(RoleManagementInterface::class);
$permissionCollection = $objectManager->get(CollectionFactory::class);
$permissionManagement = $objectManager->get(PermissionManagementInterface::class);
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

$deniedPermissions = [
    'Magento_Company::roles_edit',
];

$customer = $customerRepository->get('veronica.costello@example.com');
$defaultRole = $roleManagement->getCompanyDefaultRole(
    $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
);

$rolePermissions = $permissionCollection
    ->create()
    ->addFieldToFilter('role_id', ['eq' => $defaultRole->getId()])
    ->getColumnValues('resource_id');

// Disable access
foreach ($deniedPermissions as $permission) {
    if (in_array($permission, $rolePermissions, true)) {
        $key = array_search($permission, $rolePermissions, true);
        unset($rolePermissions[$key]);
    }
}

$defaultRole->setPermissions($permissionManagement->populatePermissions($rolePermissions));
$roleRepository->save($defaultRole);
