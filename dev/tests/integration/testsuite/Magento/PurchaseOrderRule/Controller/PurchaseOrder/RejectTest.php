<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\PurchaseOrder;

use Magento\Company\Model\Role;
use Magento\Company\Model\UserRoleManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderManagementInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\Data\AppliedRuleApproverInterface;
use Magento\PurchaseOrderRule\Model\AppliedRule;
use Magento\PurchaseOrderRule\Model\AppliedRuleApprover;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Controller test class for rejecting a purchase order
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class RejectTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorder/purchaseorder/reject';

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
     * @var UserRoleManagement
     */
    private $userRoleManagement;

    /**
     * @var AppliedRuleRepositoryInterface
     */
    private $appliedRuleRepository;

    /**
     * @var AppliedRuleApproverRepositoryInterface
     */
    private $appliedRuleApproverRepository;

    /**
     * @var PurchaseOrderManagementInterface
     */
    private $purchaseOrderManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);

        $this->userRoleManagement = $objectManager->get(UserRoleManagement::class);
        $this->appliedRuleRepository = $objectManager->get(AppliedRuleRepositoryInterface::class);
        $this->appliedRuleApproverRepository = $objectManager->get(AppliedRuleApproverRepositoryInterface::class);

        $this->purchaseOrderManagement = $objectManager->get(PurchaseOrderManagementInterface::class);

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Verify that rejecting a Purchase Order updates the applied rule approver entry
     *
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testRejectUpdatesRuleApprover()
    {
        // Log in as the current user
        $currentUser = $this->customerRepository->get('veronica.costello@example.com');
        $currentUserRoles = $this->userRoleManagement->getRolesByUserId($currentUser->getId());
        $currentUserRoleIds = array_map(function (Role $role) {
            return $role->getRoleId();
        }, $currentUserRoles);
        $this->session->loginById($currentUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_REJECTED, $postPurchaseOrder->getStatus());

        $appliedRules = $this->appliedRuleRepository->getListByPurchaseOrderId((int) $postPurchaseOrder->getEntityId());
        $this->assertEquals(2, $appliedRules->getTotalCount());

        /** @var AppliedRule $appliedRule */
        foreach ($appliedRules->getItems() as $appliedRule) {
            $approvers = $this->appliedRuleApproverRepository->getListByAppliedRuleId((int) $appliedRule->getId());
            $this->assertEquals(1, $approvers->getTotalCount());

            /** @var AppliedRuleApprover $approver */
            foreach ($approvers as $approver) {
                // Verify all approvers of the same role as the rejector were marked, and all others are left as pending
                if (in_array($approver->getRoleId(), $currentUserRoleIds)) {
                    $this->assertEquals(AppliedRuleApprover::STATUS_REJECTED, $approver->getStatus());
                } else {
                    $this->assertEquals(AppliedRuleApprover::STATUS_PENDING, $approver->getStatus());
                }
            }
        }

        $this->session->logout();
    }

    /**
     * Verify when an admin rejects a partially approved Purchase Order that only the relevant entries are updated
     *
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules_partially_approved.php
     */
    public function testRejectPartiallyApprovedPurchaseOrderUpdatesCorrectly()
    {
        // Login as admin and reject partially approved PO
        $currentUser = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($currentUser->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');

        $appliedRules = $this->appliedRuleRepository->getListByPurchaseOrderId((int) $purchaseOrder->getEntityId());
        $this->assertEquals(2, $appliedRules->getTotalCount());

        /** @var AppliedRule $appliedRule */
        foreach ($appliedRules->getItems() as $appliedRule) {
            $approvers = $this->appliedRuleApproverRepository->getListByAppliedRuleId((int) $appliedRule->getId());
            $this->assertEquals(1, $approvers->getTotalCount());

            /** @var AppliedRuleApprover $approver */
            foreach ($approvers->getItems() as $approver) {
                // Verify the initial state of the approvers, admin should be pending manager approved
                if ($approver->getApproverType() === AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN) {
                    $this->assertEquals(AppliedRuleApprover::STATUS_PENDING, $approver->getStatus());
                }
                if ($approver->getApproverType() === AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER) {
                    $this->assertEquals(AppliedRuleApprover::STATUS_APPROVED, $approver->getStatus());
                }
            }
        }

        // Reject the purchase order as the admin
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals(PurchaseOrderInterface::STATUS_REJECTED, $postPurchaseOrder->getStatus());

        $appliedRules = $this->appliedRuleRepository->getListByPurchaseOrderId((int) $postPurchaseOrder->getEntityId());
        $this->assertEquals(2, $appliedRules->getTotalCount());

        /** @var AppliedRule $appliedRule */
        foreach ($appliedRules->getItems() as $appliedRule) {
            $approvers = $this->appliedRuleApproverRepository->getListByAppliedRuleId((int) $appliedRule->getId());
            $this->assertEquals(1, $approvers->getTotalCount());

            /** @var AppliedRuleApprover $approver */
            foreach ($approvers->getItems() as $approver) {
                // Verify the admin is rejected and the manager is approved
                if ($approver->getApproverType() === AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN) {
                    $this->assertEquals(AppliedRuleApprover::STATUS_REJECTED, $approver->getStatus());
                }
                if ($approver->getApproverType() === AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER) {
                    $this->assertEquals(AppliedRuleApprover::STATUS_APPROVED, $approver->getStatus());
                }
            }
        }

        $this->session->logout();
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
}
