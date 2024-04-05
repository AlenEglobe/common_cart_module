<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\PurchaseOrder;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Model\RoleRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\Data\AppliedRuleApproverInterface;
use Magento\PurchaseOrderRule\Api\Data\AppliedRuleInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Payment\Helper\Data as PaymentData;

/**
 * Controller test class for approving purchase orders with applied rules
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Approve
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ApproveTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorder/purchaseorder/approve';

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    /**
     * @var AppliedRuleRepositoryInterface
     */
    private $appliedRuleRepository;

    /**
     * @var AppliedRuleApproverRepositoryInterface
     */
    private $appliedRuleApproverRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var PaymentData
     */
    private $paymentData;

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
        $this->roleRepository = $objectManager->create(RoleRepository::class);
        $this->appliedRuleRepository = $objectManager->get(AppliedRuleRepositoryInterface::class);
        $this->appliedRuleApproverRepository = $objectManager->create(AppliedRuleApproverRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);
        $this->paymentData = $objectManager->get(PaymentData::class);

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @param string $paymentMethod
     * @throws LocalizedException
     * @throws \Exception
     * @dataProvider paymentMethodsDataProvider
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     */
    public function testApproveActionForSingleRule($paymentMethod)
    {
        // Log in as the approver
        $levelOneCustomer = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($levelOneCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);
        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($purchaseOrder);

        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());
        $appliedRules = $this->appliedRuleRepository->getListByPurchaseOrderId((int)$purchaseOrder->getEntityId());
        foreach ($appliedRules->getItems() as $rule) {
            $this->assertTrue($rule->isApproved());
        }

        $this->session->logout();
    }

    /**
     * @param string $paymentMethod
     * @throws LocalizedException
     * @throws \Exception
     * @dataProvider paymentMethodsDataProvider
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testApproveActionForMultipleRulesAdminApproval($paymentMethod)
    {
        // Log in as the company admin
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($postPurchaseOrder);
        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * @param string $paymentMethod
     * @throws LocalizedException
     * @throws \Exception
     * @dataProvider paymentMethodsDataProvider
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testApproveActionForMultipleRulesSingleApproval($paymentMethod)
    {
        // Log in as the approver
        $levelOneCustomer = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($levelOneCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testApproveActionForMultipleRulesFinalApproval($paymentMethod)
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        /** @var RoleInterface $role1 */
        $role1 = current(
            $this->roleRepository->getList(
                $this->searchCriteriaBuilder
                    ->addFilter(RoleInterface::ROLE_NAME, 'Role 1')
                    ->create()
            )
            ->getItems()
        );
        $appliedRuleList = $this->appliedRuleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(AppliedRuleInterface::KEY_PURCHASE_ORDER_ID, $purchaseOrder->getEntityId())
                ->create()
        );
        foreach ($appliedRuleList->getItems() as $appliedRule) {
            /** @var AppliedRuleApproverInterface $approver */
            $approver = current(
                $this->appliedRuleApproverRepository
                    ->getListByAppliedRuleId((int)$appliedRule->getId())
                    ->getItems()
            );
            if ($approver->getRoleId() == $role1->getId()) {
                $levelOneCustomer = $this->customerRepository->get('veronica.costello@example.com');
                $approver->approve((int)$levelOneCustomer->getId());
                $this->appliedRuleApproverRepository->save($approver);
            }
        }

        // Log in as the second approver
        $levelTwoCustomer = $this->customerRepository->get('alex.smith@example.com');
        $this->session->loginById($levelTwoCustomer->getId());

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Perform assertions
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());

        $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($postPurchaseOrder);

        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * Test that an approval from a user with super approver permissions approves the whole purchase order.
     *
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testApproveActionForMultipleRulesSuperApproval($paymentMethod)
    {
        // Grant the "Role 1" role with super approver permission
        $this->setCompanyRolePermission(
            'Magento',
            'Role 1',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Role 1',
            'Magento_PurchaseOrderRule::super_approve_purchase_order',
            PermissionInterface::ALLOW_PERMISSION
        );

        // Log in as the super approver
        $levelOneCustomer = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($levelOneCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Verify the PO is approved
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());

        $expectedStatus = $this->getExpectedPurchaseOrderApprovedStatus($postPurchaseOrder);

        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        // Verify the applied rules are only approved for the super approver role
        /** @var RoleInterface $role1 */
        $role1 = current(
            $this->roleRepository
                ->getList(
                    $this->searchCriteriaBuilder
                        ->addFilter(RoleInterface::ROLE_NAME, 'Role 1')
                        ->create()
                )
                ->getItems()
        );
        $appliedRuleList = $this->appliedRuleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(AppliedRuleInterface::KEY_PURCHASE_ORDER_ID, $purchaseOrder->getEntityId())
                ->create()
        );
        foreach ($appliedRuleList->getItems() as $appliedRule) {
            /** @var AppliedRuleApproverInterface $approver */
            $approver = current(
                $this->appliedRuleApproverRepository
                    ->getListByAppliedRuleId((int)$appliedRule->getId())
                    ->getItems()
            );
            if ($approver->getRoleId() == $role1->getId()) {
                $this->assertTrue($appliedRule->isApproved());
            } else {
                $this->assertFalse($appliedRule->isApproved());
            }
        }

        $this->session->logout();
    }

    /**
     * Test that an approval from a user with super approver permissions who is the creator, so can't approve.
     *
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_super_approver_multiple_rules.php
     */
    public function testApproveActionForMultipleRulesSuperApprovalInApprovingRole($paymentMethod)
    {
        // Grant the "Role 1" role with super approver permission
        $this->setCompanyRolePermission(
            'Magento',
            'Role 1',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Role 1',
            'Magento_PurchaseOrderRule::super_approve_purchase_order',
            PermissionInterface::ALLOW_PERMISSION
        );

        // Log in as the super approver
        $buyerCustomer = $this->customerRepository->get('buyer@example.com');
        $this->session->loginById($buyerCustomer->getId());

        // Retrieve the current users PO
        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Verify the super approver is unable to approve their own PO
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('company/accessdenied'));
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * Test that an approval from a user with super approver who isn't required approval to the PO can't approve.
     *
     * @param string $paymentMethod
     * @dataProvider paymentMethodsDataProvider
     * @throws LocalizedException
     * @throws \Exception
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     */
    public function testApproveActionForSuperApproverWhoCannotApprovePurchaseOrder($paymentMethod)
    {
        // Grant the "Default User" role with super approver permission
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrderRule::super_approve_purchase_order',
            PermissionInterface::ALLOW_PERMISSION
        );

        // Log in as the super approver
        $levelTwoCustomer = $this->customerRepository->get('alex.smith@example.com');
        $this->session->loginById($levelTwoCustomer->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $purchaseOrder->setPaymentMethod($paymentMethod);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Dispatch approval
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        // Verify the super approver is unable to approve a PO they aren't required for approval on
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains('company/accessdenied'));
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        $this->session->logout();
    }

    /**
     * Data provider of purchase order payment methods
     *
     * @return string[]
     */
    public function paymentMethodsDataProvider()
    {
        return [
            'Offline Payment Method' => ['checkmo'],
            'Online Payment Method' => ['paypal_express']
        ];
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
     * Sets the permission value for the specified company role.
     *
     * @param string $companyName
     * @param string $roleName
     * @param string $resourceId
     * @param string $permissionValue
     * @throws LocalizedException
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
     * Get expected purchase order status based on payment method
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     * @throws LocalizedException
     */
    private function getExpectedPurchaseOrderApprovedStatus(PurchaseOrderInterface $purchaseOrder)
    {
        $paymentMethodInstance = $this->paymentData->getMethodInstance($purchaseOrder->getPaymentMethod());
        return ($paymentMethodInstance->isOffline())
            ? PurchaseOrderInterface::STATUS_APPROVED
            : PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT;
    }
}
