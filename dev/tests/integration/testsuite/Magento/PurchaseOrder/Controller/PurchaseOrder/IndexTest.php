<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Framework\App\ResponseInterface;

/**
 * Controller test class for the purchase order grid.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Index
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
 */
class IndexTest extends AbstractController
{
    const URI = 'purchaseorder/purchaseorder/index';

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CompanyConfigRepositoryInterface
     */
    private $companyConfigRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->companyConfigRepository = $objectManager->get(CompanyConfigRepositoryInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);

        // Enable company functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);

        // Grant the "Default User" role with permission to the purchase order grouping resource.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
            PermissionInterface::DENY_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::DENY_PERMISSION
        );
    }

    /**
     * Enable/Disable the configuration for the website scope.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    private function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Enable/Disable purchase order functionality on a per company basis.
     *
     * @param string $companyName
     * @param bool $isEnabled
     * @throws LocalizedException
     */
    private function setCompanyPurchaseOrderConfig(string $companyName, bool $isEnabled)
    {
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        $companyConfig = $this->companyConfigRepository->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $this->companyConfigRepository->save($companyConfig);
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

    /**
     * Test that a company user has the proper access to the purchase order grid.
     *
     * This is based on various configuration and permission settings.
     *
     * @dataProvider indexActionAsCompanyUserDataProvider
     * @param string $companyUserEmail
     * @param int $companyPurchaseOrdersConfigEnabled
     * @param string $purchaseOrdersPermissionValue
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testIndexActionAsCompanyUser(
        $companyUserEmail,
        $companyPurchaseOrdersConfigEnabled,
        $purchaseOrdersPermissionValue,
        $expectedHttpResponseCode,
        $expectedRedirect
    ) {
        // Enable/Disable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', (bool) $companyPurchaseOrdersConfigEnabled);

        // Grant/Revoke the view purchase orders permission to the company "Default User" role.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            Index::COMPANY_RESOURCE,
            $purchaseOrdersPermissionValue
        );

        // Log-in as the company user
        $companyUser = $this->customerRepository->get($companyUserEmail);
        $this->session->loginById($companyUser->getId());

        // Dispatch the request to view the purchase orders grid
        $this->dispatch(self::URI);

        // Perform assertions
        $this->assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());

        if ($expectedRedirect) {
            $this->assertRedirect($this->stringContains($expectedRedirect));
        }

        $this->session->logout();
    }

    /**
     * Test that a user who is not affiliated with a company is redirected to a 'noroute' page.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testIndexActionAsNonCompanyUser()
    {
        $nonCompanyUser= $this->customerRepository->get('customer@example.com');

        $this->session->loginById($nonCompanyUser->getId());

        $this->dispatch(self::URI);
        $this->assertRedirect($this->stringContains('noroute'));

        $this->session->logout();
    }

    /**
     * Test that a guest user is redirected to the login page.
     */
    public function testIndexActionAsGuestUser()
    {
        $this->dispatch(self::URI);
        $this->assertRedirect($this->stringContains('customer/account/login'));
    }

    /**
     * Test My Purchase Orders are loaded when sorting field is passed
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testMyPurchaseOrdersGridColumnSortOrder()
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $customerId = $customer->getId();
        $this->session->loginById($customerId);
        $this->assertGridColumnsSortOrder(
            'my_purchaseorder_listing',
            '"totalRecords":1'
        );
        $this->session->logout();
    }

    /**
     * Test that Company Purchase Orders are loaded when the sorting field is passed
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testCompanyPurchaseOrdersGridColumnSortOrder()
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $customerId = $customer->getId();
        $this->session->loginById($customerId);
        $this->assertGridColumnsSortOrder(
            'company_purchaseorder_listing',
            '"totalRecords":3'
        );
        $this->session->logout();
    }

    /**
     * Test that My Purchase Orders > Requires My Approval are loaded
     * when the sorting field is passed
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_admin_approver.php
     */
    public function testMyPurchaseOrdersRequiresMyApprovalGridColumnSortOrder()
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $customerId = $customer->getId();
        $this->session->loginById($customerId);
        $this->assertGridColumnsSortOrder(
            'require_my_approval_purchaseorder_listing',
            '"totalRecords":1'
        );
        $this->session->logout();
    }

    /**
     * Data provider for various index action scenarios for company users.
     *
     * @return array
     */
    public function indexActionAsCompanyUserDataProvider()
    {
        return [
            'view_purchase_order_grid' => [
                'company_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'purchase_order_permission_value' => PermissionInterface::ALLOW_PERMISSION,
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'view_purchase_order_grid_without_permission' => [
                'company_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'purchase_order_permission_value' => PermissionInterface::DENY_PERMISSION,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'view_purchase_order_grid_without_company_purchase_orders_enabled' => [
                'company_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 0,
                'purchase_order_permission_value' => PermissionInterface::ALLOW_PERMISSION,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ]
        ];
    }

    /**
     * Assert the grid component are loaded when the sorting field is passed.
     *
     * @param string $namespace
     * @param string $assertString
     */
    private function assertGridColumnsSortOrder($namespace, $assertString)
    {
        $columns = [
            'increment_id',
            'order_increment_id',
            'created_at',
            'updated_at',
            'creator_name',
            'status',
            'grand_total'
        ];
        foreach ($columns as $column) {
            $params = [
                'namespace' => $namespace,
                'sorting[field]' => $column,
                'sorting[direction]' => 'asc',
                'paging[pageSize]' => '20',
                'paging[current]' => '1'
            ];
            $this->getRequest()->setParams($params);
            $this->dispatch('mui/index/render?' . http_build_query($params));
            $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
            $this->assertStringContainsString($assertString, $this->getResponse()->getBody());
            Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
            $this->resetRequest();
            $this->resetResponse();
        }
    }

    /**
     * Reset dispatch response
     *
     */
    private function resetResponse()
    {
        Bootstrap::getObjectManager()->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }
}
