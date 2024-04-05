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

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_address.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_bundle_and_options.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_simple_product_saved.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_saved.php'
);
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company.php');

$objectManager = Bootstrap::getObjectManager();
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

$purchaseOrderData = [
    [
        'reserved_order_id' => 'test_order_with_virtual_product',
        'data' => [
            'increment_id' => '900000001',
            'company_id' => $company->getId(),
            'creator_id' => $customer->getId(),
            'status' => PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED,
            'payment_method' => 'checkmo'
        ]
    ],
    [
        'reserved_order_id' => 'test_order_1',
        'data' => [
            'increment_id' => '900000002',
            'company_id' => $company->getId(),
            'creator_id' => $customer->getId(),
            'status' => PurchaseOrderInterface::STATUS_PENDING,
            'payment_method' => 'checkmo'
        ]
    ],
    [
        'reserved_order_id' => 'test_order_bundle',
        'data' => [
            'increment_id' => '900000003',
            'company_id' => $company->getId(),
            'creator_id' => $customer->getId(),
            'status' => PurchaseOrderInterface::STATUS_CANCELED,
            'payment_method' => 'checkmo'
        ]
    ],
    [
        'reserved_order_id' => 'test_order_with_simple_product_without_address',
        'data' => [
            'increment_id' => '900000004',
            'company_id' => $company->getId(),
            'creator_id' => $customer->getId(),
            'status' => PurchaseOrderInterface::STATUS_REJECTED,
            'payment_method' => 'checkmo'
        ]
    ],
    [
        'reserved_order_id' => 'test_order_with_virtual_product_without_address',
        'data' => [
            'increment_id' => '900000005',
            'company_id' => $company->getId(),
            'creator_id' => $customer->getId(),
            'status' => PurchaseOrderInterface::STATUS_APPROVED,
            'payment_method' => 'checkmo'
        ]
    ]
];

foreach ($purchaseOrderData as $data) {
    $quote = $quoteFactory->create();
    $quoteResource->load($quote, $data['reserved_order_id'], 'reserved_order_id');
    $quote->getPayment()->setMethod('checkmo');
    $quote->collectTotals()->save();

    $data['data']['quote_id'] = $quote->getId();
    $data['data']['snapshot_quote'] = $quote;

    // Create a new purchase order for the customer
    $purchaseOrder = $purchaseOrderFactory->create();
    $dataObjectHelper->populateWithArray(
        $purchaseOrder,
        $data['data'],
        PurchaseOrderInterface::class
    );
    $purchaseOrderRepository->save($purchaseOrder);
}
