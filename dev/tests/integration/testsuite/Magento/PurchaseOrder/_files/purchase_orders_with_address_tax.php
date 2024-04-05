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
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\Tax\Model\Calculation\Rate;

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_shipping_method.php'
);
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company.php');
/** @var $objectManager \Magento\TestFramework\ObjectManager */
$objectManager = Bootstrap::getObjectManager();

//taxes initialization
$customerTaxClass = $objectManager->create(\Magento\Tax\Model\ClassModel::class)->load('Retail Customer', 'class_name');
$productTaxClass1 = $objectManager->create(\Magento\Tax\Model\ClassModel::class)->load('Taxable Goods', 'class_name');

//add tax class to product
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
$product = $productRepository->get('simple');
$product->setTaxClassId($productTaxClass1->getId())->save();

$rates = [
    'US' => [
        'percent' => 20,
        'code' => 'us'
    ],
    'CA' => [
        'percent' => 50,
        'code' => 'ca'
    ]
];
$ratesIds = [];
foreach ($rates as $country => $rateOptions) {
    //tax rate/rule creation
    $taxRate = [
        'tax_country_id' => $country,
        'tax_region_id' => '0',
        'tax_postcode' => '*',
        'code' => $rateOptions['code'],
        'rate' => $rateOptions['percent'],
    ];
    $rate = $objectManager->create(Rate::class)->setData($taxRate)->save();
    $ratesIds[] = $rate->getId();
}

$ruleData = [
    'code' => 'Test Rule',
    'priority' => '0',
    'position' => '0',
    'customer_tax_class_ids' => [$customerTaxClass->getId()],
    'product_tax_class_ids' => [$productTaxClass1->getId()],
    'tax_rate_ids' => $ratesIds,
];

$taxRule = $objectManager->create(Rule::class)->setData($ruleData)->save();

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
$quote->setIsActive(false);

$shippingAddress = $quote->getShippingAddress();

$shippingAddress
    ->setShippingMethod('flatrate_flatrate')
    ->setCollectShippingRates(true)
    ->save();

$quote->collectTotals()->save();

$purchaseOrderData = [
    'increment_id' => '900000001',
    'company_id' => $company->getId(),
    'creator_id' => $customer->getId(),
    'status' => PurchaseOrderInterface::STATUS_PENDING,
    'quote_id' => $quote->getId(),
    'snapshot_quote' => $quote,
    'grand_total' => $quote->getGrandTotal(),
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
