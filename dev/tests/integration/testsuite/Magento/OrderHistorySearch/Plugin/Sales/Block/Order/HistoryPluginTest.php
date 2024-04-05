<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\OrderHistorySearch\Plugin\Sales\Block\Order;

use Magento\Company\Api\CompanyManagementInterface;
use Magento\Company\Api\Data\CompanyCustomerInterfaceFactory;
use Magento\Company\Api\Data\StructureInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\StructureRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Order\History;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Test for app/code/Magento/OrderHistorySearch/Plugin/Sales/Block/Order/HistoryPlugin class.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class HistoryPluginTest extends AbstractController
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var History
     */
    private $orderHistory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var CompanyCustomerInterfaceFactory
     */
    private $companyCustomerAttributesFactory;

    /**
     * @var StructureManager
     */
    private $structureManager;

    /**
     * @var StructureRepository
     */
    private $structureRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->session = $this->objectManager->get(Session::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->orderHistory = $this->objectManager->get(History::class);
        $this->orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $this->orderManagement = $this->objectManager->get(OrderManagementInterface::class);
        $this->companyManagement = $this->objectManager->get(CompanyManagementInterface::class);
        $this->companyCustomerAttributesFactory = $this->objectManager->get(CompanyCustomerInterfaceFactory::class);
        $this->structureManager = $this->objectManager->get(StructureManager::class);
        $this->structureRepository = $this->objectManager->get(structureRepository::class);

        $this->setCompanyActiveStatus(true);

        parent::setUp();
    }

    /**
     * Test that a customer who places a non-company order, joins a company and places an order in that company can see
     * both orders in My Orders grid on storefront
     *
     * - Create a user and login as them
     * - Create an order for that user
     * - Assign user to a company
     * - Create another order for user while they’re a part of that company
     * - Assert that both orders appear when calling \Magento\Sales\Block\Order\History::getOrders
     *
     * Given a regular storefront customer and a company
     * When the customer places an order
     * And afterwards is assigned to the company
     * And places another order while a part of that company
     * Then both orders are present in the My Orders grid in the storefront
     * And both order details pages are accessible to the customer
     *
     * @magentoDataFixture Magento/OrderHistorySearch/_files/customers_with_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testMyOrdersGridShowsNonCompanyOrderAndCompanyOrderForCustomerAfterCustomerJoinsCompany()
    {
        // get orders belonging to customer 1
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter(
            OrderInterface::INCREMENT_ID,
            ['100000001', '100000011'],
            'in'
        )->create();

        list($noCompanyOrder, $withCompanyOrder) = array_values(
            $this->orderRepository->getList($searchCriteria)->getItems()
        );

        // login as customer
        $customer = $this->customerRepository->get('customer1@example.com');
        $this->loginAsCustomer($customer);

        $this->orderManagement->place($noCompanyOrder);

        // assign customer to company
        $companyCustomerAttributes = $this->companyCustomerAttributesFactory->create();
        $customer->getExtensionAttributes()->setCompanyAttributes($companyCustomerAttributes);
        $this->customerRepository->save($customer);

        $companyAdmin = $this->customerRepository->get('company-admin@example.com');
        $companyId = $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

        $this->companyManagement->assignCustomer(
            $companyId,
            $customer->getId()
        );

        // get structure id for the company admin
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)
            ->addFilter('entity_id', $companyAdmin->getId(), 'eq')
            ->addFilter('entity_type', StructureInterface::TYPE_CUSTOMER, 'eq')
            ->create();

        $structureEntries = $this->structureRepository->getList($searchCriteria)->getItems();
        $structureId = array_shift($structureEntries)->getStructureId();

        // assign customer as subordinate of admin using company admin's structure id
        $this->structureManager->addNode($customer->getId(), StructureInterface::TYPE_CUSTOMER, $structureId);

        // place order while being associated with a company
        $this->orderManagement->place($withCompanyOrder);

        $currentOrders = [];
        $orderHistoryData  = $this->orderHistory->getOrders();
        foreach ($orderHistoryData as $order) {
            $currentOrders[ $order->getIncrementId() ] = $order->getIncrementId();
        }

        $expectedOrders = [
            $noCompanyOrder->getIncrementId() => $noCompanyOrder->getIncrementId(),
            $withCompanyOrder->getIncrementId() => $withCompanyOrder->getIncrementId()
        ];

        $this->assertEquals($expectedOrders, $currentOrders);

        $this->dispatch('sales/order/view/order_id/' . $noCompanyOrder->getEntityId() . '/');
        $response = $this->getResponse();
        $this->assertStringContainsString('Order # ' . $noCompanyOrder->getIncrementId(), $response->getBody());

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        $this->resetRequest();
        $this->resetResponse();

        $this->dispatch('sales/order/view/order_id/' . $withCompanyOrder->getEntityId() . '/');
        $response = $this->getResponse();
        $this->assertStringContainsString('Order # ' . $withCompanyOrder->getIncrementId(), $response->getBody());
    }

    /**
     * Test that a company admin can only see and access company orders for a subordinate that placed orders before and
     * after joining a company
     *
     * - Create a company with a company admin
     * - Create a user (no company) and login as them
     * - Create an order for that user
     * - Assign user to the company created in step 1
     * - Create another order for user while they’re a part of that company
     * - Log out of user and log in as company admin
     * - Assert that only user’s company order appears when calling \Magento\Sales\Block\Order\History::getOrders
     *
     * Given a regular storefront customer and a company admin
     * When the customer places an order
     * And afterwards is assigned to the company
     * And places another order while a part of that company
     * And the company admin logs into the storefront
     * Then the admin only sees the order the customer placed while in the company in My Orders grid on storefront
     * And the admin can access the order detail page for the order the customer placed while in the company
     * And the admin cannot access the order detail page for the order the customer placed while not in the company
     *
     * @magentoDataFixture Magento/OrderHistorySearch/_files/customers_with_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testMyOrdersGridShowsOnlyCompanyOrderForCompanyAdminAfterCustomerJoinsCompany()
    {
        // get orders belonging to customer 1
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter(
            OrderInterface::INCREMENT_ID,
            ['100000001', '100000011'],
            'in'
        )->create();

        list($noCompanyOrder, $withCompanyOrder) = array_values(
            $this->orderRepository->getList($searchCriteria)->getItems()
        );

        $customer = $this->customerRepository->get('customer1@example.com');
        $this->loginAsCustomer($customer);

        $this->orderManagement->place($noCompanyOrder);

        // assign customer to company
        $companyCustomerAttributes = $this->companyCustomerAttributesFactory->create();
        $customer->getExtensionAttributes()->setCompanyAttributes($companyCustomerAttributes);
        $this->customerRepository->save($customer);

        $companyAdmin = $this->customerRepository->get('company-admin@example.com');
        $companyId = $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

        $this->companyManagement->assignCustomer(
            $companyId,
            $customer->getId()
        );

        // get structure id for the company admin
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)
            ->addFilter('entity_id', $companyAdmin->getId(), 'eq')
            ->addFilter('entity_type', StructureInterface::TYPE_CUSTOMER, 'eq')
            ->create();

        $structureEntries = $this->structureRepository->getList($searchCriteria)->getItems();
        $structureId = array_shift($structureEntries)->getStructureId();

        // assign customer as subordinate of admin using company admin's structure id
        $this->structureManager->addNode($customer->getId(), StructureInterface::TYPE_CUSTOMER, $structureId);

        // place order while being associated with a company
        $this->orderManagement->place($withCompanyOrder);

        $expectedOrders = [
            $withCompanyOrder->getIncrementId()
        ];

        $this->session->logout();

        $this->loginAsCustomer($companyAdmin);

        $currentOrders = [];
        $ordersHistoryFromAdmin = $this->orderHistory->getOrders();

        foreach ($ordersHistoryFromAdmin as $order) {
            $currentOrders[] = $order->getIncrementId();
        }

        $this->assertEquals($expectedOrders, $currentOrders);

        $this->dispatch('sales/order/view/order_id/' . $withCompanyOrder->getEntityId() . '/');
        $response = $this->getResponse();
        $this->assertStringContainsString('Order # ' . $withCompanyOrder->getIncrementId(), $response->getBody());

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        $this->resetRequest();
        $this->resetResponse();

        $this->dispatch('sales/order/view/order_id/' . $noCompanyOrder->getEntityId() . '/');
        $response = $this->getResponse();
        $this->assertEquals(302, $response->getHttpResponseCode());
        $this->assertStringNotContainsString('Order # ' . $noCompanyOrder->getIncrementId(), $response->getBody());
    }

    /**
     * Test that a customer who places a non-company order, joins company A and places an order in that company and then
     * moves to company B can only see their non-company order in My Orders grid on storefront. Test that they can only
     * access this order as well
     *
     * - Create two companies (A, B)
     * - Create a user (no company) and login as them
     * - Create an order for that user
     * - Assign user to the company A created in step 1
     * - Create another order for user while they’re a part of that company
     * - Assign user to company B
     * - Assert that none of user’s company A orders appear when calling \Magento\Sales\Block\Order\History::getOrders
     * - Assert that user’s personal order is still present when calling \Magento\Sales\Block\Order\History::getOrders
     *
     * Given a regular storefront customer and two companies A and B
     * When the customer places an order
     * And afterwards is assigned to company A
     * And places another order while a part of that company
     * And afterwards is assigned to company B
     * Then only the order the customer placed while not part of any company appears in My Orders grid on storefront
     * And the customer can access the order detail page for the order they placed while not part of any company
     * And the customer cannot access the order detail page for the order they placed while in company A
     *
     * @magentoDataFixture Magento/OrderHistorySearch/_files/customers_with_orders.php
     * @magentoDataFixture Magento/OrderHistorySearch/_files/companies_with_admin.php
     */
    public function testMyOrdersGridShowsOnlyNonCompanyOrderForCustomerAfterCustomerJoinsASecondCompany()
    {
        // get orders belonging to customer 1
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter(
            OrderInterface::INCREMENT_ID,
            ['100000001', '100000011'],
            'in'
        )->create();

        list($noCompanyOrder, $withCompanyOneOrder) = array_values(
            $this->orderRepository->getList($searchCriteria)->getItems()
        );

        // login as customer
        $customer = $this->customerRepository->get('customer1@example.com');
        $this->loginAsCustomer($customer);

        $this->orderManagement->place($noCompanyOrder);

        // assign customer to company 1
        $companyCustomerAttributes = $this->companyCustomerAttributesFactory->create();
        $customer->getExtensionAttributes()->setCompanyAttributes($companyCustomerAttributes);
        $this->customerRepository->save($customer);

        $companyAdmin = $this->customerRepository->get('company-adminone@example.com');
        $companyId = $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

        $this->companyManagement->assignCustomer(
            $companyId,
            $customer->getId()
        );

        // get structure id for the company 1 admin
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)
            ->addFilter('entity_id', $companyAdmin->getId(), 'eq')
            ->addFilter('entity_type', StructureInterface::TYPE_CUSTOMER, 'eq')
            ->create();

        $structureEntries = $this->structureRepository->getList($searchCriteria)->getItems();
        $structureId = array_shift($structureEntries)->getStructureId();

        // assign customer as subordinate of admin using company 1 admin's structure id
        $this->structureManager->addNode($customer->getId(), StructureInterface::TYPE_CUSTOMER, $structureId);

        // place order while being associated with company 1
        $this->orderManagement->place($withCompanyOneOrder);

        // assign customer to company 2
        $companyCustomerAttributes = $this->companyCustomerAttributesFactory->create();
        $customer->getExtensionAttributes()->setCompanyAttributes($companyCustomerAttributes);
        $this->customerRepository->save($customer);

        $companyAdmin = $this->customerRepository->get('company-admintwo@example.com');
        $companyId = $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

        $this->companyManagement->assignCustomer(
            $companyId,
            $customer->getId()
        );

        // get structure id for the company 2 admin
        $searchCriteria = $this->objectManager->get(SearchCriteriaBuilder::class)
            ->addFilter('entity_id', $companyAdmin->getId(), 'eq')
            ->addFilter('entity_type', StructureInterface::TYPE_CUSTOMER, 'eq')
            ->create();

        $structureEntries = $this->structureRepository->getList($searchCriteria)->getItems();
        $structureId = array_shift($structureEntries)->getStructureId();

        // assign customer as subordinate of admin using company 2 admin's structure id
        $this->structureManager->addNode($customer->getId(), StructureInterface::TYPE_CUSTOMER, $structureId);

        $ordersFromCompanyTwo = [];
        $orderHistoryData  = $this->orderHistory->getOrders();
        foreach ($orderHistoryData as $order) {
            $ordersFromCompanyTwo[] = $order->getIncrementId();
        }

        // assert customer can only view their $noCompanyOrder, and not their order placed in company 1
        $this->assertNotContains($withCompanyOneOrder->getIncrementId(), $ordersFromCompanyTwo);
        $this->assertContains($noCompanyOrder->getIncrementId(), $ordersFromCompanyTwo);

        $this->dispatch('sales/order/view/order_id/' . $noCompanyOrder->getEntityId());
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertStringContainsString('Order # ' . $noCompanyOrder->getIncrementId(), $response->getBody());

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        $this->resetRequest();
        $this->resetResponse();

        $this->dispatch('sales/order/view/order_id/' . $withCompanyOneOrder->getEntityId());
        $response = $this->getResponse();
        $this->assertEquals(302, $response->getHttpResponseCode());
        $this->assertStringNotContainsString('Order # ' . $withCompanyOneOrder->getIncrementId(), $response->getBody());
    }

    /**
     * Login as a customer.
     *
     * @param CustomerInterface $customer
     * @return void
     */
    private function loginAsCustomer(CustomerInterface $customer)
    {
        $this->session->setCustomerDataAsLoggedIn($customer);
    }

    /**
     * Set company active status.
     *
     * magentoConfigFixture does not support changing the value for website scope.
     *
     * @param bool $isActive
     */
    private function setCompanyActiveStatus($isActive)
    {
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            'btob/website_configuration/company_active',
            $isActive ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Reset shared response object instance and set property to null (to be reinitialized after subsequent request)
     */
    private function resetResponse()
    {
        $this->_objectManager->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }
}
