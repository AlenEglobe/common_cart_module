<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Config\Model\Config as ConfigModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/categories.php
 * @magentoConfigFixture btob/website_configuration/company_active 1
 */
class DeleteChildCategoryPermissionsTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ResourceConnection
     */
    private $connection;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);
        $this->connection = $this->objectManager->get(ResourceConnection::class);
        $this->enableSharedCatalog(true);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->enableSharedCatalog(false);
    }

    /**
     * The purpose of the test is to check that Category Permissions are deleted for child categories
     * when their parent category is deleted
     */
    public function testDeleteChildCategoryPermissionsTest()
    {
        $parentCategoryId = 3;
        $parentCategory = $this->categoryRepository->get($parentCategoryId);
        $childrenCategoryIds = $parentCategory->getChildren();
        $connection = $this->connection->getConnection();
        $tableName = $this->connection->getTableName('sharedcatalog_category_permissions');

        $select = $connection->select()->from($tableName)->where('category_id IN (?)', $childrenCategoryIds);
        $containsTwoRecords = $connection->fetchAll($select);
        $this->assertEquals(2, count($containsTwoRecords), 'Should contain two Category Permissions records');

        $this->categoryRepository->delete($parentCategory);
        $select = $connection->select()->from($tableName)->where('category_id IN (?)', $childrenCategoryIds);
        $containsNoRecords = $connection->fetchAll($select);
        $this->assertEmpty($containsNoRecords, 'Should contain no Category Permissions records');
    }

    /**
     * Enable/Disable Shared Catalog
     *
     * @param bool $isEnabled
     * @return void
     */
    private function enableSharedCatalog($isEnabled)
    {
        $config = $this->objectManager->create(ConfigModel::class);
        $config->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, (int)$isEnabled);
        $config->save();
    }
}
