<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company, with purchase orders with a single applied rule which has a single role with multiple customers /
 * approvers within.
 */

use Magento\Company\Api\AclInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\AppliedRuleFactory;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\Api\DataObjectHelper;

Resolver::getInstance()->requireDataFixture(
    'Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php'
);

$objectManager = Bootstrap::getObjectManager();
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
$levelOneCustomer = $customerRepository->get('veronica.costello@example.com');
$levelTwoCustomer = $customerRepository->get('alex.smith@example.com');

/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);

/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);

/** @var QuoteRepository $quoteRepository */
$quoteRepository = $objectManager->get(QuoteRepository::class);

/** @var PurchaseOrderInterfaceFactory $purchaseOrderFactory */
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

/** @var PurchaseOrderQuoteConverter $purchaseOrderQuoteConverter */
$purchaseOrderQuoteConverter = $objectManager->get(PurchaseOrderQuoteConverter::class);

/** @var JsonSerializer $jsonSerializer */
$jsonSerializer = $objectManager->get(JsonSerializer::class);

/** @var AclInterface $companyAcl */
$companyAcl = $objectManager->get(AclInterface::class);

/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->get(RuleRepositoryInterface::class);

/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);

/** @var AppliedRuleFactory $appliedRuleFactory */
$appliedRuleFactory = $objectManager->get(AppliedRuleFactory::class);

/** @var RoleInterface $role */
$role1 = $objectManager->create(RoleInterface::class);
$role1->setCompanyId($company->getId());
$role1->setRoleName('Role 1');
$role2 = $objectManager->create(RoleInterface::class);
$role2->setCompanyId($company->getId());
$role2->setRoleName('Role 2');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$roleRepository->save($role1);
$roleRepository->save($role2);
$companyAcl->assignRoles($levelOneCustomer->getId(), [$role1]);
$companyAcl->assignRoles($levelTwoCustomer->getId(), [$role1]);

// Build the conditions array to be encoded
$conditions = [
    "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Combine",
    "attribute" => null,
    "operator" => null,
    "value" => "1",
    "is_value_processed" => null,
    "aggregator" => "all",
    "conditions" => [
        [
            "type" => "Magento\\PurchaseOrderRule\\Model\\Rule\\Condition\\Address",
            "attribute" => "grand_total",
            "operator" => ">",
            "value" => "1",
            "is_value_processed" => false
        ]
    ]
];

$rulesData = [
    [
        'name' => 'Rule with Creator as Approver',
        'approval_role' => [$role1->getId()],
        'manager_approval' => false,
        'admin_approval' => false
    ],
    [
        'name' => 'Rule with Manager as Approver',
        'approval_role' => [],
        'manager_approval' => true,
        'admin_approval' => false
    ],
    [
        'name' => 'Rule with Admin as Approver',
        'approval_role' => [],
        'manager_approval' => false,
        'admin_approval' => true
    ],
    [
        'name' => 'Rule with Creator and Admin as Approver',
        'approval_role' => [$role1->getId()],
        'manager_approval' => false,
        'admin_approval' => true
    ],
    [
        'name' => 'Rule with not Creator role as Approver',
        'approval_role' => [$role2->getId()],
        'manager_approval' => false,
        'admin_approval' => false
    ]
];
// Create a rule for the company
foreach ($rulesData as $ruleData) {
    $rule = $ruleFactory->create();
    $dataObjectHelper->populateWithArray(
        $rule,
        [
            'name' => $ruleData['name'],
            'description' => 'Creator as approver test rule',
            'is_active' => 1,
            'company_id' => $company->getId(),
            'conditions_serialized' => json_encode($conditions)
        ],
        RuleInterface::class
    );
    $rule->setApproverRoleIds($ruleData['approval_role']);
    $rule->setManagerApprovalRequired($ruleData['manager_approval']);
    $rule->setAdminApprovalRequired($ruleData['admin_approval']);
    $rule->setAppliesToAll(true);
    $ruleRepository->save($rule);
}
