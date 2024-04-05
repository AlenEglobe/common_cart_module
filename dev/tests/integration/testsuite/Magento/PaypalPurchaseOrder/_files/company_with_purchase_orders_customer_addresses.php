<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
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

Resolver::getInstance()
    ->requireDataFixture('Magento/Checkout/_files/quote_with_virtual_product_and_address.php');
Resolver::getInstance()
    ->requireDataFixture('Magento/Company/_files/company_with_structure.php');

$objectManager = Bootstrap::getObjectManager();
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');
$quoteRepository = $objectManager->get(QuoteRepository::class);
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
$purchaseOrderQuoteConverter = $objectManager->get(PurchaseOrderQuoteConverter::class);
$customerRepository = $objectManager->create(CustomerRepository::class);
$adminCustomer = $customerRepository->get('john.doe@example.com');
$levelOneCustomer = $customerRepository->get('veronica.costello@example.com');
$levelTwoCustomer = $customerRepository->get('alex.smith@example.com');
$companyFactory = $objectManager->get(CompanyInterfaceFactory::class);
$company = $companyFactory->create()->load('Magento', 'company_name');
$purchaseOrdersData = [
    [
        'customer' => $adminCustomer,
        'company_id' => $company->getId(),
        'creator_id' => $adminCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_APPROVED,
        'grand_total' => 15,
        'auto_approve' => 1,
        'is_validate' => 1,
        'payment_method' => 'checkmo'
    ],
    [
        'customer' => $levelOneCustomer,
        'company_id' => $company->getId(),
        'creator_id' => $levelOneCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_PENDING,
        'grand_total' => 0,
        'auto_approve' => 0,
        'is_validate' => 0,
        'payment_method' => 'checkmo'
    ],
    [
        'customer' => $levelTwoCustomer,
        'company_id' => $company->getId(),
        'creator_id' => $levelTwoCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_PENDING,
        'grand_total' => 15,
        'auto_approve' => 0,
        'is_validate' => 0,
        'payment_method' => 'checkmo'
    ]
];
$quote->getPayment()->setMethod('checkmo');
$quote->collectTotals()->save();
foreach ($purchaseOrdersData as $purchaseOrderData) {
    $quote->setGrandTotal($purchaseOrderData['grand_total']);
    $snapshotQuote = clone $quote;
    $snapshotQuote->isObjectNew(true);
    $snapshotQuote->unsetData('entity_id');
    $snapshotQuote->setCustomer($purchaseOrderData['customer']);
    $snapshotQuote->setData('customer_id', $purchaseOrderData['creator_id']);
    $snapshotQuote->save();
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
    foreach ($quote->getItemsCollection() as $item) {
        if (!$item->getParentItem() || !isset($newParentItemIds[$item->getParentItemId()])) {
            continue;
        }
        $newItem = clone $item;
        $newItem->setQuote($snapshotQuote);
        $newItem->setParentItemId($newParentItemIds[$item->getParentItemId()]);
        $newItem->save();
    }
    foreach ($quote->getAddressesCollection() as $address) {
        /** @var \Magento\Quote\Model\Quote\Address $newAddress */
        $newAddress = clone $address;
        $newAddress->setId(null);
        $newAddress->setCustomerId($purchaseOrderData['customer']->getId());
        $newAddress->setQuote($snapshotQuote);
        /** @var Address $customerAddress */
        $customerAddress = $objectManager->create(Address::class);
        $customerAddress->setData(
            [
                'attribute_set_id' => 2,
                'telephone' => $newAddress->getTelephone(),
                'postcode' => $newAddress->getPostcode(),
                'country_id' => $newAddress->getCountryId(),
                'city' => $newAddress->getCity(),
                'company' => $newAddress->getCompany(),
                'street' => $newAddress->getStreet(),
                'lastname' => $newAddress->getLastname(),
                'firstname' => $newAddress->getFirstname(),
                'parent_id' => 1,
                'region_id' => $newAddress->getRegionId(),
            ]
        );
        $customerAddress->isObjectNew(true);
        $customerAddress->setCustomerId($purchaseOrderData['customer']->getId());
        $customerAddress->save();
        $newAddress->setCustomerAddressId($customerAddress->getId());
        $newAddress->save();
        if ($newAddress->getAddressType() == 'billing') {
            $snapshotQuote->setBillingAddress($newAddress);
        } else {
            $snapshotQuote->setShippingAddress($newAddress);
        }
    }
    $payment = $quote->getPayment();
    $newPayment = clone $payment;
    $newPayment->setQuote($snapshotQuote);
    $newPayment->setId(null);
    $newPayment->save();
    $snapshotQuote->save();
    $purchaseOrderData['snapshot_quote'] = $snapshotQuote;
    $purchaseOrderData['quote_id'] = $snapshotQuote->getId();
    $purchaseOrder = $purchaseOrderFactory->create();
    $dataObjectHelper->populateWithArray(
        $purchaseOrder,
        $purchaseOrderData,
        PurchaseOrderInterface::class
    );
    $purchaseOrderRepository->save($purchaseOrder);
}
