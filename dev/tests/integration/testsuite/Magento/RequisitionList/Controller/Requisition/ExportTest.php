<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\RequisitionList\Controller\Requisition;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Export RequisitionList Controller Test
 *
 * @see \Magento\RequisitionList\Controller\Requisition\Export
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class ExportTest extends AbstractController
{
    /**
     * @var string
     */
    private $exportUrlTemplate = '/requisition_list/requisition/export/requisition_id/%s/';

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customerSession = $this->_objectManager->create(CustomerSession::class);
        $this->accountManagement = $this->_objectManager->create(AccountManagementInterface::class);
        $this->storeManager = $this->_objectManager->create(StoreManagerInterface::class);
        $this->setRequisitionListActiveStatus(true);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->setRequisitionListActiveStatus(false);
        $this->customerSession->logout();
        parent::tearDown();
    }

    /**
     * Test export only exports the requisition list items belonging to requested requisition list
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/RequisitionList/_files/list_items_for_search.php
     * @magentoDataFixture Magento/RequisitionList/_files/list_items_for_list_two.php
     */
    public function testAuthorizedExport()
    {
        $this->loginAsCustomer('customer@example.com', 'password');

        $requisitionList = $this->getRequisitionList('list two');

        ob_start();
        $this->dispatch(
            sprintf(
                $this->exportUrlTemplate,
                $requisitionList->getId()
            )
        );
        $body = ob_get_clean();

        $response = $this->getResponse();

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        $this->assertEquals(
            'text/csv',
            $response->getHeader('Content-Type')->getFieldValue()
        );

        $this->assertEquals(
            'attachment; filename="list two.csv"',
            $response->getHeader('Content-Disposition')->getFieldValue()
        );

        $expectedCsv = file_get_contents(__DIR__ . '/../../_files/list_two.csv');
        $this->assertEquals($expectedCsv, $body);
    }

    /**
     * Test exporting non-existent requisition list redirects back to the referrer (store base url in our case)
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/RequisitionList/_files/list.php
     */
    public function testExportOfNonExistentList()
    {
        $this->loginAsCustomer('customer@example.com', 'password');

        $this->dispatch(
            sprintf(
                $this->exportUrlTemplate,
                -1
            )
        );

        $this->assertRedirect($this->equalTo($this->storeManager->getStore()->getBaseUrl()));
    }

    /**
     * Test customer is not allowed to export list that does not belong to them and redirects back to the referrer
     *
     * @magentoDataFixture Magento/Customer/_files/two_customers.php
     * @magentoDataFixture Magento/RequisitionList/_files/list.php
     */
    public function testUnauthorizedExport()
    {
        $this->loginAsCustomer('customer_two@example.com', 'password');

        $this->dispatch(
            sprintf(
                $this->exportUrlTemplate,
                $this->getRequisitionList('list name')->getId()
            )
        );

        $this->assertRedirect($this->equalTo($this->storeManager->getStore()->getBaseUrl()));
    }

    /**
     * Set requisition list active status; magentoConfigFixture does not allow changing the value for website scope
     *
     * @param bool $isActive
     */
    private function setRequisitionListActiveStatus($isActive)
    {
        $this->_objectManager->get(
            MutableScopeConfigInterface::class
        )->setValue(
            'btob/website_configuration/requisition_list_active',
            $isActive ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Start customer session
     *
     * @param string $email
     * @param string $password
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function loginAsCustomer($email, $password)
    {
        $customer = $this->accountManagement->authenticate($email, $password);
        $this->customerSession->loginById($customer->getId());
    }

    /**
     * Get Requisition List by name
     *
     * @param string $listName
     * @return RequisitionListInterface
     */
    private function getRequisitionList($listName)
    {
        $requisitionListRepository = $this->_objectManager->get(RequisitionListRepositoryInterface::class);

        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->_objectManager->create(FilterBuilder::class);
        $filter = $filterBuilder->setField(RequisitionListInterface::NAME)->setValue($listName)->create();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter]);
        $list = $requisitionListRepository->getList($searchCriteriaBuilder->create())->getItems();

        return array_pop($list);
    }
}
