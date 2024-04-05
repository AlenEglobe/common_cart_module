<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');
Resolver::getInstance()->requireDataFixture('Magento/GiftCard/_files/quote_with_items_saved.php');

$objectManager = Bootstrap::getObjectManager();

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

/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);

/** @var QuoteResource $quoteFactory */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_item_with_gift_card_items', 'reserved_order_id');

$companySubordinate = $customerRepository->get('veronica.costello@example.com', 1);

$quote->setStoreId(1)->getPayment()->setMethod('checkmo');
$quote->setCustomer($companySubordinate)->setCustomerIsGuest(false);

// assign addresses to company subordinate customer
foreach ($quote->getAddressesCollection() as $address) {
    /** @var $address \Magento\Quote\Api\Data\AddressInterface */
    $address->setCustomerAddressId(null);
    $address->setQuote($quote);
    $address->setCustomerId($companySubordinate->getId());
    $address->save();
}

$quote->collectTotals()->save();

// Create a new purchase order for the customer
$purchaseOrder = $purchaseOrderFactory->create();
$purchaseOrderData = [
    'increment_id' => '900000001',
    'company_id' => $company->getId(),
    'creator_id' => $companySubordinate->getId(),
    'status' => PurchaseOrderInterface::STATUS_PENDING,
    'quote_id' => $quote->getId(),
    'snapshot_quote' => $quote,
    'payment_method' => 'checkmo'
];
$dataObjectHelper->populateWithArray(
    $purchaseOrder,
    $purchaseOrderData,
    PurchaseOrderInterface::class
);
$purchaseOrderRepository->save($purchaseOrder);
