<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Abstract base Controller test class for adding purchase order items to the shopping cart.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class AddItemAbstract extends PurchaseOrderAbstract
{
    protected const URI = 'purchaseorder/purchaseorder/additem';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Enable company functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);

        // Grant the "Default User" role with permission to the purchase order grouping resource.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
            PermissionInterface::DENY_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::DENY_PERMISSION
        );
    }

    /**
     * Enable/Disable configuration for the website scope.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    protected function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Enable/Disable purchase order functionality on a per company basis.
     *
     * @param string $companyName
     * @param bool $isEnabled
     * @throws LocalizedException
     */
    protected function setCompanyPurchaseOrderConfig(string $companyName, bool $isEnabled)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $companyConfigRepository = $objectManager->get(CompanyConfigRepositoryInterface::class);
        $searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        $searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $searchCriteriaBuilder->create();
        $results = $companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        $companyConfig = $companyConfigRepository->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $companyConfigRepository->save($companyConfig);
    }

    /**
     * Sets the permission value for the specified company role.
     *
     * @param string $companyName
     * @param string $roleName
     * @param string $resourceId
     * @param string $permissionValue
     */
    protected function setCompanyRolePermission(
        string $companyName,
        string $roleName,
        string $resourceId,
        string $permissionValue
    ) {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        // Get the company
        $searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $searchCriteriaBuilder->create();
        $results = $companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        // Get the company role
        $searchCriteriaBuilder->addFilter('company_id', $company->getId());
        $searchCriteriaBuilder->addFilter('role_name', $roleName);
        $searchCriteria = $searchCriteriaBuilder->create();
        $results = $roleRepository->getList($searchCriteria)->getItems();

        /** @var RoleInterface $role */
        $role = reset($results);

        // For that role, find the specified permission and set it to the desired value
        /** @var PermissionInterface $permission */
        foreach ($role->getPermissions() as $permission) {
            if ($permission->getResourceId() === $resourceId) {
                $permission->setPermission($permissionValue);
                break;
            }
        }

        $roleRepository->save($role);
    }
}
