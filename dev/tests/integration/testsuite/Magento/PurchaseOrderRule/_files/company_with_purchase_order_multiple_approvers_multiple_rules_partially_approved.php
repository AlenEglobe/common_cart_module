<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company, with purchase orders with two applied rules with different roles and customers within
 */

use Magento\Company\Api\AclInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\AppliedRuleApprover;
use Magento\PurchaseOrderRule\Model\AppliedRuleFactory;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\ResourceModel\Structure\Tree as StructureTree;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

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

/** @var AppliedRuleApproverRepositoryInterface $appliedRuleApproverRepository */
$appliedRuleApproverRepository = $objectManager->get(AppliedRuleApproverRepositoryInterface::class);

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
$rule1 = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $rule1,
    [
        'name' => 'Admin Approval',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule1->setAdminApprovalRequired(true);
$ruleRepository->save($rule1);

// Create a rule for the company
$rule2 = $ruleFactory->create();
$dataObjectHelper->populateWithArray(
    $rule2,
    [
        'name' => 'Admin Approval 2',
        'description' => 'Integration Test Rule Description',
        'is_active' => 1,
        'company_id' => $company->getId(),
        'conditions_serialized' => json_encode($conditions)
    ],
    RuleInterface::class
);
// Have to set independently as the populateDataWithArray attempts to use reflection on array
$rule2->setManagerApprovalRequired(true);
$ruleRepository->save($rule2);

$buyerCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $buyerCustomer,
    [
        'firstname' => 'Buyer',
        'lastname' => 'Buyer',
        'email' => 'buyer@example.com',
        'website_id' => 1,
        'extension_attributes' => [
            'company_attributes' => [
                'company_id' => $company->getId(),
                'status' => 1,
                'job_title' => 'Sales Rep'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($buyerCustomer, 'password');
$buyerCustomer = $customerRepository->get('buyer@example.com');

/*
 * Move the buyerCustomer so that they are a subordinate of the levelTwoCustomer.
 */
/** @var StructureManager $structureManager */
$objectManager->removeSharedInstance(StructureTree::class);
$structureManager = $objectManager->create(StructureManager::class);

$buyerCustomerStructure = $structureManager->getStructureByCustomerId($buyerCustomer->getId());
$levelTwoCustomerStructure = $structureManager->getStructureByCustomerId($levelTwoCustomer->getId());
$structureManager->moveNode($buyerCustomerStructure->getId(), $levelTwoCustomerStructure->getId(), true);

$purchaseOrdersData = [
    [
        'company_id' => $company->getId(),
        'creator_id' => $buyerCustomer->getId(),
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

    // Apply both rules to the purchase order
    $appliedRuleInstance = $appliedRuleFactory->create();
    $appliedRuleInstance->setPurchaseOrderId((int) $purchaseOrder->getEntityId())
        ->setRuleId((int) $rule1->getId())
        ->setAdminApprovalRequired(true);
    $appliedRuleRepository->save($appliedRuleInstance);

    $appliedRuleInstance = $appliedRuleFactory->create();
    $appliedRuleInstance->setPurchaseOrderId((int) $purchaseOrder->getEntityId())
        ->setRuleId((int) $rule2->getId())
        ->setManagerApprovalRequired(true);
    $appliedRuleRepository->save($appliedRuleInstance);

    // Approve rule 2 (the managers) approval rule
    $appliedRuleApprovers = $appliedRuleApproverRepository->getListByAppliedRuleId((int) $appliedRuleInstance->getId());
    /** @var AppliedRuleApprover $approver */
    foreach ($appliedRuleApprovers->getItems() as $approver) {
        $approver->approve($levelTwoCustomer->getId());
        $appliedRuleApproverRepository->save($approver);
    }
}
