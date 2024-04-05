<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyCredit;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\CompanyCredit\Api\CreditLimitRepositoryInterface;
use Magento\CompanyCredit\Controller\Adminhtml\Index\Reimburse;
use Magento\CompanyCredit\Model\HistoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Logging\Model\Event;
use Magento\Logging\Model\ResourceModel\Event\Collection;
use Magento\Logging\Model\ResourceModel\Event\CollectionFactory;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Test company credit changes email notifications.
 *
 * @magentoConfigFixture default/payment/companycredit/active 1
 * @magentoConfigFixture default_store carriers/freeshipping/active 1
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AdminActionsLoggingTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $uri = 'backend/credit/index/reimburse';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @inheritDoc
     */
    protected $resource = Reimburse::ADMIN_RESOURCE;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CollectionFactory
     */
    private $eventCollectionFactory;

    /**
     * @var HistoryRepositoryInterface
     */
    private $creditHistoryRepository;

    /**
     * @var CreditLimitRepositoryInterface
     */
    private $companyCreditRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->companyRepository = $this->_objectManager->get(CompanyRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $this->eventCollectionFactory = $this->_objectManager->get(CollectionFactory::class);
        $this->creditHistoryRepository = $this->_objectManager->get(HistoryRepositoryInterface::class);
        $this->companyCreditRepository = $this->_objectManager->get(CreditLimitRepositoryInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->companyRepository = null;
        $this->searchCriteriaBuilder = null;
        $this->eventCollectionFactory = null;
        $this->creditHistoryRepository = null;
    }

    /**
     * Tests logging entry after executing Reimburse Balance action
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testCompanyCreditReimburseBalanceActionLogging(): void
    {
        $companies = $this->companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $company = array_shift($companies);

        $reimburseData = $this->triggerReimburse((int)$company->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('credit_index_reimburse', $event->getFullaction());
        $this->assertEquals('save', $event->getAction());
        $this->assertStringContainsString('Company id: ' . $company->getId(), $event->getInfo());
        $this->assertStringContainsString('(' . $company->getCompanyName() . ')', $event->getInfo());
        $this->assertEventData($event, $reimburseData);
    }

    /**
     * Tests logging entry after executing Edit Credit action
     *
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testCompanyCreditEditActionLogging(): void
    {
        $companies = $this->companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $company = array_shift($companies);

        $this->triggerReimburse((int)$company->getId());

        $companyCredits = $this->companyCreditRepository->getList(
            $this->searchCriteriaBuilder->addFilter('company_id', $company->getId())->create()
        )->getItems();
        $companyCredit = array_shift($companyCredits);

        $companyCreditHistory = $this->creditHistoryRepository->getList(
            $this->searchCriteriaBuilder->addFilter('company_credit_id', $companyCredit->getEntityId())->create()
        )->getItems();
        $companyCreditHistoryEntry = array_shift($companyCreditHistory);

        $reimburseData = [
            'purchase_order' => '111222',
            'credit_comment' => 'Other test comment',
        ];

        $this->_getBootstrap()->reinitialize();
        $this->resetRequest();
        $this->getRequest()->setPostValue(['reimburse_balance' => $reimburseData]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/credit/index/edit/history_id/' . $companyCreditHistoryEntry->getId());

        $event = $this->provideLatestEvent();

        $this->assertEquals('credit_index_edit', $event->getFullaction());
        $this->assertEquals('view', $event->getAction());
        $this->assertStringContainsString('Company id: ' . $company->getId(), $event->getInfo());
        $this->assertStringContainsString('(' . $company->getCompanyName() . ')', $event->getInfo());
        $this->assertEventData($event, $reimburseData);
    }

    /**
     * Tests logging entry after executing Mass Convert action
     *
     * @magentoDataFixture Magento/CompanyCredit/_files/companies_with_credit_limit.php
     */
    public function testCompanyCreditMassConvertActionLogging(): void
    {
        $companies = $this->companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $companiesNames = [];
        foreach ($companies as $company) {
            $companiesNames[$company->getId()] = $company->getCompanyName();
        }

        $requestData = [
            'selected' => array_keys($companiesNames),
            'excluded' => 'false',
            'currency_rates' => ['USD' => '1.2'],
            'currency_to' => 'USD',
            'namespace' => 'company_listing',
            'filters' => ['placeholder' => true],
            'search' => '',
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/credit/index/massConvert');

        $event = $this->provideLatestEvent();

        $this->assertEquals('credit_index_massConvert', $event->getFullaction());
        $this->assertEquals('massUpdate', $event->getAction());
        $this->assertStringContainsString('Affected companies:', $event->getInfo());
        foreach ($companiesNames as $companyId => $companyName) {
            $this->assertStringContainsString(sprintf('%s (%s)', $companyId, $companyName), $event->getInfo());
        }

        $dataToCheck = [
            'Currency to' => 'USD',
            'Rates' => '1.2',
        ];

        $this->assertEventData($event, $dataToCheck);
    }

    /**
     * Tests logging entry after executing Mass Convert action but providing bad data
     *
     * @magentoDataFixture Magento/CompanyCredit/_files/companies_with_credit_limit.php
     */
    public function testCompanyCreditMassConvertActionWithBadDataLogging(): void
    {
        $companies = $this->companyRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $companiesIds = [];
        foreach ($companies as $company) {
            $companiesIds[] = $company->getId();
        }

        $requestData = [
            'selected' => $companiesIds,
            'excluded' => 'false',
            'currency_rates' => [],
            'namespace' => 'company_listing',
            'filters' => ['placeholder' => true],
            'search' => '',
        ];

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/credit/index/massConvert');

        $event = $this->provideLatestEvent();

        $this->assertStringContainsString('Missing parameters to complete action request', $event->getInfo());
    }

    /**
     * Runs reimburse credit action
     *
     * @param int $companyId
     * @param array $reimburseData
     * @return array
     */
    private function triggerReimburse(int $companyId, array $reimburseData = []): array
    {
        if (!$reimburseData) {
            $reimburseData = [
                'amount' => '10',
                'purchase_order' => '122113',
                'credit_comment' => 'Test',
            ];
        }

        $this->getRequest()->setPostValue(['reimburse_balance' => $reimburseData]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/credit/index/reimburse/id/' . $companyId);

        return $reimburseData;
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
        $this->assertStringContainsString(date('Y-m-d H:i'), $event->getTime());
        foreach ($additionalData as $fieldName => $fieldValue) {
            $this->assertStringContainsString(sprintf('%s: %s', $fieldName, $fieldValue), $event->getInfo());
        }
    }

    /**
     * @inheritDoc
     */
    protected function resetRequest(): void
    {
        $requestInstanceClassName = get_class($this->getRequest());
        $this->_objectManager->removeSharedInstance($requestInstanceClassName);
        parent::resetRequest();
    }
}
