<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Api;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexProcessor;
use Magento\SharedCatalog\Test\Fixture\AssignProductsCategory;
use Magento\SharedCatalog\Test\Fixture\AssignCategory as AssignCategorySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignCompany as AssignCompanySharedCatalog;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionIndexProcessor;
use Magento\CatalogPermissions\Model\Indexer\Product\Processor as ProductPermissionIndexProcessor;
use Magento\CatalogPermissions\Model\Permission  as CatalogPermission;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Elasticsearch\Model\ResourceModel\Index;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\AbstractProcessor;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Indexer\Cron\UpdateMview;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Model\CatalogPermissionManagement;
use Magento\SharedCatalog\Model\SharedCatalogFactory;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog as SharedCatalogFixture;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SharedCatalogRepositoryInterfacePermissionTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CatalogPermissionManagement
     */
    private $catalogPermissionManagement;

    /**
     * @var bool
     */
    private $resetIndexersMode = false;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->catalogPermissionManagement = $this->objectManager->create(CatalogPermissionManagement::class);
    }

    /**
     * @dataProvider indexerModeDataProvider
     *
     * @param bool $isIndexerScheduled
     * @return void
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DbIsolation(false),
        Config('btob/website_configuration/company_active', 1, 'website'),
        Config('btob/website_configuration/sharedcatalog_active', 1, 'website'),
        Config('catalog/magento_catalogpermissions/enabled', 1, 'store', 'current'),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'customer_group_id' => '$customer_group.id$',
                'super_user_id' => '$company_admin.id$',
            ],
            'company'
        ),
        DataFixture(
            SharedCatalogFixture::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(CategoryFixture::class, as: 'category'),
        DataFixture(ProductFixture::class, as: 'simple_product'),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$simple_product$'],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => ['$simple_product.id$'],
                'catalog_id' => '$shared_catalog.id$'
            ]
        ),
        DataFixture(
            AssignCategorySharedCatalog::class,
            [
                'category' => '$category$',
                'catalog_id' => '$shared_catalog.id$'
            ]
        ),
        DataFixture(
            AssignCompanySharedCatalog::class,
            [
                'company' => '$company$',
                'catalog_id' => '$shared_catalog.id$'
            ]
        )
    ]
    public function testNewSharedCatalogCustomerGroupShouldHaveDeniedPermissionForAllCategoriesAndProducts(
        bool $isIndexerScheduled
    ): void {

        $this->setIndexerMode(
            CategoryPermissionIndexProcessor::class,
            $isIndexerScheduled
        );

        $this->setIndexerMode(
            ProductPermissionIndexProcessor::class,
            $isIndexerScheduled
        );

        $website = $this->objectManager->get(StoreManagerInterface::class)->getWebsite('base');

        /** @var CategoryInterface $category **/
        $category = DataFixtureStorageManager::getStorage()->get('category');
        $categoryId = $category->getId();

        /** @var ProductInterface $sharedCatalog */
        $product = DataFixtureStorageManager::getStorage()->get('simple_product');
        $productId = $product->getId();

        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        self::assertNotNull($sharedCatalog->getCustomerGroupId());

        $this->catalogPermissionManagement->setDenyPermissions(
            [$categoryId],
            [$sharedCatalog->getCustomerGroupId()]
        );

        $sharedCatalogPermission = $this->catalogPermissionManagement->getSharedCatalogPermission(
            (int)$categoryId,
            null,
            $sharedCatalog->getCustomerGroupId()
        );
        self::assertEquals(
            CatalogPermission::PERMISSION_DENY,
            $sharedCatalogPermission->getPermission()
        );

        $this->queueConsumerStart('sharedCatalogUpdateCategoryPermissions');
        $this->queueConsumerStart('sharedCatalogUpdatePrice');
        $this->reindexAllInvalid();
        if ($isIndexerScheduled) {
            $this->updateMview();
        }
        /**
         * @var \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index $catalogPermissionResource
         */
        $catalogPermissionResource = $this->objectManager->get(
            \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index::class
        );
        $catalogPermission = $catalogPermissionResource->getIndexForCategory(
            $categoryId,
            $sharedCatalog->getCustomerGroupId(),
            $website->getId()
        );
        $expectedCatalogPermission = [
            'category_id' => (string)$categoryId,
            'website_id' => $website->getId(),
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'grant_catalog_category_view' => (string)CatalogPermission::PERMISSION_DENY,
            'grant_catalog_product_price' => (string)CatalogPermission::PERMISSION_DENY,
            'grant_checkout_items' => (string)CatalogPermission::PERMISSION_DENY,
        ];
        self::assertIsArray($catalogPermission);
        self::assertArrayHasKey($categoryId, $catalogPermission);
        self::assertEquals(
            $expectedCatalogPermission,
            $catalogPermission[$categoryId]
        );

        $catalogPermission = $catalogPermissionResource->getIndexForProduct(
            $productId,
            $sharedCatalog->getCustomerGroupId(),
            $website->getDefaultStore()->getId()
        );
        $expectedCatalogPermission = [
            'product_id' => $productId,
            'store_id' => $website->getDefaultStore()->getId(),
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'grant_catalog_category_view' => (string)CatalogPermission::PERMISSION_DENY,
            'grant_catalog_product_price' => (string)CatalogPermission::PERMISSION_DENY,
            'grant_checkout_items' => (string)CatalogPermission::PERMISSION_DENY,
            'index_id' => '3'
        ];
        self::assertIsArray($catalogPermission);
        self::assertArrayHasKey($productId, $catalogPermission);
        self::assertEquals(
            $expectedCatalogPermission,
            $catalogPermission[$productId]
        );
    }

    /**
     * @dataProvider indexerModeDataProvider
     *
     * @param bool $isIndexerScheduled
     * @return void
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DbIsolation(false),
        Config('btob/website_configuration/company_active', 1, 'website'),
        Config('btob/website_configuration/sharedcatalog_active', 1, 'website'),
        Config('catalog/magento_catalogpermissions/enabled', 1, 'store', 'current'),
        Config('catalog/magento_catalogpermissions/grant_catalog_category_view', 1, 'website'),
        Config('catalog/magento_catalogpermissions/grant_catalog_product_price', 1, 'website'),
        Config('catalog/magento_catalogpermissions/grant_checkout_items', 1, 'website'),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'customer_group_id' => '$customer_group.id$',
                'super_user_id' => '$company_admin.id$',
            ],
            'company'
        ),
        DataFixture(
            SharedCatalogFixture::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(CategoryFixture::class, as: 'category'),
        DataFixture(ProductFixture::class, as: 'simple_product'),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$simple_product$'],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => ['$simple_product.id$'],
                'catalog_id' => '$shared_catalog.id$'
            ]
        ),
        DataFixture(
            AssignCategorySharedCatalog::class,
            [
                'category' => '$category$',
                'catalog_id' => '$shared_catalog.id$'
            ]
        ),
        DataFixture(
            AssignCompanySharedCatalog::class,
            [
                'company' => '$company$',
                'catalog_id' => '$shared_catalog.id$'
            ]
        )
    ]
    public function testNewSharedCatalogCustomerGroupShouldHavePermissionForAssignedCategoriesAndProducts(
        bool $isIndexerScheduled
    ): void {
        $this->setIndexerMode(
            CategoryPermissionIndexProcessor::class,
            $isIndexerScheduled
        );
        $this->setIndexerMode(
            ProductPermissionIndexProcessor::class,
            $isIndexerScheduled
        );
        $this->setIndexerMode(
            PriceIndexProcessor::class,
            $isIndexerScheduled
        );

        $website = $this->objectManager->get(StoreManagerInterface::class)->getWebsite('base');

        /** @var CategoryInterface $category **/
        $category = DataFixtureStorageManager::getStorage()->get('category');
        $categoryId = $category->getId();

        /** @var ProductInterface $sharedCatalog */
        $product = DataFixtureStorageManager::getStorage()->get('simple_product');
        $productId = $product->getId();

        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        self::assertNotNull($sharedCatalog->getCustomerGroupId());

        $this->catalogPermissionManagement->setAllowPermissions(
            [$categoryId],
            [$sharedCatalog->getCustomerGroupId()]
        );

        $this->queueConsumerStart('sharedCatalogUpdateCategoryPermissions');
        $this->queueConsumerStart('sharedCatalogUpdatePrice');
        $this->reindexAllInvalid();
        if ($isIndexerScheduled) {
            $this->updateMview();
        }

        /** @var \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index $catalogPermissionResource */
        $catalogPermissionResource = $this->objectManager->get(
            \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index::class
        );
        $catalogPermission = $catalogPermissionResource->getIndexForCategory(
            $categoryId,
            $sharedCatalog->getCustomerGroupId(),
            $website->getId()
        );
        $expectedCatalogPermission = [
            'category_id' => (string)$categoryId,
            'website_id' => $website->getId(),
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'grant_catalog_category_view' => (string)CatalogPermission::PERMISSION_ALLOW,
            'grant_catalog_product_price' => (string)CatalogPermission::PERMISSION_ALLOW,
            'grant_checkout_items' => (string)CatalogPermission::PERMISSION_ALLOW,
        ];
        self::assertIsArray($catalogPermission);
        self::assertArrayHasKey($categoryId, $catalogPermission);
        self::assertEquals(
            $expectedCatalogPermission,
            $catalogPermission[$categoryId]
        );

        $catalogPermission = $catalogPermissionResource->getIndexForProduct(
            $productId,
            $sharedCatalog->getCustomerGroupId(),
            $website->getDefaultStore()->getId()
        );
        $expectedCatalogPermission = [
            'product_id' => $productId,
            'store_id' => $website->getDefaultStore()->getId(),
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'grant_catalog_category_view' => (string)CatalogPermission::PERMISSION_ALLOW,
            'grant_catalog_product_price' => (string)CatalogPermission::PERMISSION_ALLOW,
            'grant_checkout_items' => (string)CatalogPermission::PERMISSION_ALLOW,
            'index_id' => '3'
        ];
        self::assertIsArray($catalogPermission);
        self::assertArrayHasKey($productId, $catalogPermission);
        self::assertEquals(
            $expectedCatalogPermission,
            $catalogPermission[$productId]
        );
        /** @var Index $priceIndexResource */
        $priceIndexResource = $this->objectManager->get(Index::class);
        $priceIndexData = $priceIndexResource->getPriceIndexData(
            [$productId],
            $website->getDefaultStore()->getId()
        );
        self::assertIsArray($priceIndexData);
        self::assertArrayHasKey($productId, $priceIndexData);
        self::assertArrayHasKey($sharedCatalog->getCustomerGroupId(), $priceIndexData[$productId]);
    }

    /**
     * @param string $consumerName
     * @param int $maxNumberOfMessages
     * @throws LocalizedException
     */
    private function queueConsumerStart(string $consumerName, int $maxNumberOfMessages = 1000): void
    {
        /** @var ConsumerFactory $consumerFactory */
        $consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $categoryPermissionsUpdater = $consumerFactory->get($consumerName);
        $categoryPermissionsUpdater->process($maxNumberOfMessages);
    }

    private function setIndexerMode(string $processorClassName, bool $isScheduled)
    {
        /** @var AbstractProcessor $processor */
        $processor = $this->objectManager->get($processorClassName);
        if ($isScheduled !== $processor->getIndexer()->isScheduled()) {
            $processor->getIndexer()->setScheduled($isScheduled);
            $this->resetIndexersMode = true;
        }
    }

    private function reindexAllInvalid(): void
    {
        $this->objectManager->create(\Magento\Indexer\Model\Processor::class)->reindexAllInvalid();
    }

    private function updateMview(): void
    {
        $this->objectManager->create(UpdateMview::class)->execute();
    }

    /**
     * @return array
     */
    public function indexerModeDataProvider(): array
    {
        return [
            ['isIndexerScheduled' => true],
            ['isIndexerScheduled' => false]
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        if ($this->resetIndexersMode) {
            $this->setIndexerMode(
                CategoryPermissionIndexProcessor::class,
                false
            );

            $this->setIndexerMode(
                ProductPermissionIndexProcessor::class,
                false
            );

            $this->setIndexerMode(
                PriceIndexProcessor::class,
                false
            );
        }

        $this->resetIndexersMode = false;
    }
}
