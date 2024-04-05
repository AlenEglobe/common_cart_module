<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\QueueConsumer;

use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\Role;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderLogRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\Data\AppliedRuleApproverInterface;
use Magento\PurchaseOrderRule\Model\AppliedRule;
use Magento\PurchaseOrderRule\Model\AppliedRuleApprover;
use Magento\PurchaseOrderRule\Model\RuleRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;

/**
 * Test for validating Purchase Order
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ValidatePurchaseOrderTest extends TestCase
{
    private const CONSUMER_NAME = 'purchaseorder.validation';

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var ConsumerFactory
     */
    private $consumerFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var AppliedRuleRepositoryInterface
     */
    private $appliedRulesRepository;

    /**
     * @var AppliedRuleApproverRepositoryInterface
     */
    private $appliedRulesApproverRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PurchaseOrderLogRepositoryInterface
     */
    private $purchaseOrderLogRepository;

    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @var ClearQueueProcessor
     */
    private $clearQueueProcessor;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->publisher = $this->objectManager->get(PublisherInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->appliedRulesRepository = $this->objectManager->get(AppliedRuleRepositoryInterface::class);
        $this->appliedRulesApproverRepository =
            $this->objectManager->get(AppliedRuleApproverRepositoryInterface::class);
        $this->roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);
        $this->purchaseOrderLogRepository = $this->objectManager->get(PurchaseOrderLogRepositoryInterface::class);
        $this->ruleRepository = $this->objectManager->get(RuleRepository::class);
        $this->clearQueueProcessor = $this->objectManager->get(ClearQueueProcessor::class);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationNoRules()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify no applied rule messages are in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(0, $applied->getTotalCount());

        // Verify approved message is in the log
        $approved = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'auto_approve')
                ->create()
        );
        $this->assertEquals(1, $approved->getTotalCount());
    }

    /**
     * Test a rule which won't match the purchase order marks the order as approved
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_no_match.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationNoMatchingRules()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify no applied rule messages are in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(0, $applied->getTotalCount());

        // Verify approved message is in the log
        $approved = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'auto_approve')
                ->create()
        );
        $this->assertEquals(1, $approved->getTotalCount());
    }

    /**
     * Test a disabled rule won't match and the Purchase Order is marked as approved
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_disabled.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationDisabledRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify no rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(0, $appliedRules->getTotalCount());

        // Verify no applied rule messages are in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(0, $applied->getTotalCount());

        // Verify approved message is in the log
        $approved = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'auto_approve')
                ->create()
        );
        $this->assertEquals(1, $approved->getTotalCount());
    }

    /**
     * Test a company with a rule of Order Total > 9 with a Purchase Order of 10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalRule()
    {
        $this->validateRuleWasApplied();
    }

    /**
     * Test a company with a rule of Order Total < 20 with a Purchase Order of 10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_less_than_20.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalRuleLessThan()
    {
        $this->validateRuleWasApplied();
    }

    /**
     * Test a company with a rule of Order Total >= 10 with a Purchase Order of 10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_more_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalGreaterThanOrEqual()
    {
        $this->validateRuleWasApplied();
    }

    /**
     * Test a company with a rule of Order Total <= 10 with a Purchase Order of 10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_less_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalLessThanOrEqual()
    {
        $this->validateRuleWasApplied();
    }

    /**
     * Test a company with a rule of Order Total < 10 with a Purchase Order of 20
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_two_simple_products.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_less_than_20.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalRuleLessThanNotApplied()
    {
        $this->validateNoRulesWereApplied();
    }

    /**
     * Test a company with a rule of Order Total >= 10 with a Purchase Order of 20
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_two_simple_products.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_more_than_or_equal_25.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalGreaterThanOrEqualNotApplied()
    {
        $this->validateNoRulesWereApplied();
    }

    /**
     * Test a company with a rule of Order Total <= 10 with a Purchase Order of 20
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_two_simple_products.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_less_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalLessThanOrEqualNotApplied()
    {
        $this->validateNoRulesWereApplied();
    }

    /**
     * Validate that rule is matched to purchase order.
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function validateRuleWasApplied()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int)$postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertFalse($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current role
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int)$appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApprover = reset($appliedRuleApproversItems);

        // Verify role of the approver is the default role in the company
        $approverRoles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', 'Approver Role')
                ->create()
        )->getItems();
        /** @var Role $approverRole */
        $approverRole = reset($approverRoles);
        $this->assertEquals($approverRole->getId(), $appliedRuleApprover->getRoleId());

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test a company with a rule of Order Total > 5 with a Purchase Order of 10 and multiple approvers
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_with_multiple_roles.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationGrandTotalRuleWithMultipleApproversRoles()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $purchaseOrder->setIsValidate(1);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order has been validated
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int)$postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertFalse($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current roles
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int)$appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(2, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApproverIds = array_map(function (AppliedRuleApprover $appliedRuleApprover) {
            return $appliedRuleApprover->getRoleId();
        }, $appliedRuleApproversItems);

        // Verify roles
        $roles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', ['Role 1', 'Role 2'], 'in')
                ->create()
        )->getItems();
        /** @var Role $defaultRole */
        $roleIds = array_map(function (Role $role) {
            return $role->getId();
        }, $roles);
        $this->assertEmpty(array_diff($appliedRuleApproverIds, $roleIds));

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test a company with rule for admin approvals
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_admin_rule.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationAdminRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order requires approval
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertFalse($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current role
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int) $appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApprover = reset($appliedRuleApproversItems);
        $this->assertEquals(AppliedRuleApproverInterface::APPROVER_TYPE_ADMIN, $appliedRuleApprover->getApproverType());

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test a company with rule for manager approvals
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_manager_rule.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationManagerRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order requires approval
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertFalse($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current role
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int) $appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApprover = reset($appliedRuleApproversItems);
        $this->assertEquals(
            AppliedRuleApproverInterface::APPROVER_TYPE_MANAGER,
            $appliedRuleApprover->getApproverType()
        );

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test a company with a rule of Order Total > 9 with a Purchase Order of 10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_duplicate.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationMultipleGrandTotalRules()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order requires approval
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int)$postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(2, $appliedRules->getTotalCount());

        // Verify role of the approver is the default role in the company
        $approverRoles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', 'Approver Role')
                ->create()
        )->getItems();
        /** @var Role $approverRole */
        $approverRole = reset($approverRoles);

        /** @var AppliedRule $appliedRule */
        foreach ($appliedRules->getItems() as $appliedRule) {
            $this->assertFalse($appliedRule->isApproved());

            // Check the applied rule is requiring approval from the current role
            $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
                (int)$appliedRule->getId()
            );
            $appliedRuleApproversItems = $appliedRuleApprovers->getItems();

            /** @var AppliedRuleApprover $appliedRuleApprover */
            $appliedRuleApprover = reset($appliedRuleApproversItems);
            $this->assertEquals($approverRole->getId(), $appliedRuleApprover->getRoleId());
        }

        // Verify applied rule messages in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(2, $applied->getTotalCount());
    }

    /**
     * Test a rule which will match and have the same approver role as the creators role and approve the PO
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_approver_equal_to_purchase_order_creator.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationTriggeredSameApproverAsCreator()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int)$postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertTrue($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current role
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int)$appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApprover = reset($appliedRuleApproversItems);

        // Verify role of the approver is the default role in the company
        $approverRoles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', 'Default User')
                ->create()
        )->getItems();
        /** @var Role $approverRole */
        $approverRole = reset($approverRoles);
        $this->assertEquals($approverRole->getId(), $appliedRuleApprover->getRoleId());

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test a rule which only applies to a different role than the Purchase Order creator
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_applies_to_different_role.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationRuleWhichDoesNotApplyToCreatorRole()
    {
        $this->validateNoRulesWereApplied();
    }

    /**
     * Validate no rules were applied and order has been approved.
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function validateNoRulesWereApplied()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify no rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(0, $appliedRules->getTotalCount());

        // Verify no applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(0, $applied->getTotalCount());

        // Verify approval message in the log
        $approved = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'auto_approve')
                ->create()
        );
        $this->assertEquals(1, $approved->getTotalCount());
    }

    /**
     * Test a rule which will match and have the same approver role as the creators role and approve the PO
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_applies_to_creator_role_with_other_rules.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationAppliesToCreatorRoleWithOtherRules()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(1, $appliedRules->getTotalCount());
        $appliedRulesItems = $appliedRules->getItems();
        /** @var AppliedRule $appliedRule */
        $appliedRule = reset($appliedRulesItems);
        $this->assertFalse($appliedRule->isApproved());

        // Check the applied rule is requiring approval from the current role
        $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
            (int) $appliedRule->getId()
        );
        $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
        $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
        /** @var AppliedRuleApprover $appliedRuleApprover */
        $appliedRuleApprover = reset($appliedRuleApproversItems);

        // Verify role of the approver is the default role in the company
        $approverRoles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', 'Approver Role')
                ->create()
        )->getItems();
        /** @var Role $approverRole */
        $approverRole = reset($approverRoles);
        $this->assertEquals($approverRole->getId(), $appliedRuleApprover->getRoleId());

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test multiple rules with the same applies to but with different approvers
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_multiple_rules_applies_to_same_role.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationMultipleRulesSameAppliesToDifferentApprovers()
    {
        $this->verifyTwoRulesMatch();
    }

    /**
     * Test multiple rules with the same applies to with other applies to role IDs and with different approvers
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_multiple_rules_applies_to_same_role_with_other_applies_to.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationMultipleRulesMultipleAppliesToDifferentApprovers()
    {
        $this->verifyTwoRulesMatch();
    }

    /**
     * Test multiple rules one which applies to the role and one that applies to all with different approvers
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_multiple_rules_applies_to_role_applies_to_all.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationMultipleRulesOneAppliesToRoleOneAppliestoAll()
    {
        $this->verifyTwoRulesMatch();
    }

    /**
     * Verify two rules match for multiple rules validation with different approvers
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws InputException
     */
    private function verifyTwoRulesMatch()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(2, $appliedRules->getTotalCount());

        // Verify role of the approver is the default role in the company
        $approverRoles = $this->roleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $purchaseOrder->getCompanyId())
                ->addFilter('role_name', ['Approver Role', 'Approver Role 1'], 'in')
                ->create()
        )->getItems();
        /** @var Role $approverRole */
        $approverIds = [];
        foreach ($approverRoles as $approverRole) {
            $approverIds[] = $approverRole->getId();
        }

        $count = 0;
        /** @var AppliedRule $appliedRule */
        foreach ($appliedRules->getItems() as $appliedRule) {
            $this->assertFalse($appliedRule->isApproved());

            // Check the applied rule is requiring approval from the current role
            $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
                (int) $appliedRule->getId()
            );
            $appliedRuleApproversItems = $appliedRuleApprovers->getItems();

            /** @var AppliedRuleApprover $appliedRuleApprover */
            $appliedRuleApprover = reset($appliedRuleApproversItems);
            $this->assertEquals($approverIds[$count], $appliedRuleApprover->getRoleId());
            ++$count;
        }

        // Verify applied rule messages in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(2, $applied->getTotalCount());
    }

    /**
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

    /**
     * Test a rule of Order Total > 9 EUR which won't match the purchase order of 10 USD marks the order as approved
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_grand_total_eur_no_match.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationNoMatchingCurrencyRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify approval message in the log
        $approved = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'auto_approve')
                ->create()
        );
        $this->assertEquals(1, $approved->getTotalCount());
    }

    /**
     * Test a rule of Order Total > 10000000 NOK which match the PO of 10 USD marks the order as approval required
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_missing_currency.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationMatchingNoCurrencyRateRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify applied rule message in the log
        $applied = $this->purchaseOrderLogRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(PurchaseOrderLogInterface::REQUEST_ID, $id)
                ->addFilter(PurchaseOrderLogInterface::ACTIVITY_TYPE, 'apply_rules')
                ->create()
        );
        $this->assertEquals(1, $applied->getTotalCount());
    }

    /**
     * Test purchase order is auto approved if creator is in rule approvers role
     *
     * @magentoDbIsolation enabled
     * @dataProvider ruleAutoApprovedDataProvider
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_creator_in_approvers_role.php
     * @param $ruleNames
     * @param $creatorIsTheOnlyApprover
     * @param $status
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InputMismatchException
     */
    public function testValidationMatchingRuleAutoApproval(
        $ruleNames,
        $creatorIsTheOnlyApprover,
        $status
    ) {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $allRules = $this->ruleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('description', 'Creator as approver test rule')
                ->create()
        )->getItems();
        foreach ($allRules as $rule) {
            if (in_array($rule->getName(), $ruleNames)) {
                $rule->setIsActive(true);
            } else {
                $rule->setIsActive(false);
            }
            $rule->setAdminApprovalRequired($rule->isAdminApprovalRequired());
            $rule->setManagerApprovalRequired($rule->isManagerApprovalRequired());
            $this->ruleRepository->save($rule);
        }

        if ($creatorIsTheOnlyApprover) {
            // Set one of two users in the role to inactive status
            $notActiveUser = $this->customerRepository->get('veronica.costello@example.com');
            $notActiveUser->getExtensionAttributes()
                ->getCompanyAttributes()
                ->setStatus(CompanyCustomerInterface::STATUS_INACTIVE);
            $this->customerRepository->save($notActiveUser);
        }
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as there is only PO Creator who is active user whithin approval role
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals($status, $postPurchaseOrder->getStatus());
    }

    /**
     * @return array
     */
    public function ruleAutoApprovedDataProvider()
    {
        return [
            'Creator is not the only user in approvers role' => [
                ['Rule with Creator as Approver'],
                false,
                PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ],
            'Creator is the only user in approvers role, rule requires admin approval' => [
                ['Rule with Creator and Admin as Approver'],
                true,
                PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ],
            'Creator is the only active user in approvers role, PO matches other rule requires manager approval' => [
                ['Rule with Creator as Approver', 'Rule with Manager as Approver'],
                true,
                PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ],
            'Creator is the only active user in approvers role, PO matches other rule requires admin approval' => [
                ['Rule with Creator as Approver', 'Rule with Admin as Approver'],
                true,
                PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ],
            'Creator is the only active user in approvers role, PO matches other rule requires other rule approval' => [
                ['Rule with Creator as Approver', 'Rule with not Creator role as Approver'],
                true,
                PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
            ],
            'Creator is the only active user in approvers role' => [
                ['Rule with Creator as Approver'],
                true,
                PurchaseOrderInterface::STATUS_APPROVED
            ]
        ];
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by a rule of shipping cost over $5
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_5.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostGreaterThan5OnPurchaseOrderShippingCost10()
    {
        $this->verifyPurchaseOrderMatches(['Shipping Cost Greater Than or Equal to 5']);
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by a rule of shipping cost greater or equal to $10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostGreaterThanOrEqual10OnPurchaseOrderShippingCost10()
    {
        $this->verifyPurchaseOrderMatches(['Shipping Cost Greater More Than or Equal 10']);
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by a rule of shipping cost less than or equal to $10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostLessThanOrEqual10OnPurchaseOrderShippingCost10()
    {
        $this->verifyPurchaseOrderMatches(['Shipping Cost Less Than or Equal 10']);
    }

    /**
     * Test a purchase order with a shipping cost of $0 is matched by a rule less than $5
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_0_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_5.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostLessThan5OnPurchaseOrderShippingCost0()
    {
        $this->verifyPurchaseOrderMatches(['Shipping Cost Less Than 5']);
    }

    /**
     * Test a purchase order with a shipping cost of $0 is matched by multiple less than rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_0_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_5.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMultipleRulesPurchaseOrderShippingCost0()
    {
        $this->verifyPurchaseOrderMatches([
            'Shipping Cost Less Than 5',
            'Shipping Cost Less Than or Equal 10'
        ]);
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by multiple rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_5.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_or_equal_10.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMultipleRulesShippingCost10()
    {
        $this->verifyPurchaseOrderMatches([
            'Shipping Cost Greater More Than or Equal 10',
            'Shipping Cost Greater Than or Equal to 5',
            'Shipping Cost Less Than or Equal 10'
        ]);
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by a 5 EUR or more shipping cost rule
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_5_EUR.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMoreThan5EURPurchaseOrder10USD()
    {
        $this->verifyPurchaseOrderMatches(['Shipping Cost Greater Than or Equal to 5EUR']);
    }

    /**
     * Test a purchase order with a shipping cost of $10 is matched by a 10 EUR or more shipping cost rule
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_10_EUR.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMoreThan10EURPurchaseOrder10USD()
    {
        $this->verifyPurchaseOrderDoesNotMatchAnyRule();
    }

    /**
     * Test a purchase order with a shipping cost of $10 is not matched and approved by a shipping cost rule < $5
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_10_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_less_than_5.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostLessThan5OnPurchaseOrderShippingCost10()
    {
        $this->verifyPurchaseOrderDoesNotMatchAnyRule();
    }

    /**
     * Test a purchase order with shipping cost of $0 does not match a rule above or equal to $10
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_0_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMoreThanOrEqual10OnPurchaseOrderShippingCost0()
    {
        $this->verifyPurchaseOrderDoesNotMatchAnyRule();
    }

    /**
     * Test a purchase order with a shipping cost of $0 is not matched by multiple rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_with_0_shipping_cost.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_5.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_shipping_cost_more_than_or_equal_10.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationShippingCostMultipleRulesShippingCost0()
    {
        $this->verifyPurchaseOrderDoesNotMatchAnyRule();
    }

    /**
     * Verify the shipping cost matches based on fixtures
     *
     * @param array $ruleNames
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function verifyPurchaseOrderMatches(array $ruleNames)
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order requires approval and matched the shipping cost rule
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED, $postPurchaseOrder->getStatus());

        // Verify rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(count($ruleNames), $appliedRules->getTotalCount());
        $appliedRulesItems = (array) $appliedRules->getItems();
        $ruleNamesCount = count($ruleNames);

        for ($i = 0; $i < $ruleNamesCount; $i++) {
            /** @var AppliedRule $appliedRule */
            $appliedRule = array_shift($appliedRulesItems);
            $this->assertFalse($appliedRule->isApproved());

            // Verify the correct rule was applied
            $this->assertContains($appliedRule->getRule()->getName(), $ruleNames);

            // Check the applied rule is requiring approval from the current role
            $appliedRuleApprovers = $this->appliedRulesApproverRepository->getListByAppliedRuleId(
                (int) $appliedRule->getId()
            );
            $appliedRuleApproversItems = $appliedRuleApprovers->getItems();
            $this->assertEquals(1, $appliedRuleApprovers->getTotalCount());
            /** @var AppliedRuleApprover $appliedRuleApprover */
            $appliedRuleApprover = reset($appliedRuleApproversItems);
            $this->assertEquals(
                AppliedRuleApproverInterface::APPROVER_TYPE_ROLE,
                $appliedRuleApprover->getApproverType()
            );

            // Verify role of the approver is the default role in the company
            $approverRoles = $this->roleRepository->getList(
                $this->searchCriteriaBuilder
                    ->addFilter('company_id', $purchaseOrder->getCompanyId())
                    ->addFilter('role_name', 'Approver Role')
                    ->create()
            )->getItems();
            /** @var Role $approverRole */
            $approverRole = reset($approverRoles);
            $this->assertEquals($approverRole->getId(), $appliedRuleApprover->getRoleId());
        }
    }

    /**
     * Verify a rule was not applied to the purchase order
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function verifyPurchaseOrderDoesNotMatchAnyRule()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('buyer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $id);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());

        // Verify no rules were applied
        $appliedRules = $this->appliedRulesRepository->getListByPurchaseOrderId(
            (int) $postPurchaseOrder->getEntityId()
        );
        $this->assertEquals(0, $appliedRules->getTotalCount());
    }

    /**
     * Test a Purchase Order with different product types:
     * - Simple x 2
     * - Virtual x 3
     * - Configurable Option 1 x 4
     * - Configurable Option 2 x 5
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/purchase_order_with_different_product_types.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_less_then_5.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_more_than_3.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_less_then_or_equal_to_1.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_more_than_or_equal_to_1.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationWithMultipleProductTypesNumberOfSkusRuleApplied()
    {
        $this->verifyPurchaseOrderMatches([
            'Number of SKUs Less Than 5',
            'Number of SKUs More Than 3',
            'Number of SKUs More Than Or Equal To 1',
        ]);
    }

    /**
     * Test a Purchase Order with Virtual Quote:
     * - Virtual x 4
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/purchase_order_with_virtual_quote.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_less_then_5.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_more_than_3.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_less_then_or_equal_to_1.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_more_than_or_equal_to_1.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testValidationWithVirtualNumberOfSkusRuleApplied()
    {
        $this->verifyPurchaseOrderMatches([
            'Number of SKUs Less Than 5',
            'Number of SKUs More Than Or Equal To 1',
            'Number of SKUs Less Than Or Equal To 1',
        ]);
    }
}
