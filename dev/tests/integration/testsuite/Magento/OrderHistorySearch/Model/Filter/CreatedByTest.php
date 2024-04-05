<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\OrderHistorySearch\Model\Filter;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Sales\Block\Order\History as OrderHistory;
use Magento\Framework\App\RequestInterface;

/**
 * CreatedBy Filter for Order History Search Test
 *
 * @see \Magento\OrderHistorySearch\Model\Filter\CreatedBy
 *
 * @magentoDataFixture Magento/OrderHistorySearch/_files/company_with_structure_and_orders.php
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class CreatedByTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CustomerInterface[]
     */
    private $companyUsersByRole;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->session = $objectManager->get(Session::class);
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->request = $objectManager->get(RequestInterface::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);

        $this->companyUsersByRole = [
            'companyAdmin' => $customerRepository->get('john.doe@example.com'),
            'companyUserLevelOne' => $customerRepository->get('veronica.costello@example.com'),
            'companyUserLevelTwo' => $customerRepository->get('alex.smith@example.com'),
        ];

        $this->setCompanyActiveStatus(true);
    }

    /**
     * Test My Orders grid in storefront shows own and subordinate orders by default, and that Created By filter filters
     * orders based on input while preventing orders from company superiors in the hierarchy from being displayed
     *
     * Given a company with an admin, a direct subordinate A and a direct subordinate B (who is a subordinate of A)
     * And a single distinct order for every member in the company
     * When Company Admin logs in
     * Then all 3 orders are visible on the My Orders grid on the storefront
     * When the Company Admin adds a Created By Filter of Company Admin
     * Then only the order that the Company Admin placed is visible on the My Orders grid on the storefront
     * When the Company Admin adds a Created By Filter of Subordinate A
     * Then only the order Subordinate A placed is visible on the My Orders grid on the storefront
     * When the Company Admin adds a Created By Filter of Subordinate B
     * Then only the order Subordinate B placed is visible on the My Orders grid on the storefront
     * When Subordinate A logs in
     * Then only the orders Subordinate A and Subordinate B placed are visible the My Orders grid on the storefront
     * When Subordinate A adds a Created By Filter of Company Admin
     * Then no orders are visible the My Orders grid on the storefront
     * When Subordinate A adds a Created By Filter of Subordinate A
     * Then only the order Subordinate A placed is visible on the My Orders grid on the storefront
     * When Subordinate A adds a Created By Filter of Subordinate B
     * Then only the order Subordinate B placed is visible on the My Orders grid on the storefront
     * When Subordinate B logs in
     * Then only the order Subordinate B placed is visible on the My Orders grid on the storefront
     * When Subordinate B adds a Created By Filter of Company Admin
     * Then no orders are visible the My Orders grid on the storefront
     * When Subordinate B adds a Created By Filter of Subordinate A
     * Then no orders are visible the My Orders grid on the storefront
     * When Subordinate B adds a Created By Filter of Subordinate B
     * Then only the order Subordinate B placed is visible on the My Orders grid on the storefront
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testCreatedByFilterShowsOrdersAccordingToCompanyHierarchy()
    {
        // Allow the company "Default User" role to view subordinate orders
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_Sales::view_orders_sub',
            PermissionInterface::ALLOW_PERMISSION
        );

        $companyUsersByRole = $this->companyUsersByRole;

        ///////
        // Company Admin assertions
        $this->loginAsCustomer($companyUsersByRole['companyAdmin']);
        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            'all',
            [
                '100000001',
                '100000002',
                '100000003',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyAdmin']->getId(),
            [
                '100000001',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelOne']->getId(),
            [
                '100000002',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelTwo']->getId(),
            [
                '100000003',
            ]
        );

        ////////
        // Company User Level One assertions
        $this->loginAsCustomer($companyUsersByRole['companyUserLevelOne']);

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            'all',
            [
                '100000002',
                '100000003',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyAdmin']->getId(),
            []
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelOne']->getId(),
            [
                '100000002',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelTwo']->getId(),
            [
                '100000003',
            ]
        );

        ////////
        // Company User Level Two assertions
        $this->loginAsCustomer($companyUsersByRole['companyUserLevelTwo']);
        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            'all',
            [
                '100000003',
            ]
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyAdmin']->getId(),
            []
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelOne']->getId(),
            []
        );

        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $companyUsersByRole['companyUserLevelTwo']->getId(),
            [
                '100000003',
            ]
        );
    }

    /**
     * Test My Orders grid in storefront shows own by default, and that Created By filter filters orders based on input
     * while preventing orders from anyone else in the company from being displayed when "View orders of subordinate
     * users" company permission is denied
     *
     * Given a company with an admin, a direct subordinate A and a direct subordinate B (who is a subordinate of A)
     * And a single distinct order for every member in the company
     * And the Magento_Sales::view_orders_sub company permission is denied for the Default User role
     * And Subordinate A is assigned the Default User role
     * When Subordinate A logs in
     * Then only the order Subordinate A placed is visible on the My Orders grid on the storefront
     * When Subordinate A adds a Created By Filter of Subordinate A
     * Then only the order Subordinate A placed is visible on the My Orders grid on the storefront
     */
    public function testCreatedByFilterShowsOnlyOwnOrdersWhenViewOrdersOfSubordinateUsersPermissionIsDenied()
    {
        // Deny the company "Default User" role the ability to view subordinate orders
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_Sales::view_orders_sub',
            PermissionInterface::DENY_PERMISSION
        );

        $this->loginAsCustomer($this->companyUsersByRole['companyUserLevelOne']);

        // Assert that attempting to filter by "all" only includes orders for self
        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            'all',
            ['100000002']
        );

         // Assert that attempting to filter by a subordinate's order instead returns orders for self
        $this->assertCreatedByFilterShowsOnlyTheseOrderIds(
            $this->companyUsersByRole['companyUserLevelOne']->getId(),
            ['100000002']
        );
    }

    protected function tearDown(): void
    {
        $this->session->logout();
    }

    /**
     * @param string $createdByCustomerId
     * @param array $expectedOrderIds
     */
    private function assertCreatedByFilterShowsOnlyTheseOrderIds($createdByCustomerId, array $expectedOrderIds)
    {
        $this->request->setParam('advanced-filtering', '');

        if ($createdByCustomerId === 'all') {
            $this->request->setParam('created-by', '');
        } else {
            $this->request->setParam('created-by', $createdByCustomerId);
        }

        $orderHistory = Bootstrap::getObjectManager()->create(OrderHistory::class);
        $actualOrderCollection = $orderHistory->getOrders()->load();

        $actualOrderIds = array_column($actualOrderCollection->toArray()['items'], 'increment_id');

        sort($expectedOrderIds, SORT_NUMERIC);
        sort($actualOrderIds, SORT_NUMERIC);

        $this->assertEquals($expectedOrderIds, $actualOrderIds);
    }

    /**
     * Login as a customer.
     *
     * @param CustomerInterface $customer
     * @return void
     */
    private function loginAsCustomer(CustomerInterface $customer)
    {
        $this->session->loginById($customer->getId());
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
     * Sets the permission value for the specified company role.
     *
     * @param string $companyName
     * @param string $roleName
     * @param string $resourceId
     * @param string $permissionValue
     */
    private function setCompanyRolePermission(
        string $companyName,
        string $roleName,
        string $resourceId,
        string $permissionValue
    ) {
        // Get the company
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        // Get the company role
        $this->searchCriteriaBuilder->addFilter('company_id', $company->getId());
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->roleRepository->getList($searchCriteria)->getItems();

        /** @var RoleInterface $role */
        $role = reset($results);

        // For that role, find the specified permission and set it to the desired value
        /** @var PermissionInterface $permission */
        foreach ($role->getPermissions() as $permission) {
            if ($permission->getResourceId() === $resourceId) {
                $permission->setPermission($permissionValue);
                break;
            }
        }

        $this->roleRepository->save($role);
    }
}
