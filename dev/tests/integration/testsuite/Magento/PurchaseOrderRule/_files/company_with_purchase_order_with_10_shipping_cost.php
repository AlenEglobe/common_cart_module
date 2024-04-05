<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company with purchase orders and a single rule, single role and single approver
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
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);

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

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

$company = $registry->registry('company');

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

$purchaseOrdersData = [
    [
        'company_id' => $company->getId(),
        'creator_id' => $buyerCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED,
        'grand_total' => 20,
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
    $quote->getShippingAddress()->setShippingInclTax(10);
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
}
