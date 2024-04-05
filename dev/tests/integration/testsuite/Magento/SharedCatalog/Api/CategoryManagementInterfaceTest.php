<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Api;

use Magento\Catalog\Test\Fixture\Category;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection as SharedCatalogCollection;
use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    Config('btob/website_configuration/sharedcatalog_active', 1),
    DataFixture(Customer::class, as: 'company_admin'),
    DataFixture(CustomerGroup::class, as: 'customer_group'),
    DataFixture(
        \Magento\SharedCatalog\Test\Fixture\SharedCatalog::class,
        [
            'customer_group_id' => '$customer_group.id$'
        ],
        'shared_catalog'
    ),
    DataFixture(Category::class, as: 'category1'),
    DataFixture(Category::class, as: 'category2'),
    DataFixture(Category::class, as: 'category3'),
]
class CategoryManagementInterfaceTest extends TestCase
{
    /**
     * @var SharedCatalog
     */
    private $customSharedCatalog;

    /**
     * @var CategoryManagementInterface
     */
    private $categoryManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $sharedCatalogCollection = $objectManager->create(SharedCatalogCollection::class);
        $this->customSharedCatalog = $sharedCatalogCollection->getLastItem();

        $this->categoryManagement = $objectManager->create(CategoryManagementInterface::class);
    }

    /**
     * @return void
     */
    public function testAssignCategories()
    {
        /** @var \Magento\Catalog\Model\Category[] $categories */
        $categories = [
            DataFixtureStorageManager::getStorage()->get('category1'),
            DataFixtureStorageManager::getStorage()->get('category2'),
            DataFixtureStorageManager::getStorage()->get('category3'),
        ];
        $this->categoryManagement->assignCategories($this->customSharedCatalog->getId(), $categories);
        $this->testGetCategories(3);
    }

    /**
     * @return void
     */
    public function testUnassignCategories()
    {
        $this->testAssignCategories();

        /** @var \Magento\Catalog\Model\Category[] $categories */
        $categories = [
            DataFixtureStorageManager::getStorage()->get('category1'),
            DataFixtureStorageManager::getStorage()->get('category2'),
            DataFixtureStorageManager::getStorage()->get('category3'),
        ];
        $this->categoryManagement->unassignCategories($this->customSharedCatalog->getId(), $categories);
        $this->testGetCategories(0);
    }

    /**
     * @return void
     */
    private function testGetCategories($count = 0)
    {
        $categories = $this->categoryManagement->getCategories($this->customSharedCatalog->getId());
        $this->assertCount($count, $categories);
    }
}
