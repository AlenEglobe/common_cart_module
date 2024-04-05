<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Api;

use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog as SharedCatalogFixture;
use Magento\Store\Test\Fixture\Website;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Test\Fixture\User;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class SharedCatalogRepositoryInterfaceTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var SharedCatalogRepositoryInterface
     */
    private $repository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->repository = $this->objectManager->create(SharedCatalogRepositoryInterface::class);
    }

    /**
     * Test Shared Catalog Repository getList with different search criteria
     *
     * @throws LocalizedException
     */
    #[
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(SharedCatalogFixture::class, ['description' => 'A'], 'shared_catalog1'),
        DataFixture(SharedCatalogFixture::class, ['description' => 'Z'], 'shared_catalog2'),
        DataFixture(SharedCatalogFixture::class, ['description' => 'B'], 'shared_catalog3'),
        DataFixture(SharedCatalogFixture::class, ['description' => 'C'], 'shared_catalog4'),
    ]
    public function testGetList()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog1 = DataFixtureStorageManager::getStorage()->get('shared_catalog1');
        $sharedCatalog2 = DataFixtureStorageManager::getStorage()->get('shared_catalog2');
        $sharedCatalog3 = DataFixtureStorageManager::getStorage()->get('shared_catalog3');
        $sharedCatalog4 = DataFixtureStorageManager::getStorage()->get('shared_catalog4');

        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->objectManager->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(SharedCatalogInterface::NAME)
            ->setValue($sharedCatalog1->getName())
            ->create();
        $filter2 = $filterBuilder->setField(SharedCatalogInterface::NAME)
            ->setValue($sharedCatalog2->getName())
            ->create();
        $filter3 = $filterBuilder->setField(SharedCatalogInterface::NAME)
            ->setValue($sharedCatalog3->getName())
            ->create();
        $filter4 = $filterBuilder->setField(SharedCatalogInterface::NAME)
            ->setValue($sharedCatalog4->getName())
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = $this->objectManager->create(SortOrderBuilder::class);

        $sortOrder = $sortOrderBuilder->setField(SharedCatalogInterface::DESCRIPTION)
            ->setDirection(SortOrder::SORT_ASC)
            ->create();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->create(SearchCriteriaBuilder::class);

        $searchCriteriaBuilder->addFilters([$filter1, $filter2, $filter3, $filter4]);
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->setPageSize(3);
        $searchCriteriaBuilder->setCurrentPage(2);

        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);

        $this->assertEquals(4, $searchResult->getTotalCount());
        $items = array_values($searchResult->getItems());
        $this->assertCount(1, $items);

        /** @var SharedCatalogInterface $itemInLastPage */
        $itemInLastPage = reset($items);
        $this->assertEquals($sharedCatalog2->getName(), $itemInLastPage->getName());
    }

    /**
     * Verify admin with restriction to specific website able to get shared catalog without store id specified.
     *
     * @magentoAppArea adminhtml
     * @return void
     * @throws NoSuchEntityException|LocalizedException
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        DataFixture(Website::class, as: 'website'),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(RoleFixture::class, as: 'restrictedRole'),
        DataFixture(User::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$restrictedUser.id$',
                'super_user_id' => '$company_admin.id$'
            ],
            'company'
        ),
        DataFixture(SharedCatalogFixture::class, ['store_id' => null], 'shared_catalog')
    ]
    public function testGetSharedCatalogWithUserRestrictedToSpecificWebsite(): void
    {
        $sharedCatalog = $this->repository->get(
            DataFixtureStorageManager::getStorage()->get('shared_catalog')->getId()
        );
        self::assertNotEmpty($sharedCatalog->getId());
    }
}
