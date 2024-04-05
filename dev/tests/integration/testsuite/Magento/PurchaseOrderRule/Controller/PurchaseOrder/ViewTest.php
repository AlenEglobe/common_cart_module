<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\PurchaseOrder;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Model\RoleRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Controller test class for view purchase orders with applied rules
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ViewTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorder/purchaseorder/view';

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
        $scopeConfig->setValue('btob/website_configuration/purchaseorder_enabled', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     */
    public function testViewForSingleRule()
    {
        //Enable PO for company
        $this->enablePOForCompany('company@example.com');
        // Log in as the approver
        $levelOneCustomer = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($levelOneCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Purchase Order #', $body);
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     */
    public function testAccessDeniedForSingleRule()
    {
        //Enable PO for company
        $this->enablePOForCompany('company@example.com');
        // Log in as not approver
        $levelOneCustomer = $this->customerRepository->get('alex.smith@example.com');
        $this->session->loginById($levelOneCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $response = $this->getResponse();
        $this->assertEquals(302, $response->getHttpResponseCode());
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testViewForMultipleRulesAdminApproval()
    {
        //Enable PO for company
        $this->enablePOForCompany('company@example.com');
        // Log in as the company admin
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Purchase Order #', $body);
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_manager_approver.php
     */
    public function testViewForSingleRuleManagerApproval()
    {
        //Enable PO for company
        $this->enablePOForCompany('company@example.com');
        // Log in as the manager
        $companyAdmin = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Purchase Order #', $body);
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Exception
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Enable PO for company.
     *
     * @param $email
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function enablePOForCompany($email)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('company_email', $email)->create();
        $companies = $this->companyRepository->getList($searchCriteria)->getItems();
        $company =  array_pop($companies);
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);
    }
}
