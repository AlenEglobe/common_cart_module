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
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderManagementInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Framework\App\ResponseInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\CatalogInventory\Model\Stock\Status as StockStatus;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;

/**
 * Controller test class for the purchase order details page.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ViewTest extends AbstractController
{
    const URI = 'purchaseorder/purchaseorder/view';

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var StockRegistryStorage
     */
    private $stockRegistryStorage;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->stockRegistryStorage = $this->objectManager->get(StockRegistryStorage::class);

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
     * Enable/Disable configuration for the website scope.
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
        $results = $this->objectManager->get(CompanyRepositoryInterface::class)->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        $companyConfig = $this->objectManager->get(CompanyConfigRepositoryInterface::class)->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $this->objectManager->get(CompanyConfigRepositoryInterface::class)->save($companyConfig);
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
        $results = $this->objectManager->get(CompanyRepositoryInterface::class)->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        // Get the company role
        $this->searchCriteriaBuilder->addFilter('company_id', $company->getId());
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->objectManager->get(RoleRepositoryInterface::class)->getList($searchCriteria)->getItems();

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

        $this->objectManager->get(RoleRepositoryInterface::class)->save($role);
    }

    /**
     * Test that a company user has the proper access to view the purchase order details page.
     *
     * This is based on various configuration/permission settings as well as the company hierarchy.
     *
     * @dataProvider viewActionAsCompanyUserDataProvider
     * @param string $currentUserEmail
     * @param string $createdByUserEmail
     * @param int $companyPurchaseOrdersConfigEnabled
     * @param string[] $viewPurchaseOrdersPermissions
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     * @param string $purchaseOrderId
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testViewActionAsCompanyUser(
        $currentUserEmail,
        $createdByUserEmail,
        $companyPurchaseOrdersConfigEnabled,
        $viewPurchaseOrdersPermissions,
        $expectedHttpResponseCode,
        $expectedRedirect,
        $purchaseOrderId = ''
    ) {
        // Enable/Disable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', (bool) $companyPurchaseOrdersConfigEnabled);

        foreach ($viewPurchaseOrdersPermissions as $viewPurchaseOrdersPermission) {
            $this->setCompanyRolePermission(
                'Magento',
                'Default User',
                $viewPurchaseOrdersPermission,
                PermissionInterface::ALLOW_PERMISSION
            );
        }

        // Log in as the current user
        $currentUser = $this->objectManager->get(CustomerRepositoryInterface::class)->get($currentUserEmail);
        $this->session->loginById($currentUser->getId());

        // Dispatch the request to the view details page for the desired purchase order
        $purchaseOrderId = $purchaseOrderId ?: $this->getPurchaseOrderForCustomer($createdByUserEmail)->getEntityId();
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrderId);

        // Perform assertions
        $this->assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());

        if ($expectedRedirect) {
            $this->assertRedirect($this->stringContains($expectedRedirect));
        }

        $this->session->logout();
    }

    /**
     * Data provider for various view action scenarios for company users.
     *
     * @return array
     */
    public function viewActionAsCompanyUserDataProvider()
    {
        return [
            'view_my_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => ['Magento_PurchaseOrder::view_purchase_orders'],
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'view_my_purchase_order_without_permission' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => [],
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'view_my_purchase_order_without_company_purchase_orders_enabled' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'veronica.costello@example.com',
                'company_purchase_order_config_is_enabled' => 0,
                'view_purchase_order_permissions' => ['Magento_PurchaseOrder::view_purchase_orders'],
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'view_subordinate_purchase_order_no_view_subordinate_permission' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => ['Magento_PurchaseOrder::view_purchase_orders'],
                'expected_http_response_code' => 302,
                'expected_redirect' => ''
            ],
            'view_subordinate_purchase_order_with_view_subordinate_permission' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_subordinates'
                ],
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'view_superior_purchase_order' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => ['Magento_PurchaseOrder::view_purchase_orders'],
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied'
            ],
            'view_superior_purchase_order_with_view_company_permission' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'john.doe@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_company'
                ],
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'view_subordinate_purchase_order_with_view_company_permission' => [
                'current_customer' => 'veronica.costello@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permissions' => [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_company'
                ],
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'company_admin_view_purchase_order' => [
                'current_customer' => 'john.doe@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permission_value' => [],
                'expected_http_response_code' => 200,
                'expected_redirect' => ''
            ],
            'company_admin_view_!existing_purchase_order' => [
                'current_customer' => 'john.doe@example.com',
                'created_by_customer' => 'alex.smith@example.com',
                'company_purchase_order_config_is_enabled' => 1,
                'view_purchase_order_permission_value' => [],
                'expected_http_response_code' => 302,
                'expected_redirect' => 'company/accessdenied',
                'purchase_order_id' => '5000'
            ]
        ];
    }

    /**
     * Test that a user who is not affiliated with a company is redirected to a 'noroute' page.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testViewActionAsNonCompanyUser()
    {
        $nonCompanyUser = $this->objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com');

        $this->session->loginById($nonCompanyUser->getId());
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertRedirect($this->stringContains('noroute'));

        $this->session->logout();
    }

    /**
     * Test that a guest user is redirected to the login page.
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testViewActionAsGuestUser()
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertRedirect($this->stringContains('customer/account/login'));
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testViewActionAsOtherCompanyAdmin()
    {
        $otherCompanyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)
            ->get('company-admin@example.com');
        $this->session->loginById($otherCompanyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('company/accessdenied'));

        $this->session->logout();
    }

    /**
     * Test the Giftcard options are showing in PO details and Order details page
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_giftcard_products.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testGiftCardProductOptionsInViewAction()
    {
        // Enable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );

        // Log in as the company admin
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's purchase order
        $purchaseOrder = $this->getPurchaseOrderForCustomer('veronica.costello@example.com');

        $giftCardOptionsToAssert = [
            'giftcard_sender_label' => 'Gift Card Sender',
            'giftcard_sender_name' => 'test sender name',
            'giftcard_sender_email' => 'sender@example.com',
            'giftcard_recipient_label' => 'Gift Card Recipient',
            'giftcard_recipient_name' => 'test recipient name',
            'giftcard_recipient_email' => 'recipient@example.com',
            'giftcard_message_label' => 'Gift Card Message',
            'giftcard_message' => 'message text'
        ];

        // Go to Purchase Order details page
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Purchase Order details page contains gift card options in response
        $responseBody = $this->getResponse()->getBody();
        foreach ($giftCardOptionsToAssert as $giftCardOptionToAssert) {
            $this->assertStringContainsString($giftCardOptionToAssert, $responseBody);
        }

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $purchaseOrderManagement = Bootstrap::getObjectManager()->get(PurchaseOrderManagementInterface::class);
        $order = $purchaseOrderManagement->createSalesOrder(
            $purchaseOrder,
            $companyAdmin->getId()
        );

        // Assert purchase order has a status of STATUS_ORDER_PLACED
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)
            ->getById($purchaseOrderId);
        $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        $orderId = $order->getId();

        $this->assertNotNull($orderId);

        // Logout of company admin
        $this->session->logout();

        // Reset request and response singleton
        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        Bootstrap::getInstance()->loadArea('frontend');
        $this->resetRequest();
        $this->resetResponse();

        // Log in as the owner of the purchase order
        $subordinateUser = $this->objectManager->get(CustomerRepositoryInterface::class)
            ->get('veronica.costello@example.com');
        $this->session->loginById($subordinateUser->getId());

        // Go to Sales Order details page
        $this->dispatch('sales/order/view/order_id/' . $orderId);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Sales Order details page contains gift card options in response
        $responseBody = $this->getResponse()->getBody();
        foreach ($giftCardOptionsToAssert as $giftCardOptionToAssert) {
            $this->assertStringContainsString($giftCardOptionToAssert, $responseBody);
        }

        $this->session->logout();
    }

    /**
     * Test the reward points are showing on PO details page totals block
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_reward_points.php
     */
    public function testRewardPointsInViewAction()
    {
        // Enable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        // Log in as the company admin
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's purchase order
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');

        // Go to Purchase Order details page
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Purchase Order details page contains gift card options in response
        $responseBody = $this->getResponse()->getBody();

        $this->assertStringContainsString('5 Reward points', $responseBody);
        $this->assertStringContainsString('<span class="price">-$5.00</span>', $responseBody);

        // Logout of company admin
        $this->session->logout();
    }

    /**
     * Test the customer balance and gift cards on PO details page totals block
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_customer_balance_and_gift_card.php
     */
    public function testCustomerBalanceAndGiftCardsInViewAction()
    {
        // Enable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        // Log in as the company admin
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's purchase order
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');

        // Go to Purchase Order details page
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Purchase Order details page contains gift card options in response
        $responseBody = $this->getResponse()->getBody();

        $this->assertStringContainsString(
            'Gift Card (giftcardaccount_fixture)',
            $responseBody
        );

        $this->assertStringContainsString(
            '<span class="discount">-<span class="price">$9.99</span></span>',
            $responseBody
        );

        $this->assertStringContainsString('Store Credit', $responseBody);
        $this->assertStringContainsString('<span class="price">-$0.01</span>', $responseBody);

        // Logout of company admin
        $this->session->logout();
    }

    /**
     * Test the Bundle product options are showing in PO details and Order details page
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_bundle_products.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testBundleProductOptionsInViewAction()
    {
        // Enable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );

        // Log in as the company admin
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's purchase order
        $purchaseOrder = $this->getPurchaseOrderForCustomer('veronica.costello@example.com');

        $bundleOptionsToAssert = [
            'Option 1',
            'Option 2',
            'Option 3',
            'Option 4',
            'Option 5',
            '1 x Simple Product1',
            '1 x Simple Product2'
        ];

        // Go to Purchase Order details page
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Purchase Order details page contains bundle options in response
        $responseBody = $this->getResponse()->getBody();
        foreach ($bundleOptionsToAssert as $bundleOptionToAssert) {
            $this->assertStringContainsString($bundleOptionToAssert, $responseBody);
        }

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $purchaseOrderManagement = Bootstrap::getObjectManager()->get(PurchaseOrderManagementInterface::class);
        $order = $purchaseOrderManagement->createSalesOrder(
            $purchaseOrder,
            $companyAdmin->getId()
        );

        // Assert purchase order has a status of STATUS_ORDER_PLACED
        $postPurchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)
            ->getById($purchaseOrderId);
        $this->assertEquals(PurchaseOrderInterface::STATUS_ORDER_PLACED, $postPurchaseOrder->getStatus());
        $orderId = $order->getId();

        $this->assertNotNull($orderId);

        // Logout of company admin
        $this->session->logout();

        // Reset request and response singleton
        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        Bootstrap::getInstance()->loadArea('frontend');
        $this->resetRequest();
        $this->resetResponse();

        // Log in as the owner of the purchase order
        $subordinateUser = $this->objectManager->get(CustomerRepositoryInterface::class)
            ->get('veronica.costello@example.com');
        $this->session->loginById($subordinateUser->getId());

        // Go to Sales Order details page
        $this->dispatch('sales/order/view/order_id/' . $orderId);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // assert Sales Order details page contains bundle options in response
        $responseBody = $this->getResponse()->getBody();
        foreach ($bundleOptionsToAssert as $bundleOptionToAssert) {
            $this->assertStringContainsString($bundleOptionToAssert, $responseBody);
        }

        $this->session->logout();
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)
            ->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Reset response singleton to allow multiple dispatches in the same test
     */
    private function resetResponse()
    {
        Bootstrap::getObjectManager()->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && 0 !== strpos($property->getDeclaringClass()->getName(), 'PHPUnit')) {
                $property->setAccessible(true);
                $property->setValue($this, null);
            }
        }
    }

    /**
     * Test purchase order banner notifications
     *
     * @param string $customerEmail
     * @param string $orderStatus
     * @param int $productStockStatus
     * @param int $productStatus
     * @param int $productQty
     * @param int $productCartQty
     * @param string $paymentMethod
     * @param array $expectedResponse
     * @param array $notExpectedResponse
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     *
     * @dataProvider bannerNotificationsDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testBannerNotifications(
        string $customerEmail,
        string $orderStatus,
        int $productStockStatus,
        int $productStatus,
        int $productQty,
        int $productCartQty,
        string $paymentMethod,
        array $expectedResponse,
        array $notExpectedResponse
    ) {
        // Enable/Disable purchase order functionality for the specific company
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        $customer = $this->customerRepository->get($customerEmail);
        $purchaseOrder = $this->getPurchaseOrderForCustomer($customerEmail);
        $snapshotQuote = $purchaseOrder->getSnapshotQuote();

        //init purchase order
        $purchaseOrder->setStatus($orderStatus)
            ->setPaymentMethod($paymentMethod);

        //init purchase order quote
        $items = $snapshotQuote->getAllVisibleItems();
        $quoteItem = array_shift($items);
        $quoteItem->setQty($productCartQty);
        $purchaseOrder->setSnapshotQuote($snapshotQuote);
        $this->purchaseOrderRepository->save($purchaseOrder);

        //init quote product data
        $product = $this->productRepository->get(
            'virtual-product',
            false,
            0
        );

        $product->setStockData(
            [
                'qty' => $productQty,
                'is_in_stock' => $productStockStatus
            ]
        );
        $product->setStatus($productStatus);
        $this->productRepository->save($product);
        $this->stockRegistryStorage->clean();

        $this->session->loginById($customer->getId());

        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        $responseBody = $this->getResponse()->getBody();

        foreach ($expectedResponse as $response) {
            $this->assertStringContainsString(
                $response,
                $responseBody
            );
        }

        foreach ($notExpectedResponse as $response) {
            $this->assertStringNotContainsString(
                $response,
                $responseBody
            );
        }
    }

    /**
     * Data Provider method for banner notifications test
     *
     * @return array
     */
    public function bannerNotificationsDataProvider()
    {
        return [
            'product_enabled_in_stock_right_item_qty' => [
                'customerEmail' => 'john.doe@example.com',
                'orderStatus' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'productStockStatus' => StockStatus::STATUS_IN_STOCK,
                'productStatus' => ProductStatus::STATUS_ENABLED,
                'productQty' => 10,
                'productCartQty' => 2,
                'paymentMethod' => 'paypal_express',
                'expectedResponse' => [
                    '<span>Place Order</span>'
                ],
                'notExpectedResponse' => [
                    'This order could not be completed as some items are currently unavailable.'
                ]

            ],
            'product_disabled_in_stock_right_item_qty' => [
                'customerEmail' => 'john.doe@example.com',
                'orderStatus' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'productStockStatus' => StockStatus::STATUS_IN_STOCK,
                'productStatus' => ProductStatus::STATUS_DISABLED,
                'productQty' => 10,
                'productCartQty' => 2,
                'paymentMethod' => 'paypal_express',
                'expectedResponse' => [
                    'This order could not be completed as some items are currently unavailable.'
                ],
                'notExpectedResponse' => [
                    '<span>Place Order</span>'
                ]
            ],
            'product_enabled_out_of_stock_right_item_qty' => [
                'customerEmail' => 'john.doe@example.com',
                'orderStatus' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'productStockStatus' => StockStatus::STATUS_OUT_OF_STOCK,
                'productStatus' => ProductStatus::STATUS_ENABLED,
                'productQty' => 10,
                'productCartQty' => 2,
                'paymentMethod' => 'paypal_express',
                'expectedResponse' => [
                    'This order could not be completed as some items are currently unavailable.'
                ],
                'notExpectedResponse' => [
                    '<span>Place Order</span>'
                ]
            ],
            'product_enabled_in_stock_zero_item_qty' => [
                'customerEmail' => 'john.doe@example.com',
                'orderStatus' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'productStockStatus' => StockStatus::STATUS_IN_STOCK,
                'productStatus' => ProductStatus::STATUS_ENABLED,
                'productQty' => 0,
                'productCartQty' => 2,
                'paymentMethod' => 'paypal_express',
                'expectedResponse' => [
                    'This order could not be completed as some items are currently unavailable.'
                ],
                'notExpectedResponse' => [
                    '<span>Place Order</span>'
                ]
            ],
            'product_enabled_in_stock_wrong_item_qty' => [
                'customerEmail' => 'john.doe@example.com',
                'orderStatus' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'productStockStatus' => StockStatus::STATUS_IN_STOCK,
                'productStatus' => ProductStatus::STATUS_ENABLED,
                'productQty' => 1,
                'productCartQty' => 2,
                'paymentMethod' => 'paypal_express',
                'expectedResponse' => [
                    'This order could not be completed as some items are currently unavailable.'
                ],
                'notExpectedResponse' => [
                    '<span>Place Order</span>'
                ]
            ]
        ];
    }
}
