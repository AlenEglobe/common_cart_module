<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model\Permissions;

use Magento\CatalogPermissions\Model\Permission as CatalogPermission;
use Magento\CatalogPermissions\Model\ResourceModel\Permission\CollectionFactory as CatalogPermissionsCollectionFactory;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\SharedCatalog\Model\CatalogPermissionManagement;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\SharedCatalog\Model\CustomerGroupManagement;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Store/_files/website.php
 * @magentoDataFixture Magento/Catalog/_files/category.php
 * @magentoDataFixture Magento/SharedCatalog/_files/shared_category_product.php
 * @magentoConfigFixture btob/website_configuration/company_active 1
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
class SynchronizerTest extends TestCase
{
    /**
     * @var CatalogPermissionsCollectionFactory
     */
    private $catalogPermissionsCollectionFactory;

    /**
     * @var CatalogPermissionManagement
     */
    private $catalogPermissionManagement;

    /**
     * @var CustomerGroupManagement
     */
    private $customerGroupManagement;

    /**
     * @var SharedCatalogInterface
     */
    private $publicCatalog;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var array
     */
    private $publicGroupsId;

    /**
     * @var Synchronizer
     */
    private $synchronizer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->catalogPermissionsCollectionFactory = $objectManager->get(CatalogPermissionsCollectionFactory::class);
        $this->catalogPermissionManagement = $objectManager->get(CatalogPermissionManagement::class);
        $this->customerGroupManagement = $objectManager->get(CustomerGroupManagement::class);
        $sharedCatalogManagement = $objectManager->get(SharedCatalogManagementInterface::class);
        $this->publicCatalog = $sharedCatalogManagement->getPublicCatalog();
        $this->websiteRepository = $objectManager->get(WebsiteRepositoryInterface::class);
        $this->publicGroupsId = [
            GroupInterface::NOT_LOGGED_IN_ID,
            (int) $this->publicCatalog->getCustomerGroupId(),
        ];

