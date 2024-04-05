<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();
/** @var \Magento\SalesRule\Model\RuleFactory $salesRule */
$salesRuleFactory = $objectManager->get(\Magento\SalesRule\Model\RuleFactory::class);

/** @var \Magento\SalesRule\Model\Rule $salesRule */
$salesRule = $salesRuleFactory->create();

$data = [
    'name' => 'Test Sales Rule',
    'is_active' => true,
    'website_ids' => [
        \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            \Magento\Store\Model\StoreManagerInterface::class
        )->getStore()->getWebsiteId()
    ],
    'customer_group_ids' => [\Magento\Customer\Model\GroupManagement::NOT_LOGGED_IN_ID, 1],
    'coupon_type' => \Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON,
    'simple_action' => \Magento\SalesRule\Model\Rule::BY_PERCENT_ACTION,
    'discount_amount' => 10,
    'discount_step' => 1
];

$salesRule->loadPost($data)->setUseAutoGeneration(false)->save();
$objectManager->get(\Magento\Framework\Registry::class)->unregister('Magento/Checkout/_file/discount_10percent');
$objectManager->get(\Magento\Framework\Registry::class)
    ->register('Magento/Checkout/_file/discount_10percent', $salesRule->getRuleId());

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_address_saved.php');
/** @var QuoteFactory $quoteFactory */
$quoteFactory = Bootstrap::getObjectManager()->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = Bootstrap::getObjectManager()->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

$salesRuleFactory = $objectManager->get(\Magento\SalesRule\Model\RuleFactory::class);
$salesRule = $salesRuleFactory->create();
$salesRuleId = $objectManager->get(\Magento\Framework\Registry::class)
    ->registry('Magento/Checkout/_file/discount_10percent');
$salesRule->load($salesRuleId);

$quote->collectTotals()->save();

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company.php');

/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->create(CustomerRegistry::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
$customer = $customerRegistry->retrieveByEmail('customer@example.com', 1);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(\Magento\Framework\Api\DataObjectHelper::class);
/** @var PurchaseOrderInterfaceFactory $purchaseOrderFactory */
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);
/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
/** @var PurchaseOrderQuoteConverter $purchaseOrderQuoteConverter */
$purchaseOrderQuoteConverter = $objectManager->get(PurchaseOrderQuoteConverter::class);
/** @var JsonSerializer $jsonSerializer */
$jsonSerializer = $objectManager->get(JsonSerializer::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@magento.com', 'company_email');
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
$companyCustomer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $companyCustomer,
    [
        'id' => $customer->getId(),
        'firstname' => $customer->getFirstname(),
        'lastname' => $customer->getLastname(),
        'email' => $customer->getEmail(),
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
$customerRepository->save($companyCustomer, 'password');
$companyCustomer = $customerRepository->get($customer->getEmail());

/** @var \Magento\Quote\Api\CartRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);

$quote->getPayment()->setMethod('checkmo');

$rate = $objectManager->get(\Magento\Quote\Model\Quote\Address\Rate::class);
$shippingAddress = $quote->getShippingAddress();

$rate->setPrice(0)
    ->setCode('flatrate_flatrate')
    ->setAddressId($shippingAddress->getId())
    ->save();

$shippingAddress
    ->setShippingMethod('flatrate_flatrate')
    ->setShippingDescription('Flat Rate - Fixed')
    ->save();

$shippingAddress->setBaseShippingAmount($rate->getPrice());
$shippingAddress->setShippingAmount($rate->getPrice());

$quote->collectTotals()
    ->setIsActive(false)
    ->save();

$purchaseOrderData = [
    'increment_id' => '900000001',
    'company_id' => $company->getId(),
    'creator_id' => $customer->getId(),
    'status' => PurchaseOrderInterface::STATUS_PENDING,
    'quote_id' => $quote->getId(),
    'snapshot_quote' => $quote,
    'payment_method' => 'checkmo'
];

// Create a new purchase order for the customer
$purchaseOrder = $purchaseOrderFactory->create();
$dataObjectHelper->populateWithArray(
    $purchaseOrder,
    $purchaseOrderData,
    PurchaseOrderInterface::class
);
$purchaseOrderRepository->save($purchaseOrder);
