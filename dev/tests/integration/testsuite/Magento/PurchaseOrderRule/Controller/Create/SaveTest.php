<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\Create;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleManagementInterface;
use Magento\Company\Model\CompanyUser;
use Magento\Company\Model\RoleRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Controller\Create\Save as SaveController;
use Magento\PurchaseOrderRule\Model\Rule\GetRule;
use Magento\PurchaseOrderRule\Model\RuleRepository;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RequestInterface|HttpRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    private $request;

    /**
     * @var RedirectFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultRedirectFactory;

    /**
     * @var Redirect|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultRedirect;

    /**
     * @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $messageManager;

    /**
     * @var CompanyUser|\PHPUnit\Framework\MockObject\MockObject
     */
    private $companyUser;

    /**
     * @var RoleInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $companyRole;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var SaveController
     */
    private $controller;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = ObjectManager::getInstance();

        $this->request = $this->getMockForAbstractClass(
            RequestInterface::class,
            [],
            '',
            true,
            true,
            true,
            ['getPostValue']
        );
        $this->resultRedirectFactory = $this->createMock(RedirectFactory::class);
        $this->resultRedirect = $this->createMock(Redirect::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->companyUser = $this->createMock(CompanyUser::class);
        $this->companyRole = $this->createMock(RoleInterface::class);

        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->session = $this->objectManager->get(Session::class);
        $getRule = $this->objectManager->create(
            GetRule::class,
            [
                'companyUser' => $this->companyUser
            ]
        );

        $context = $this->objectManager->create(
            Context::class,
            [
                'request' => $this->request,
                'messageManager' => $this->messageManager,
                'resultRedirectFactory' => $this->resultRedirectFactory
            ]
        );

        $this->controller = $this->objectManager->create(
            SaveController::class,
            [
                'context' => $context,
                'getRule' => $getRule
            ]
        );
    }

    /**
     * @param string $field
     * @param string $value
     * @return RuleInterface[]
     * @throws LocalizedException
     */
    private function findRules(string $field, string $value): array
    {
        return $this->objectManager->get(RuleRepository::class)->getList(
            $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter($field, $value)->create()
        )->getItems();
    }

    /**
     * Data provider for save validation of new and existing rules.
     *
     * @return array
     */
    public function saveRuleDataProvider()
    {
        return [
            'save_new_valid_rule' => [
                'rule_name' => 'New Purchase Order Rule',
                'active_rules_count' => 2,
                'success_message' => 'The approval rule has been created.'
            ],
            'save_changes_to_existing_rule' => [
                'rule_name' => 'Integration Test Rule Name',
                'active_rules_count' => 1,
                'success_message' => 'The approval rule has been updated.'
            ]
        ];
    }

    /**
     * Retrieve the role ID for the created company
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getRoleId()
    {
        $this->session->loginById($this->customerRepository->get('john.doe@example.com')->getId());
        $rolesSearchResult = $this->objectManager->get(RoleRepository::class)->getList(
            $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter(
                'company_id',
                $this->objectManager->get(CompanyUser::class)->getCurrentCompanyId()
            )->create()
        );

        if ($rolesSearchResult->getTotalCount() === 0) {
            $this->fail('Company does not contain at least one role to create rule for.');
        }

        /* @var RoleInterface $role */
        $role = current($rolesSearchResult->getItems());
        return $role->getId();
    }

    /**
     * Error an error message is present and a redirect to the referral URL occurs
     *
     * @param $message
     * @throws LocalizedException
     */
    private function assertErrorMethodAndRedirect($message)
    {
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__($message));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setRefererUrl');

        $this->controller->execute();
    }

    /**
     * Test that when the form is submitted without a name the correct message and redirect response are followed
     */
    public function testMissingRequiredName()
    {
        $this->request->expects($this->atLeastOnce())
            ->method('getParams')
            ->willReturn(['name' => '']);

        $this->assertErrorMethodAndRedirect('The approval rule must have a name.');
    }

    /**
     * Test to verify the output of missing conditions array but with the name present
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testNullConditionsArray()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [$this->getRoleId()],
                'is_active' => '1',
                'applies_to_all' => '1',
                'conditions' => null
            ]);
        $this->assertErrorMethodAndRedirect('Required field is not complete.');
    }

    /**
     * Test to verify the output of missing conditions array but with the name present
     */
    public function testMissingParamsConditionsArray()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'operator' => '>',
                        'value' => null
                    ]
                ]
            ]);

        $this->assertErrorMethodAndRedirect('Required data is missing from a rule condition.');
    }

    /**
     * Verify incomplete conditions array produces error
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testMissingConditionOperator()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [$this->getRoleId()],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('Required data is missing from a rule condition.');
    }

    /**
     * Test a condition which does not exist in the system
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testInvalidRuleConditionName()
    {
        $roleId = $this->getRoleId();
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [$roleId],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'does_not_exist',
                        'operator' => '>',
                        'value' => '100',
                        'currency_code' => null
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('Unknown condition type: does_not_exist');
    }

    /**
     * Test a negative order total value, which is invalid
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testNegativeOrderTotalCondition()
    {
        $roleId = $this->getRoleId();
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [$roleId],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'operator' => '>',
                        'value' => '-100',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('Rule is incorrectly configured.');
    }

    /**
     * Test an invalid approver for the current users company
     */
    public function testMissingRequiredApprover()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('At least one approver is required to configure this rule.');
    }

    /**
     * Test an invalid approver, which doesn't exist
     */
    public function testInvalidApprover()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [9999999999],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('The company role with ID "9999999999" does not exist.');
    }

    /**
     * Test that trying to use an approver role for another company fails
     */
    public function testApproverRoleFromOtherCompany()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [10],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('The company role with ID "10" does not exist.');
    }

    /**
     * Test that not applying a rule to all and not specifying any role IDs throws an error
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testMissingAppliesToRoles()
    {
        $roleId = $this->getRoleId();

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [$roleId],
                'applies_to_all' => '0',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);
        $this->assertErrorMethodAndRedirect('This rule must apply to at least one or all roles.');
    }

    /**
     * Verify a role from a different company triggers an error when used in "Applies To"
     */
    public function testAppliesToRoleFromDifferentCompany()
    {
        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => 'Test Rule',
                'approvers' => [12],
                'applies_to_all' => '1',
                'applies_to' => [10],
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->companyRole->expects($this->any())
            ->method('getCompanyId')
            ->willReturn(1);

        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn(2);

        $this->assertErrorMethodAndRedirect('The company role with ID "10" does not exist.');
    }

    /**
     * Create a valid rule for a logged in customer and ensure a success message is received and rule is created
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testSaveValidRuleWithSpecificAppliesTo()
    {
        $roleId = $this->getRoleId();
        $ruleName = 'Created Purchase Order Rule';

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' =>  $ruleName,
                'is_active' => '1',
                'description' => '',
                'approvers' => [$roleId],
                'applies_to_all' => '0',
                'applies_to' => [$roleId],
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $companyId = (int) $companyAdmin->getExtensionAttributes()
            ->getCompanyAttributes()
            ->getCompanyId();
        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($companyId);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The approval rule has been created.'));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();

        $ruleSearch = $this->objectManager->get(RuleRepository::class)->getList(
            $this->objectManager->get(SearchCriteriaBuilder::class)->addFilter('name', $ruleName)->create()
        );

        $this->assertEquals($ruleSearch->getTotalCount(), 1);

        /* @var RuleInterface $rule */
        $rule = current($ruleSearch->getItems());

        // Assert basic information about the created rule
        $this->assertEquals($ruleName, $rule->getName());
        $this->assertNull($rule->getDescription());
        $this->assertNotEmpty($rule->getConditionsSerialized());
        $this->assertEquals($companyId, $rule->getCompanyId());
        $this->assertEquals([$roleId], $rule->getApproverRoleIds());
        $this->assertFalse($rule->isAppliesToAll());
        $this->assertEquals([$roleId], $rule->getAppliesToRoleIds());
        $this->assertTrue($rule->isActive());
        $this->assertEquals($this->session->getCustomerId(), $rule->getCreatedBy());

        // Verify the condition that was generated contains the correct information regarding the input
        $storedCondition = json_decode($rule->getConditionsSerialized(), true);
        $this->assertArrayHasKey('conditions', $storedCondition);
        $this->assertCount(1, $storedCondition['conditions']);
        $this->assertEquals('grand_total', $storedCondition['conditions'][0]['attribute']);
        $this->assertEquals('>', $storedCondition['conditions'][0]['operator']);
        $this->assertEquals('100', $storedCondition['conditions'][0]['value']);
    }

    /**
     * Verify a rule is able to remove admin approval requirement after it is created
     *
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_admin_rule.php
     */
    public function testAbleToUnsetAdminAsApprover()
    {
        /* @var RuleRepository $ruleRepository */
        $ruleRepository = $this->objectManager->get(RuleRepository::class);

        /** @var RoleManagementInterface $roleManagement */
        $roleManagement = $this->objectManager->get(RoleManagementInterface::class);

        $companyAdmin = $this->customerRepository->get('admin@magento.com');
        $companyId = (int) $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $defaultRoleId = $roleManagement->getCompanyDefaultRole($companyId)->getId();

        $this->session->loginById($companyAdmin->getId());

        $rules = $ruleRepository->getByCompanyId($companyId)->getItems();

        $this->assertCount(1, $rules);

        $rule = current($rules);
        $this->assertTrue($rule->isAdminApprovalRequired());

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => $rule->getName(),
                'rule_id' => (int)$rule->getId(),
                'is_active' => '1',
                'description' => '',
                'approvers' => [$defaultRoleId],
                'applies_to_all' => '1',
                'conditions' => json_decode($rule->getConditionsSerialized(), true)['conditions']
            ]);

        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($companyId);

        $this->companyRole->expects($this->any())
            ->method('getCompanyId')
            ->willReturn($companyId);

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();

        $rules = $ruleRepository->getByCompanyId($companyId)->getItems();
        $this->assertCount(1, $rules);
        $rule = current($rules);

        $this->assertFalse($rule->isAdminApprovalRequired());
        $this->assertEquals([$defaultRoleId], $rule->getApproverRoleIds());
    }

    /**
     * Verify a rule is able to remove manager approval requirement after it is created
     *
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_manager_rule.php
     */
    public function testAbleToUnsetManagerAsApprover()
    {
        /* @var RuleRepository $ruleRepository */
        $ruleRepository = $this->objectManager->get(RuleRepository::class);

        /** @var RoleManagementInterface $roleManagement */
        $roleManagement = $this->objectManager->get(RoleManagementInterface::class);

        $companyAdmin = $this->customerRepository->get('admin@magento.com');
        $companyId = (int) $companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $defaultRoleId = $roleManagement->getCompanyDefaultRole($companyId)->getId();

        $this->session->loginById($companyAdmin->getId());

        $rules = $ruleRepository->getByCompanyId($companyId)->getItems();

        $this->assertCount(1, $rules);

        $rule = current($rules);
        $this->assertTrue($rule->isManagerApprovalRequired());

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => $rule->getName(),
                'rule_id' => (int)$rule->getId(),

                'is_active' => '1',
                'description' => '',
                'approvers' => [$defaultRoleId],
                'applies_to_all' => '1',
                'conditions' => json_decode($rule->getConditionsSerialized(), true)['conditions']
            ]);

        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($companyId);

        $this->companyRole->expects($this->any())
            ->method('getCompanyId')
            ->willReturn($companyId);

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();

        $rules = $ruleRepository->getByCompanyId($companyId)->getItems();
        $this->assertCount(1, $rules);
        $rule = current($rules);

        $this->assertFalse($rule->isManagerApprovalRequired());
        $this->assertEquals([$defaultRoleId], $rule->getApproverRoleIds());
    }

    /**
     * Create a valid admin rule for a logged in customer and ensure a success message is received and rule is created
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testSaveValidAdminRule()
    {
        /* @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        /* @var RuleRepository $ruleRepository */
        $ruleRepository = $this->objectManager->get(RuleRepository::class);
        /** @var RoleManagementInterface $roleManagement */
        $roleManagement = $this->objectManager->get(RoleManagementInterface::class);

        $roleId = $this->getRoleId();
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        $adminRoleId = $roleManagement->getAdminRole()->getId();

        $ruleName = 'Created Purchase Order Rule';

        $companyId = (int) $companyAdmin->getExtensionAttributes()
            ->getCompanyAttributes()
            ->getCompanyId();
        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($companyId);

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => $ruleName,
                'is_active' => '1',
                'description' => '',
                'approvers' => [$adminRoleId],
                'applies_to' => [$roleId],
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The approval rule has been created.'));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();

        $ruleSearch = $ruleRepository->getList(
            $searchCriteriaBuilder
                ->addFilter('name', $ruleName)
                ->create()
        );

        $this->assertEquals($ruleSearch->getTotalCount(), 1);

        /* @var RuleInterface $rule */
        $rule = current($ruleSearch->getItems());

        // Assert basic information about the created rule
        $this->assertEquals($ruleName, $rule->getName());
        $this->assertNull($rule->getDescription());
        $this->assertNotEmpty($rule->getConditionsSerialized());
        $this->assertEquals($companyId, $rule->getCompanyId());
        $this->assertEquals([], $rule->getApproverRoleIds());
        $this->assertTrue($rule->isAdminApprovalRequired());
        $this->assertTrue($rule->isActive());

        // Verify the condition that was generated contains the correct information regarding the input
        $storedCondition = json_decode($rule->getConditionsSerialized(), true);
        $this->assertArrayHasKey('conditions', $storedCondition);
        $this->assertCount(1, $storedCondition['conditions']);
        $this->assertEquals('grand_total', $storedCondition['conditions'][0]['attribute']);
        $this->assertEquals('>', $storedCondition['conditions'][0]['operator']);
        $this->assertEquals('100', $storedCondition['conditions'][0]['value']);
    }

    /**
     * Create a valid manager rule for a logged in customer and ensure a success message is received and rule is created
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testSaveValidManagerRule()
    {
        /* @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        /* @var RuleRepository $ruleRepository */
        $ruleRepository = $this->objectManager->get(RuleRepository::class);
        /** @var RoleManagementInterface $roleManagement */
        $roleManagement = $this->objectManager->get(RoleManagementInterface::class);

        $roleId = $this->getRoleId();
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        $managerRoleId = $roleManagement->getManagerRole()->getId();

        $ruleName = 'Created Purchase Order Rule';

        $companyId = (int) $companyAdmin->getExtensionAttributes()
            ->getCompanyAttributes()
            ->getCompanyId();
        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($companyId);

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'name' => $ruleName,
                'is_active' => '1',
                'approvers' => [$managerRoleId],
                'applies_to' => [$roleId],
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The approval rule has been created.'));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();

        $ruleSearch = $ruleRepository->getList(
            $searchCriteriaBuilder
                ->addFilter('name', $ruleName)
                ->create()
        );

        $this->assertEquals($ruleSearch->getTotalCount(), 1);

        /* @var RuleInterface $rule */
        $rule = current($ruleSearch->getItems());

        // Assert basic information about the created rule
        $this->assertEquals($ruleName, $rule->getName());
        $this->assertNull($rule->getDescription());
        $this->assertNotEmpty($rule->getConditionsSerialized());
        $this->assertEquals($companyId, $rule->getCompanyId());
        $this->assertEquals([], $rule->getApproverRoleIds());
        $this->assertTrue($rule->isManagerApprovalRequired());
        $this->assertTrue($rule->isActive());

        // Verify the condition that was generated contains the correct information regarding the input
        $storedCondition = json_decode($rule->getConditionsSerialized(), true);
        $this->assertArrayHasKey('conditions', $storedCondition);
        $this->assertCount(1, $storedCondition['conditions']);
        $this->assertEquals('grand_total', $storedCondition['conditions'][0]['attribute']);
        $this->assertEquals('>', $storedCondition['conditions'][0]['operator']);
        $this->assertEquals('100', $storedCondition['conditions'][0]['value']);
    }

    /**
     * Test that trying to save changes to existing rule from other company
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     */
    public function testSaveChangesToNotExistingRule()
    {
        $roleManagement = $this->objectManager->get(RoleManagementInterface::class);
        $managerRoleId = $roleManagement->getManagerRole()->getId();
        $nonExistingRuleId = 99999;

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'rule_id' => $nonExistingRuleId,
                'name' => 'Test rule',
                'is_active' => '1',
                'approvers' => [$managerRoleId],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->companyRole->expects($this->any())
            ->method('getCompanyId')
            ->willReturn(1);

        $this->companyUser->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn(1);

        $this->assertErrorMethodAndRedirect('Rule with id "' . $nonExistingRuleId . '" does not exist.');
    }

    /**
     * Test that trying to save changes to existing rule from other company
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     */
    public function testSaveChangesToRuleFromOtherCompany()
    {
        /* @var RuleInterface $rule */
        $rule = current($this->findRules('name', 'Integration Test Rule Name'));
        $roleId = $this->getRoleId();

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'rule_id' => (int)$rule->getId(),
                'name' => 'Test rule',
                'is_active' => '1',
                'approvers' => [$roleId],
                'applies_to' => [$roleId],
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->assertErrorMethodAndRedirect(sprintf('The approval rule with ID "%s" does not exist.', $rule->getId()));
    }

    /**
     *  Checks that existent rule save do not override creator id.
     *
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     */
    public function testSaveExistingRuleDoesNotChangeCreator()
    {
        $roleId = $this->getRoleId();
        $rule = current($this->findRules('name', 'Integration Test Rule Name'));
        $this->assertNotNull($rule->getCreatedBy());
        $companyUser = $this->customerRepository->get('veronica.costello@example.com');
        $this->session->loginById($companyUser->getId());

        $this->request->expects($this->any())
            ->method('getParams')
            ->willReturn([
                'rule_id' => (int)$rule->getId(),
                'name' => $rule->getName(),
                'is_active' => '1',
                'approvers' => [$roleId],
                'applies_to_all' => '1',
                'conditions' => [
                    [
                        'attribute' => 'grand_total',
                        'value' => '100',
                        'operator' => '>',
                        'currency_code' => 'USD'
                    ]
                ]
            ]);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The approval rule has been updated.'));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule');

        $this->controller->execute();
        $rule = current($this->findRules('name', 'Integration Test Rule Name'));
        $this->assertNotEquals($this->session->getCustomerId(), $rule->getCreatedBy());
    }
}
