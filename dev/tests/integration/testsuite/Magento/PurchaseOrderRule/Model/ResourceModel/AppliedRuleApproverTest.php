<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\ResourceModel;

use Magento\Company\Model\UserRole;
use Magento\Company\Model\UserRoleManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderRepository;
use Magento\PurchaseOrderRule\Api\Data\AppliedRuleApproverInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test for Applied Rule Approver collection grid
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AppliedRuleApproverTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var AppliedRuleApprover
     */
    private $appliedRuleApprover;

    /**
     * @var PurchaseOrderRepository
     */
    private $purchaseOrderRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var UserRoleManagement
     */
    private $userRoleManagement;

    /**
     * @var Session
     */
    private $session;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->appliedRuleApprover = $this->objectManager->get(AppliedRuleApprover::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepository::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->userRoleManagement = $this->objectManager->get(UserRoleManagement::class);
        $this->session = $this->objectManager->get(Session::class);
    }

    /**
     * Test retrieving purchase order IDs as a manager
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_manager_approver.php
     * @throws LocalizedException
     */
    public function testGetPurchaseOrderIdsAsManager()
    {
        $manager = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($manager->getId());

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('alex.smith@example.com', $creator->getEmail());
    }

    /**
     * Test retrieving purchase order IDs as the approving role
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     * @throws LocalizedException
     */
    public function testGetPurchaseOrderIdsAsApprovingRole()
    {
        $roleUser = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($roleUser->getId());
        $userRoles = $this->userRoleManagement->getRolesByUserId($roleUser->getId());
        /** @var UserRole $userRole */
        $userRole = current($userRoles);

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_ROLE,
            $userRole->getId()
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('buyer@example.com', $creator->getEmail());
    }

    /**
     * Test that a Purchase Order with a specific role approver is not visible to the manager
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     * @throws LocalizedException
     */
    public function testGetPurchaseOrderIdsNotManager()
    {
        $roleUser = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($roleUser->getId());

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER
        );

        $this->assertEquals(0, count($purchaseOrderIds));
    }

    /**
     * Verify a user in each role of a multiple approver role can retrieve the Purchase Order
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule.php
     * @dataProvider multipleApproversDataProvider
     * @param $email
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetPurchaseOrderIdsAsMultipleApprovingRoles($email)
    {
        $roleUser = $this->customerRepository->get($email);
        $this->session->loginById($roleUser->getId());
        $userRoles = $this->userRoleManagement->getRolesByUserId($roleUser->getId());
        /** @var UserRole $userRole */
        $userRole = current($userRoles);

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_ROLE,
            $userRole->getId()
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('buyer@example.com', $creator->getEmail());
    }

    /**
     * Data provider for email of customers within roles
     *
     * @return array
     */
    public function multipleApproversDataProvider()
    {
        return [
            'role_1' => [
                'email' => 'veronica.costello@example.com',
            ],
            'role_2' => [
                'email' => 'alex.smith@example.com'
            ]
        ];
    }

    /**
     * Verify an admin will retrieve purchase order which triggered rule requiring admin approval
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_admin_approver.php
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetPurchaseOrderIdsAsAdmin()
    {
        $adminUser = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($adminUser->getId());

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('alex.smith@example.com', $creator->getEmail());
    }

    /**
     * Verify an admin will retrieve PO which triggered rule requiring manager approval from their subordinate
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_manager_approver_admin_subordinate_creator.php
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetPurchaseOrderIdsAsAdminRequiringManagerApproval()
    {
        $adminUser = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($adminUser->getId());

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('veronica.costello@example.com', $creator->getEmail());
    }

    /**
     * Verify the entire management chain can see a PO requiring approval from a manager
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_by_nested_subordinate_manager_approver.php
     * @dataProvider multipleApproversChainDataProvider
     * @param $email
     * @param $type
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetPurchaseOrderIdsAsManagerChain($email, $type)
    {
        $roleUser = $this->customerRepository->get($email);
        $this->session->loginById($roleUser->getId());

        $purchaseOrderIds = $this->appliedRuleApprover->getPurchaseOrderIdsByAppliedRole(
            $type
        );

        $this->assertEquals(1, count($purchaseOrderIds));

        $purchaseOrder = $this->purchaseOrderRepository->getById(current($purchaseOrderIds));
        $creator = $this->customerRepository->getById($purchaseOrder->getCreatorId());
        $this->assertEquals('buyer@example.com', $creator->getEmail());
    }

    /**
     * Provide chain of command in company structure
     *
     * @return array
     */
    public function multipleApproversChainDataProvider()
    {
        return [
            'role_1' => [
                'email' => 'veronica.costello@example.com',
                'type' => AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER,
            ],
            'role_2' => [
                'email' => 'alex.smith@example.com',
                'type' => AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER,
            ],
            'admin' => [
                'email' => 'john.doe@example.com',
                'type' => AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN,
            ]
        ];
    }
}
