<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface as ConfigResourceInterface;
use Magento\CatalogPermissions\Model\Permission as CatalogPermission;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Catalog\Test\Fixture\Category;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\Store\Test\Fixture\Website;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[
    DataFixture(
        Website::class,
        [
            'code' => 'test',
            'name' => 'Test Website',
            'default_group_id' => '1',
            'is_default' => '0'
        ],
        'website'
    ),
    DataFixture(
        Category::class,
        [
            'name' => 'Category 1',
            'parent_id' => 2,
            'path' => '1/2/333',
            'level' => 2,
            'is_active' => true,
            'position' => 1
        ],
        'category_333'
    ),
    DataFixture(
        Category::class,
        [
            'name' => 'Category',
            'parent_id' => 2,
            'path' => '1/2/310',
            'level' => 2,
            'is_active' => true,
            'position' => 1
        ],
        'category_10'
    )
]
class CatalogPermissionManagementTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    /**
     * @var CatalogPermissionManagement
     */
    private $catalogPermissionManagement;

    /**
     * @var CustomerGroupManagement
     */
    private $customerGroupManagement;

    /**
     * @var ConfigResourceInterface
     */
    private $configResource;

    /**
     * @var SharedCatalogInterface
     */
    private $publicCatalog;

    /**
     * @var SharedCatalogManagementInterface
     */
    private $sharedCatalogManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->catalogPermissionManagement = $this->objectManager->create(CatalogPermissionManagement::class);
        $this->customerGroupManagement = $this->objectManager->get(CustomerGroupManagement::class);
        $this->configResource = $this->objectManager->get(ConfigResourceInterface::class);
        $this->reinitableConfig = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->sharedCatalogManagement = $this->objectManager->get(SharedCatalogManagementInterface::class);
        $this->publicCatalog = $this->sharedCatalogManagement->getPublicCatalog();
    }

    /**
     * @return void
     */
    public function testGetSharedCatalogPermission()
    {
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category_333')->getId();
        $websiteId = (int)DataFixtureStorageManager::getStorage()->get('website')->getId();
        $groupId = (int) $this->publicCatalog->getCustomerGroupId();

        $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
            $categoryId,
            $websiteId,
            $groupId
        );
        $this->assertNull($sharedCatalogPermission->getPermission());

        $newSharedCatalogPermission = Bootstrap::getObjectManager()->create(Permission::class);
        $newSharedCatalogPermission->setCategoryId($categoryId);
        $newSharedCatalogPermission->setWebsiteId(null);
        $newSharedCatalogPermission->setCustomerGroupId($groupId);
        $newSharedCatalogPermission->setPermission(CatalogPermission::PERMISSION_DENY);
        $newSharedCatalogPermission->save();
        foreach ([null, $websiteId] as $scopeId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                $categoryId,
                $scopeId,
                $groupId
            );
            $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
        }
    }

    /**
     * @depends testGetSharedCatalogPermission
     * @dataProvider scopesDataProvider
     * @param string|null $scopeCode
     * @return void
     */
    public function testSetPermissionsForAllCategories($scopeCode)
    {
        $scope = $scopeCode
            ? ScopeInterface::SCOPE_WEBSITES
            : ReinitableConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = $scopeCode
            ? (int)DataFixtureStorageManager::getStorage()->get('website')->getId()
            : null;
        $this->configResource->saveConfig(SharedCatalogConfig::CONFIG_SHARED_CATALOG, 1, $scope, (int) $scopeId);
        $this->reinitableConfig->reinit();
        $this->catalogPermissionManagement->setPermissionsForAllCategories($scopeId);

        $categoryIds = $this-> getCategoryIds();

        $groupIdsNotInSharedCatalogs = $this->customerGroupManagement->getGroupIdsNotInSharedCatalogs();
        foreach ($groupIdsNotInSharedCatalogs as $groupId) {
            foreach ($categoryIds as $categoryId) {
                $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                    $categoryId,
                    $scopeId,
                    (int) $groupId
                );
                $this->assertNull($sharedCatalogPermission->getPermission());
            }
        }

        $publicGroupsId = [
            GroupInterface::NOT_LOGGED_IN_ID,
            $this->publicCatalog->getCustomerGroupId(),
        ];
        foreach ($publicGroupsId as $groupId) {
            foreach ($categoryIds as $categoryId) {
                $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                    $categoryId,
                    $scopeId,
                    $groupId
                );
                $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
            }
        }

        $sharedCatalogGroupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        $customGroupsId = array_diff($sharedCatalogGroupIds, $publicGroupsId);
        foreach ($customGroupsId as $groupId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                (int)DataFixtureStorageManager::getStorage()->get('category_10')->getId(),
                $scopeId,
                (int) $groupId
            );
            $this->assertEquals(CatalogPermission::PERMISSION_ALLOW, $sharedCatalogPermission->getPermission());

            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                (int)DataFixtureStorageManager::getStorage()->get('category_333')->getId(),
                $scopeId,
                (int) $groupId
            );
            $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
        }
    }

    /**
     * @return void
     */
    public function testSetDenyPermissionsForCategory()
    {
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category_10')->getId();

        $this->catalogPermissionManagement->setDenyPermissionsForCategory($categoryId);
        foreach ($this->customerGroupManagement->getSharedCatalogGroupIds() as $groupId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                $categoryId,
                null,
                (int) $groupId
            );
            $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
        }
        foreach ($this->customerGroupManagement->getGroupIdsNotInSharedCatalogs() as $groupId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                $categoryId,
                null,
                (int) $groupId
            );
            $this->assertNull($sharedCatalogPermission->getPermission());
        }
    }

    /**
     * @return void
     */
    public function testSetAllowPermissions()
    {
        $categoryIds = $this->getCategoryIds();
        $groupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        $this->catalogPermissionManagement->setAllowPermissions($categoryIds, $groupIds);
        foreach ($categoryIds as $categoryId) {
            foreach ($groupIds as $groupId) {
                $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                    $categoryId,
                    null,
                    (int) $groupId
                );
                $this->assertEquals(CatalogPermission::PERMISSION_ALLOW, $sharedCatalogPermission->getPermission());
            }
        }
    }

    /**
     * @return void
     */
    public function testSetDenyPermissions()
    {
        $categoryIds = $this->getCategoryIds();
        $groupIds = $this->customerGroupManagement->getSharedCatalogGroupIds();
        
        $this->catalogPermissionManagement->setDenyPermissions($categoryIds, $groupIds);
        foreach ($categoryIds as $categoryId) {
            foreach ($groupIds as $groupId) {
                $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                    $categoryId,
                    null,
                    (int) $groupId
                );
                $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
            }
        }
    }

    /**
     * @depends testSetAllowPermissions
     * @return void
     */
    public function testRemoveAllPermissions()
    {
        $categoryIds = $this->getCategoryIds();
        $groupId = (int) $this->publicCatalog->getCustomerGroupId();

        $this->catalogPermissionManagement->setAllowPermissions($categoryIds, [$groupId]);
        $this->catalogPermissionManagement->removeAllPermissions($groupId);
        foreach ($categoryIds as $categoryId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                $categoryId,
                null,
                $groupId
            );
            $this->assertNull($sharedCatalogPermission->getPermission());
        }
    }

    /**
     * @return array
     */
    private function getCategoryIds(): array
    {
        return [
            (int)DataFixtureStorageManager::getStorage()->get('category_333')->getId(),
            (int)DataFixtureStorageManager::getStorage()->get('category_10')->getId()
        ];
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
}
