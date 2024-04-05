<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Logging\Model\Event;
use Magento\Logging\Model\ResourceModel\Event\Changes\CollectionFactory as EventChangesCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection;
use Magento\Logging\Model\ResourceModel\Event\CollectionFactory;

/**
 * Test for company actions logging
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 */
class LoggingTest extends AbstractBackendController
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

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
        $this->searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $this->eventCollectionFactory = $this->_objectManager->get(CollectionFactory::class);
        $this->eventChangesCollectionFactory = $this->_objectManager->get(EventChangesCollectionFactory::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->searchCriteriaBuilder = null;
        $this->eventCollectionFactory = null;
        $this->eventChangesCollectionFactory = null;
    }

    /**
     * Test log entry is created when backoffice admin visits the edit page for a company
     *
     * Given a backoffice admin and a company
     * When the admin visits the edit company page in the backoffice for that company
     * Then a log entry containing the action and the id of the company is present in the database
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @return void
     * @throws LocalizedException
     */
    public function testEditCompanyActionLogging(): void
    {
        $company = $this->getTestFixture();
        $this->dispatch('backend/company/index/edit/id/' . $company->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('company_index_edit', $event->getFullaction());
        $this->assertEquals('edit', $event->getAction());
        $this->assertEventData($event);
        $this->assertStringContainsString(sprintf('"general":"%s"', $company->getId()), $event->getInfo());
    }

    /**
     * Test log entry is created when backoffice admin edits a company
     *
     * Given a backoffice admin and a company
     * When the admin visits the company page in the backoffice for that company
     * And updates the information for that company
     * Then a log entry containing the action and the id of the company is present in the database
     * And the original company data and changed company data is present as a logged event change in the database
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @return void
     * @throws LocalizedException
     */
    public function testSaveCompanyActionLogging(): void
    {
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);

        $company = $this->getTestFixture();

        $companyAdmin = $customerRepository->getById($company->getSuperUserId());

        $companyNameBeforeChange = $company->getCompanyName();
        $requestData = [
            'id' => $company->getId(),
            'general' => [
                'company_name' => 'Company Name changed',
            ],
            'company_admin' => [
                'email' => $companyAdmin->getEmail(),
                'website_id' => $companyAdmin->getWebsiteId(),
                'firstname' => $companyAdmin->getFirstname(),
                'lastname' => $companyAdmin->getLastname(),
            ]

        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->dispatch('backend/company/index/save');

        $event = $this->provideLatestEvent();

        $this->assertEquals('company_index_save', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertEventData($event);
        $this->assertStringContainsString(sprintf('"general":"%s"', $company->getId()), $event->getInfo());

        $changes = $this->eventChangesCollectionFactory->create()
            ->addFieldToFilter('event_id', $event->getId())
            ->getItems();

        foreach ($changes as $change) {
            $this->assertStringContainsString($companyNameBeforeChange, $change->getOriginalData());
            $this->assertStringContainsString($requestData['general']['company_name'], $change->getResultData());
        }
    }

    /**
     * Test log entry is created when backoffice admin deletes a company
     *
     * Given a backoffice admin and a company
     * When the admin visits the company page in the backoffice for that company
     * And deletes the company
     * Then a log entry containing the action and the id of the company is present in the database
     * And the original company data and a '__was_deleted' flag is present as a logged event change in the database
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @return void
     * @throws LocalizedException
     */
    public function testDeleteCompanyActionLogging(): void
    {
        $company = $this->getTestFixture();
        $companyId = $company->getId();
        $companyName = $company->getCompanyName();

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/company/index/delete/id/' . $companyId);

        $event = $this->provideLatestEvent();

        $this->assertEquals('company_index_delete', $event->getFullaction());
        $this->assertEquals('delete', $event->getAction());
        $this->assertEventData($event);

        $changes = $this->eventChangesCollectionFactory->create()
            ->addFieldToFilter('event_id', $event->getId())
            ->getItems();

        foreach ($changes as $change) {
            $this->assertStringContainsString(sprintf('"entity_id":"%s"', $companyId), $change->getOriginalData());
            $this->assertStringContainsString(sprintf('"company_name":"%s"', $companyName), $change->getOriginalData());
            $this->assertStringContainsString('__was_deleted', $change->getResultData());
        }
    }

    /**
     * Test log entry is created when backoffice admin blocks multiple companies via a mass delete bulk action
     *
     * Given a backoffice admin and a company
     * When the admin deletes the company via a mass deletion bulk action
     * Then a log entry containing the action and the id of the company is present in the database
     * And the original company data and a '__was_deleted' flag is present as a logged event change in the database
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @return void
     * @throws LocalizedException
     */
    public function testMassDeleteCompanyActionLogging(): void
    {
        /** @var CompanyRepositoryInterface $companyRepository */
        $companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
        $companies = $companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $companiesNames = [];
        foreach ($companies as $company) {
            $companiesNames[$company->getId()] = $company->getCompanyName();
        }

        $requestData = [
            'selected' => array_keys($companiesNames),
            'namespace' => 'company_listing',
            'filters' => ['placeholder' => true],
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/company/index/massDelete');

        $event = $this->provideLatestEvent();

        $this->assertEquals('company_index_massDelete', $event->getFullaction());
        $this->assertEquals('massDelete', $event->getAction());
        $this->assertStringContainsString(
            sprintf('"general":"%s"', implode(', ', array_keys($companiesNames))),
            $event->getInfo()
        );
        $this->assertEventData($event);
    }

    /**
     * Test log entry is created when backoffice admin blocks multiple companies via a mass block bulk action
     *
     * Given a backoffice admin and a company
     * When the admin blocks the company via a mass block bulk action
     * Then a log entry containing the action and the id of the company is present in the database
     * And the original company status and updated status is present as a logged event change in the database
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @return void
     * @throws LocalizedException
     */
    public function testMassBlockCompanyActionLogging(): void
    {
        /** @var CompanyRepositoryInterface $companyRepository */
        $companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
        $companies = $companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $companiesNames = [];
        foreach ($companies as $company) {
            $companiesNames[$company->getId()] = $company->getCompanyName();
        }

        $requestData = [
            'selected' => array_keys($companiesNames),
            'namespace' => 'company_listing',
            'filters' => ['placeholder' => true],
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/company/index/massBlock');

        $event = $this->provideLatestEvent();

        $this->assertEquals('company_index_massBlock', $event->getFullaction());
        $this->assertEquals('massUpdate', $event->getAction());
        $this->assertStringContainsString(
            sprintf('"general":"%s"', implode(', ', array_keys($companiesNames))),
            $event->getInfo()
        );
        $this->assertEventData($event);
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
     */
    private function assertEventData(Event $event): void
    {
        $this->assertEquals('success', $event->getStatus());
        $this->assertIsNumeric($event->getUserId());
        $this->assertEquals($this->_getAdminCredentials()['user'], $event->getUser());
        $this->assertStringContainsString(date('Y-m-d H:i'), $event->getTime());
    }

    /**
     * Gets Shared Catalog Fixture.
     *
     * @return CompanyInterface
     * @throws LocalizedException
     */
    private function getTestFixture(): CompanyInterface
    {
        /** @var CompanyRepositoryInterface $companyRepository */
        $companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);

        $companies = $companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        return array_shift($companies);
    }
}
