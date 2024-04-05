<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model;

use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Logging\Model\Event;
use Magento\Logging\Model\ResourceModel\Event\Changes\CollectionFactory as EventChangesCollectionFactory;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection;
use Magento\Logging\Model\ResourceModel\Event\CollectionFactory;
use Magento\User\Test\Fixture\User;

/**
 * Test for class \Magento\SharedCatalog\Controller\Adminhtml\SharedCatalog\Delete
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    Config('btob/website_configuration/company_active', 1),
    Config('btob/website_configuration/sharedcatalog_active', 1),
    Config('catalog/magento_catalogpermissions/enabled', 1),
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
    DataFixture(SharedCatalog::class, as: 'shared_catalog')
]
class LoggingTest extends AbstractBackendController
{
    /**
     * @var CollectionFactory
     */
    private $eventCollectionFactory;

    /**
     * @var EventChangesCollectionFactory
     */
    private $eventChangesCollectionFactory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->eventCollectionFactory = $this->_objectManager->get(CollectionFactory::class);
        $this->eventChangesCollectionFactory = $this->_objectManager->get(EventChangesCollectionFactory::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->eventCollectionFactory = null;
        $this->eventChangesCollectionFactory = null;
    }

    /**
     * Test logging entry after shared catalog edit action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testEditSharedCatalogActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        $this->dispatch('backend/shared_catalog/sharedCatalog/edit/shared_catalog_id/' . $sharedCatalog->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_edit', $event->getFullaction());
        $this->assertEquals('view', $event->getAction());
        $this->assertStringContainsString('(' . $sharedCatalog->getName() . ')', $event->getInfo());
        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);
    }

    /**
     * Test logging entry after shared catalog save action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testSaveSharedCatalogActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        $sharedCatalogNameBeforeChange = $sharedCatalog->getName();
        $requestData = [
            'name' => 'Testaaaaa',
            'description' => 'Descriptions test',
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'type' => (string)$sharedCatalog->getType(),
            'tax_class_id' => (string)$sharedCatalog->getTaxClassId(),
        ];

        $this->getRequest()->setPostValue(['catalog_details' => $requestData]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->dispatch('backend/shared_catalog/sharedCatalog/save/shared_catalog_id/' . $sharedCatalog->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_save', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertStringContainsString('(' . $requestData['name'] . ')', $event->getInfo());

        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);

        $changes = $this->eventChangesCollectionFactory->create()
            ->addFieldToFilter('event_id', $event->getId())
            ->getItems();

        foreach ($changes as $change) {
            $this->assertStringContainsString($sharedCatalogNameBeforeChange, $change->getOriginalData());
            $this->assertStringContainsString($requestData['name'], $change->getResultData());
        }
    }

    /**
     * Test logging entry after new shared catalog save action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testUpdateSharedCatalogActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        $requestData = [
            'name' => 'Updated Shared Catalog',
            'description' => 'Updated Shared Catalog description',
            'type' => (string)$sharedCatalog->getType(),
            'customer_group_id' => (string)$sharedCatalog->getCustomerGroupId(),
            'tax_class_id' => (string)$sharedCatalog->getTaxClassId(),
        ];

        $this->getRequest()->setPostValue(['catalog_details' => $requestData]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->dispatch('backend/shared_catalog/sharedCatalog/save/shared_catalog_id/' . $sharedCatalog->getId());
        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_save', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertStringContainsString('(' . $requestData['name'] . ')', $event->getInfo());

        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);

        $changes = $this->eventChangesCollectionFactory->create()
            ->addFieldToFilter('event_id', $event->getId())
            ->getItems();

        foreach ($changes as $change) {
            $this->assertStringContainsString($requestData['name'], $change->getResultData());
            $this->assertStringContainsString($requestData['description'], $change->getResultData());
            $this->assertStringContainsString((string)$sharedCatalog->getName(), $change->getOriginalData());
        }
    }

    /**
     * Test logging entry after shared catalog delete action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testDeleteSharedCatalogActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        $sharedCatalogId = $sharedCatalog->getId();
        $sharedCatalogName = $sharedCatalog->getName();

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/shared_catalog/sharedCatalog/delete/shared_catalog_id/' . $sharedCatalogId);

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_delete', $event->getFullaction());
        $this->assertEquals('delete', $event->getAction());

        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);

        $changes = $this->eventChangesCollectionFactory->create()
            ->addFieldToFilter('event_id', $event->getId())
            ->getItems();

        foreach ($changes as $change) {
            $this->assertStringContainsString(
                sprintf('"entity_id":"%s"', $sharedCatalogId),
                $change->getOriginalData()
            );
            $this->assertStringContainsString(sprintf('"name":"%s"', $sharedCatalogName), $change->getOriginalData());
            $this->assertStringContainsString('__was_deleted', $change->getResultData());
        }
    }

    /**
     * Test logging entry after shared catalog delete action of non-existing shared catalog
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testDeleteSharedCatalogActionLoggingWithBadData(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/shared_catalog/sharedCatalog/delete/shared_catalog_id/x1233221');

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_delete', $event->getFullaction());
        $this->assertEquals('delete', $event->getAction());
        $this->assertEquals('failure', $event->getStatus());
        $this->assertStringContainsString(
            (string)__('Requested Shared Catalog is not found'),
            $event->getErrorMessage()
        );
    }

    /**
     * Test logging entry after shared catalog mass delete action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    #[
        DataFixture(RoleFixture::class, as: 'restrictedRole'),
        DataFixture(User::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
        DataFixture(Customer::class, as: 'company_admin_1'),
        DataFixture(Customer::class, as: 'company_admin_2'),
        DataFixture(CustomerGroup::class, as: 'customer_group_1'),
        DataFixture(CustomerGroup::class, as: 'customer_group_2'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin_1.id$',
                'customer_group_id' => '$customer_group_1.id$',
                'sales_representative_id' => '$restrictedUser.id$',
            ],
            'company_1'
        ),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin_2.id$',
                'customer_group_id' => '$customer_group_2.id$',
                'sales_representative_id' => '$restrictedUser.id$',
            ],
            'company_2'
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group_1.id$'
            ],
            'shared_catalog_1'
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group_2.id$'
            ],
            'shared_catalog_2'
        )
    ]
    public function testMassDeleteSharedCatalogActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog1 */
        $sharedCatalog1 = DataFixtureStorageManager::getStorage()->get('shared_catalog_1');
        /** @var SharedCatalogInterface $sharedCatalog2 */
        $sharedCatalog2 = DataFixtureStorageManager::getStorage()->get('shared_catalog_2');
        $sharedCatalogs = [$sharedCatalog1, $sharedCatalog2];

        $selectedSharedCatalogs = [];
        foreach ($sharedCatalogs as $sharedCatalog) {
            $selectedSharedCatalogs[$sharedCatalog->getId()] = $sharedCatalog->getName();
        }

        $requestData = [
            'selected' => array_keys($selectedSharedCatalogs),
            'namespace' => 'shared_catalog_listing',
            'filters' => ['placeholder' => true],
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/shared_catalog/sharedCatalog/massDelete');

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_massDelete', $event->getFullaction());
        $this->assertEquals('massDelete', $event->getAction());
        $this->assertStringContainsString(implode(', ', array_keys($selectedSharedCatalogs)), $event->getInfo());
        $this->assertEventData($event);
    }

    /**
     * Test logging entry after shared catalog companies edit action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testEditSharedCatalogCompanyActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        $this->dispatch('backend/shared_catalog/sharedCatalog/companies/shared_catalog_id/' . $sharedCatalog->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_companies', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertStringContainsString('(' . $sharedCatalog->getName() . ')', $event->getInfo());
        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);
    }

    /**
     * Test logging entry after shared catalog companies save action
     *
     * @return void
     * @throws LocalizedException|NoSuchEntityException
     */
    public function testSharedCatalogCompanySaveActionLogging(): void
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        $requestData = [
            'shared_catalog_id' => $sharedCatalog->getId()
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->dispatch('backend/shared_catalog/sharedCatalog_company/save');

        $event = $this->provideLatestEvent();

        $this->assertEquals('shared_catalog_sharedCatalog_company_save', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertStringContainsString('(' . $sharedCatalog->getName() . ')', $event->getInfo());

        $this->assertEventData($event, [
            'Id' => $sharedCatalog->getId(),
        ]);
    }

    /**
     * Returns latest logging entry
     *
     * @return Event
     */
    private function provideLatestEvent(): Event
    {
        $eventCollection = $this->eventCollectionFactory->create();
        $eventCollection->setOrder('log_id', Collection::SORT_ORDER_DESC);

        return $eventCollection->getFirstItem();
    }

    /**
     * Asserts common entry data
     *
     * @param Event $event
     * @param array $additionalData
     */
    private function assertEventData(Event $event, array $additionalData = []): void
    {
        $this->assertEquals('success', $event->getStatus());
        $this->assertIsNumeric($event->getUserId());
        $this->assertEquals($this->_getAdminCredentials()['user'], $event->getUser());
        $this->assertLessThanOrEqual(30, strtotime(date('Y-m-d H:i:s')) - strtotime($event->getTime()));
        foreach ($additionalData as $fieldName => $fieldValue) {
            $this->assertStringContainsString(sprintf('%s: %s', $fieldName, $fieldValue), $event->getInfo());
        }
    }
}
