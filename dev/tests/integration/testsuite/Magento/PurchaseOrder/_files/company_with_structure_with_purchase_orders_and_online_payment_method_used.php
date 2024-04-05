<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\DataObjectHelper;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address.php'
);
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

$objectManager = Bootstrap::getObjectManager();
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');
/** @var QuoteRepository $quoteRepository */
$quoteRepository = $objectManager->get(QuoteRepository::class);

/** @var PurchaseOrderInterfaceFactory $purchaseOrderFactory */
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

/** @var PurchaseOrderQuoteConverter $purchaseOrderQuoteConverter */
$purchaseOrderQuoteConverter = $objectManager->get(PurchaseOrderQuoteConverter::class);

/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->create(CustomerRegistry::class);
$adminCustomer = $customerRegistry->retrieveByEmail('john.doe@example.com');
$levelOneCustomer = $customerRegistry->retrieveByEmail('veronica.costello@example.com');
$levelTwoCustomer = $customerRegistry->retrieveByEmail('alex.smith@example.com');
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('Magento', 'company_name');
$purchaseOrdersData = [
    [
        'company_id' => $company->getId(),
        'creator_id' => $adminCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_APPROVED,
        'grand_total' => 15,
        'auto_approve' => 1,
        'is_validate' => 1,
        'payment_method' => 'paypal_express'
    ],
    [
        'company_id' => $company->getId(),
        'creator_id' => $levelOneCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_PENDING,
        'grand_total' => 0,
        'auto_approve' => 0,
        'is_validate' => 0,
        'payment_method' => 'paypal_express'
    ],
    [
        'company_id' => $company->getId(),
        'creator_id' => $levelTwoCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_PENDING,
        'grand_total' => 15,
        'auto_approve' => 0,
        'is_validate' => 0,
        'payment_method' => 'paypal_express'
    ]
];

$quote->getPayment()->setMethod('paypal_express');
$quote->collectTotals()->save();

foreach ($purchaseOrdersData as $purchaseOrderData) {
    $quote->setGrandTotal($purchaseOrderData['grand_total']);
    $snapshotQuote = clone $quote;
    $snapshotQuote->isObjectNew(true);
    $snapshotQuote->unsetData('entity_id');

    $snapshotQuote->save();

    // copy items with their options
    $newParentItemIds = [];
    foreach ($snapshotQuote->getItemsCollection() as $item) {
        // save child items later
        if ($item->getParentItem()) {
            continue;
        }
        $oldItemId = $item->getId();
        $item->setId(null);
        $newItem = clone $item;
        $newItem->setQuote($snapshotQuote);
        $newItem->save();
        $newParentItemIds[$oldItemId] = $newItem->getId();
    }

    // save children with new parent id
    foreach ($quote->getItemsCollection() as $item) {
        if (!$item->getParentItem() || !isset($newParentItemIds[$item->getParentItemId()])) {
            continue;
        }
        $newItem = clone $item;
        $newItem->setQuote($snapshotQuote);
        $newItem->setParentItemId($newParentItemIds[$item->getParentItemId()]);
        $newItem->save();
    }

    // copy billing and shipping addresses
    foreach ($quote->getAddressesCollection() as $address) {
        $newAddress = clone $address;
        $newAddress->setId(null);
        $newAddress->setQuote($snapshotQuote);
        $newAddress->save();
    }

    // copy payment info
    $payment = $quote->getPayment();
    $newPayment = clone $payment;
    $newPayment->setId(null);
    $newPayment->setQuote($snapshotQuote);
    $newPayment->save();

    $snapshotQuote->save();

    // Update the quote information on the purchase order
    $purchaseOrderData['quote_id'] = $snapshotQuote->getId();
    $purchaseOrderData['snapshot_quote'] = $snapshotQuote;

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
