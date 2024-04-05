<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\CatalogPermissions\Model\Permission as CatalogPermission;
use Magento\Config\Model\Config as ConfigModel;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Store/_files/website.php
 * @magentoConfigFixture btob/website_configuration/company_active 1
 */
class DenyPermissionsForNewCategoryTest extends TestCase
{
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var CatalogPermissionManagement
     */
    private $catalogPermissionManagement;

    /**
     * @var CustomerGroupManagement
     */
    private $customerGroupManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->enableSharedCatalog();

        $this->categoryRepository = Bootstrap::getObjectManager()->get(CategoryRepositoryInterface::class);
        $this->catalogPermissionManagement = Bootstrap::getObjectManager()->get(CatalogPermissionManagement::class);
        $this->customerGroupManagement = Bootstrap::getObjectManager()->get(CustomerGroupManagement::class);
    }

    /**
     * @return void
     */
    public function testCreateNewCategory()
    {
        $category = Bootstrap::getObjectManager()->create(CategoryInterface::class);
        $category->setName('Test cat 1');
        $category->setParentId(2);
        $category->setPath('1/2/3');
        $category->setLevel(2);
        $category->setIsActive(true);
        $category = $this->categoryRepository->save($category);

        foreach ($this->customerGroupManagement->getSharedCatalogGroupIds() as $groupId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                (int) $category->getId(),
                null,
                (int) $groupId
            );
            $this->assertEquals(CatalogPermission::PERMISSION_DENY, $sharedCatalogPermission->getPermission());
        }

        foreach ($this->customerGroupManagement->getGroupIdsNotInSharedCatalogs() as $groupId) {
            $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
                (int) $category->getId(),
                null,
                (int) $groupId
            );
            $this->assertNull($sharedCatalogPermission->getPermission());
        }
    }

    /**
     * @return void
     */
    private function enableSharedCatalog()
    {
        $config = Bootstrap::getObjectManager()->create(ConfigModel::class);
        $config->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, 1);
        $config->save();
    }
}
