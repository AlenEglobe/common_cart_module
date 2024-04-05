<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;

Resolver::getInstance()->requireDataFixture('Magento/GiftCard/_files/quote_with_items_saved.php');
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

$objectManager = Bootstrap::getObjectManager();

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);

/** @var QuoteResource $quoteFactory */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_item_with_gift_card_items', 'reserved_order_id');

$customer = $customerRepository->get('veronica.costello@example.com');

$quote->setStoreId(1)->getPayment()->setMethod('checkmo');
$quote->setCustomer($customer)->setCustomerIsGuest(false);

// assign addresses to company subordinate customer
foreach ($quote->getAddressesCollection() as $address) {
    /** @var $address \Magento\Quote\Api\Data\AddressInterface */
    $address->setCustomerAddressId(null);
    $address->setQuote($quote);
    $address->setCustomerId($customer->getId());
    $address->save();
}

$quote->collectTotals()->save();

$quoteRepository = $objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$cartItemRepository = $objectManager->create(\Magento\Quote\Api\CartItemRepositoryInterface::class);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = $objectManager->create(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::class
);
$negotiableQuote->setQuoteId($quote->getId());
$negotiableQuote->setQuoteName('quote_customer_send');
$negotiableQuote->setCreatorId($customer->getId());
$negotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$negotiableQuote->setNegotiatedPriceValue(20);
$negotiableQuote->setStatus(\Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$quoteRepository->save($quote);
