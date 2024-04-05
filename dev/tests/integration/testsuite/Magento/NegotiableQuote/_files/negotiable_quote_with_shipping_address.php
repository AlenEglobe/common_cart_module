<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

$customerRepository = Bootstrap::getObjectManager()->create(
    \Magento\Customer\Api\CustomerRepositoryInterface::class
);
$quoteManager = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartManagementInterface::class);
$quoteRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$cartItemRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartItemRepositoryInterface::class);
$customer = $customerRepository->get('email@companyquote.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
$quote = $quoteRepository->get($quoteId);
/** @var \Magento\Quote\Api\Data\CartItemInterface $item */
$item = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(5);
$cartItemRepository->save($item);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::class
);
$negotiableQuote->setQuoteId($quoteId);
$negotiableQuote->setQuoteName('quote_customer_send');
$negotiableQuote->setCreatorId(1);
$negotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$negotiableQuote->setNegotiatedPriceValue(20);
$negotiableQuote->setStatus(\Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$quoteRepository->save($quote);

$addressData = [
    'region' => 'CA',
    'postcode' => '11111',
    'lastname' => 'lastname',
    'firstname' => 'firstname',
    'street' => 'street',
    'city' => 'Los Angeles',
    'email' => 'admin@example.com',
    'telephone' => '11111111',
    'country_id' => 'US'
];
/** @var Magento\Quote\Api\Data\AddressInterface $billingAddress */
$billingAddress = Bootstrap::getObjectManager()->create(
    Magento\Quote\Api\Data\AddressInterface::class,
    ['data' => $addressData]
);
$billingAddress->setCustomerAddressId(null);
$billingAddressManagement = Bootstrap::getObjectManager()->create(
    Magento\Quote\Api\BillingAddressManagementInterface::class
);
$billingAddressManagement->assign($quoteId, $billingAddress, true);
$quoteRepository->save($quote);
$quoteRepository->get($quote->getId());

$negotiableItemManagement = Bootstrap::getObjectManager()->create(
    \Magento\NegotiableQuote\Api\NegotiableQuoteItemManagementInterface::class
);
$negotiableItemManagement->recalculateOriginalPriceTax($quote->getId(), true, true);
