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
use Magento\Framework\App\State;
use Magento\Catalog\Api\ProductRepositoryInterface;

Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer.php');
Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_address.php');
Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_virtual.php');
Resolver::getInstance()->requireDataFixture('Magento/CatalogRule/_files/catalog_rule_6_off_logged_user.php');

$objectManager = Bootstrap::getObjectManager();
/** @var State $state */
$state = $objectManager->get(State::class);
$state->setAreaCode('frontend');
/** @var \Magento\Quote\Model\Quote\Address $quoteShippingAddress */
$quoteShippingAddress = $objectManager->create(
    \Magento\Quote\Model\Quote\Address::class
);
/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(
    \Magento\Customer\Api\CustomerRepositoryInterface::class
);
/** @var \Magento\Customer\Api\AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->create(
    \Magento\Customer\Api\AddressRepositoryInterface::class
);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
$product = $productRepository->get('virtual-product');
$quoteShippingAddress->importCustomerAddressData($addressRepository->getById(1));
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = Bootstrap::getObjectManager()->create(CustomerRegistry::class);
$customer = $customerRegistry->retrieve(1);
/** @var \Magento\Quote\Model\Quote $quote */
$quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
$quote->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(false)
    ->assignCustomerWithAddressChange($customerRepository->getById($customer->getId()))
    ->setShippingAddress($quoteShippingAddress)
    ->setBillingAddress($quoteShippingAddress)
    ->setCheckoutMethod($customer->getMode())
    ->setPasswordHash($customer->encryptPassword($customer->getPassword()))
    ->setReservedOrderId('test_order_with_virtual_product')
    ->setEmail('store@example.com')
    ->addProduct($product->load($product->getId()), 1);

$quote->collectTotals()->save();

/** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
$quoteIdMask = $objectManager
    ->create(\Magento\Quote\Model\QuoteIdMaskFactory::class)
    ->create();
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setDataChanges(true);
$quoteIdMask->save();

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
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');
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
$quote->collectTotals()->save();

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
