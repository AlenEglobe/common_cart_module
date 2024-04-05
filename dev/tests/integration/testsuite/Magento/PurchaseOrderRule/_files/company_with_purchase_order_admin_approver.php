<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company with purchase orders and a single rule with admin approval required
 */

use Magento\Company\Api\AclInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\AppliedRuleFactory;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Registry;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

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

/** @var AppliedRuleRepositoryInterface $appliedRuleRepository */
$appliedRuleRepository = $objectManager->get(AppliedRuleRepositoryInterface::class);

$company = $registry->registry('company');
$levelOneCustomer = $registry->registry('levelOneCustomer');
$levelTwoCustomer = $registry->registry('levelTwoCustomer');

/** @var RoleInterface $role */
$role1 = $objectManager->create(RoleInterface::class);
$role1->setCompanyId($company->getId());
$role1->setRoleName('Role 1');
/** @var RoleRepositoryInterface $roleRepository */
$roleRepository = $objectManager->get(RoleRepositoryInterface::class);
$roleRepository->save($role1);
$companyAcl->assignRoles($levelOneCustomer->getId(), [$role1]);

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
            "value" => "5",
            "is_value_processed" => false
        ]
    ]
];

// Create a rule for the company
$managerRule = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $managerRule,
    [
        'name' => 'Integration Test Admin Approval',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$managerRule->setAdminApprovalRequired(true);
$ruleRepository->save($managerRule);
$purchaseOrdersData = [
    [
        'company_id' => $company->getId(),
        'creator_id' => $levelTwoCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED,
        'grand_total' => 10,
        'auto_approve' => 0,
        'is_validate' => 0,
        'payment_method' => 'checkmo'
    ]
];

foreach ($purchaseOrdersData as $purchaseOrderData) {
    // Create a new quote for the customer
    /** @var Quote $quote */
    $quote = $quoteFactory->create();
    $quote->setStoreId(1)
        ->setIsActive(true)
        ->setCustomerId($purchaseOrderData['creator_id'])
        ->setIsMultiShipping(false)
        ->setReservedOrderId('reserved_order_id');
    $quote->getPayment()->setMethod('checkmo');
    $quote->collectTotals();
    $quoteRepository->save($quote);

    // Update the quote information on the purchase order
    $purchaseOrderData['quote_id'] = $quote->getId();
    $purchaseOrderData['snapshot_quote'] = $quote;

    // Create a new purchase order for the customer
    /** @var PurchaseOrderInterface $purchaseOrder */
    $purchaseOrder = $purchaseOrderFactory->create();

    $dataObjectHelper->populateWithArray(
        $purchaseOrder,
        $purchaseOrderData,
        PurchaseOrderInterface::class
    );

    $purchaseOrderRepository->save($purchaseOrder);

    // Apply rule to the purchase order
    $appliedRuleInstance = $appliedRuleFactory->create();
    $appliedRuleInstance->setPurchaseOrderId((int) $purchaseOrder->getEntityId())
        ->setRuleId((int) $managerRule->getId())
        ->setAdminApprovalRequired($managerRule->isAdminApprovalRequired());
    $appliedRuleRepository->save($appliedRuleInstance);
}
