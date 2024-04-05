<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\ObjectManager;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\DataObjectHelper;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\CustomerBalance\Model\Balance;
use Magento\GiftCardAccount\Model\Giftcardaccount;
use Magento\Store\Model\StoreManagerInterface;

Resolver::getInstance()->requireDataFixture(
    'Magento/GiftCardAccount/_files/giftcardaccount.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address.php'
);

Resolver::getInstance()->requireDataFixture(
    'Magento/Company/_files/company.php'
);

/** @var $objectManager ObjectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->create(CustomerRegistry::class);
/** @var $customerBalance Balance */
$customerBalance = $objectManager->create(Balance::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
/** @var PurchaseOrderInterfaceFactory $purchaseOrderFactory */
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);
/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var  Giftcardaccount $giftCardAccount */
$giftCardAccount = $objectManager->create(Giftcardaccount::class);

$customer = $customerRegistry->retrieveByEmail('customer@example.com', 1);

/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@magento.com', 'company_email');
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');
$companyCustomer = $customerFactory->create();

//create customer balance
$customerBalance->setCustomerId(
    $customer->getId()
);

$customerBalance->setAmountDelta(10);
$customerBalance->setWebsiteId(
    Bootstrap::getObjectManager()->get(
        StoreManagerInterface::class
    )->getStore()->getWebsiteId()
);
$customerBalance->save();

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

$quote->getPayment()->setMethod('checkmo');

//set use customer balance
$quote->setUseCustomerBalance(true);

//add giftcards to quote
$giftCardAccount->loadByCode('giftcardaccount_fixture');
$giftCardAccount->addToCart(true, $quote);

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