        $this->synchronizer = $objectManager->create(Synchronizer::class);
    }

    /**
     * @dataProvider scopesDataProvider
     * @param string|null $scopeCode
     * @return void
     */
    public function testUpdateCategoryPermissions($scopeCode)
    {
        $scope = $scopeCode
            ? ScopeInterface::SCOPE_WEBSITES
            : ReinitableConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = $scopeCode
            ? (int) $this->websiteRepository->get($scopeCode)->getId()
            : null;
        $this->enableSharedCatalog($scope, $scopeId);

        foreach ([10, 333] as $categoryId) {
            $this->synchronizer->updateCategoryPermissions(
                $categoryId,
                $this->customerGroupManagement->getSharedCatalogGroupIds()
            );
        }

        $catalogPermissionsCollection = $this->catalogPermissionsCollectionFactory->create();
        $catalogPermissionsCollection->addFieldToFilter('website_id', ['neq' => $scopeId]);
        $permissions = $catalogPermissionsCollection->getItems();
        $this->assertEmpty($permissions);

        $groupIdsNotInSharedCatalogs = $this->customerGroupManagement->getGroupIdsNotInSharedCatalogs();
        $this->assertNotEmpty($groupIdsNotInSharedCatalogs);
        foreach ($groupIdsNotInSharedCatalogs as $groupId) {
            foreach ([10, 333] as $categoryId) {
                $permission = $this->getCatalogPermission($categoryId, $scopeId, $groupId);
                $this->assertNull($permission->getGrantCatalogCategoryView());
            }
        }

        foreach ($this->publicGroupsId as $groupId) {
            foreach ([10, 333] as $categoryId) {
                $permission = $this->getCatalogPermission($categoryId, $scopeId, $groupId);
                $this->assertEquals(CatalogPermission::PERMISSION_DENY, $permission->getGrantCatalogCategoryView());
            }
        }

        $sharedCatalogGroupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        $customGroupsId = array_diff($sharedCatalogGroupIds, $this->publicGroupsId);
        $this->assertNotEmpty($customGroupsId);
        foreach ($customGroupsId as $groupId) {
            $permission = $this->getCatalogPermission(10, $scopeId, $groupId);
            $this->assertEquals(CatalogPermission::PERMISSION_ALLOW, $permission->getGrantCatalogCategoryView());

            $permission = $this->getCatalogPermission(333, $scopeId, $groupId);
            $this->assertEquals(CatalogPermission::PERMISSION_DENY, $permission->getGrantCatalogCategoryView());
        }

        $customCatalogGroupId = (int) array_values($customGroupsId)[0];
        $this->catalogPermissionManagement->removeAllPermissions($customCatalogGroupId);
        foreach ([10, 333] as $categoryId) {
            $this->synchronizer->updateCategoryPermissions($categoryId, [$customCatalogGroupId]);

            $permission = $this->getCatalogPermission($categoryId, $scopeId, $customCatalogGroupId);
            $this->assertNull($permission->getGrantCatalogCategoryView());
        }
    }

    /**
     * @return void
     */
    public function testUpdateExitedCategoryPermission()
    {
        $categoryId = 10;
        $sharedCatalogGroupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        $customGroupsId = array_diff($sharedCatalogGroupIds, $this->publicGroupsId);
        $sharedCatalogGroupId = array_shift($customGroupsId);

        $this->enableSharedCatalog();

        $this->synchronizer->updateCategoryPermissions($categoryId, [$sharedCatalogGroupId]);
        $permission = $this->getCatalogPermission($categoryId, null, $sharedCatalogGroupId);
        $this->assertNotEmpty($permission->getId());

        $permission->setGrantCatalogProductPrice(CatalogPermission::PERMISSION_DENY);
        $permission->save();
        $this->synchronizer->updateCategoryPermissions($categoryId, [$sharedCatalogGroupId]);

        $permission = $this->getCatalogPermission($categoryId, null, $sharedCatalogGroupId);
        $this->assertEquals(CatalogPermission::PERMISSION_DENY, $permission->getGrantCatalogProductPrice());
    }

    /**
     * @depends testUpdateCategoryPermissions
     * @dataProvider scopesDataProvider
     * @param string|null $scopeCode
     * @return void
     */
    public function testRemoveCategoryPermissions($scopeCode)
    {
        $this->enableSharedCatalog();

        $sharedCatalogGroupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        $this->assertNotEmpty($sharedCatalogGroupIds);
        foreach ([10, 333] as $categoryId) {
            $this->synchronizer->updateCategoryPermissions($categoryId, $sharedCatalogGroupIds);
        }

        $scopeId = $scopeCode
            ? (int) $this->websiteRepository->get($scopeCode)->getId()
            : null;
        $this->synchronizer->removeCategoryPermissions($scopeId);

        foreach ([10, 333] as $categoryId) {
            foreach ($sharedCatalogGroupIds as $groupId) {
                $catalogPermission = $this->getCatalogPermission($categoryId, $scopeId, (int) $groupId);
                $this->assertNull($catalogPermission->getGrantCatalogCategoryView());
            }
        }
    }

    /**
     * @return array
     */
    public function scopesDataProvider(): array
    {
        return [
            'Global scope' => [null],
            'Main website scope' => ['base'],
            'Second website scope' => ['test'],
        ];
    }

    /**
     * @param int $categoryId
     * @param int|null $websiteId
     * @param int|null $groupId
     * @return CatalogPermission
     */
    private function getCatalogPermission(int $categoryId, $websiteId, $groupId): CatalogPermission
    {
        $catalogPermissionsCollection = $this->catalogPermissionsCollectionFactory->create();
        $catalogPermissionsCollection->addFieldToFilter('category_id', ['eq' => $categoryId]);
        $catalogPermissionsCollection->addFieldToFilter('website_id', ['seq' => $websiteId]);
        $catalogPermissionsCollection->addFieldToFilter('customer_group_id', ['seq' => $groupId]);
        /** @var CatalogPermission $permission */
        $permission = $catalogPermissionsCollection->getFirstItem();

        return $permission;
    }

    /**
     * Enable shared catalog
     *
     * @param string $scope
     * @param int|null $scopeId
     * @return void
     */
    private function enableSharedCatalog(
        string $scope = ReinitableConfigInterface::SCOPE_TYPE_DEFAULT,
        ?int $scopeId = null
    ): void {
        $configModel = Bootstrap::getObjectManager()->get(\Magento\Config\Model\Config::class);
        $configModel->setScope($scope);
        $configModel->setScopeId($scopeId);
        $configModel->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, 1);
        $configModel->save();
        $reinitableConfig = Bootstrap::getObjectManager()->get(ReinitableConfigInterface::class);
        $reinitableConfig->reinit();

        $this->catalogPermissionManagement->setPermissionsForAllCategories($scopeId);
    }
}
