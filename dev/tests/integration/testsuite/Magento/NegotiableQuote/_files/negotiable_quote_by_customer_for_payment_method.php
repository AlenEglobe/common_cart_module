<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteItemManagementInterface;
use Magento\NegotiableQuote\Model\Quote\History;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
$quoteManager = $objectManager->create(CartManagementInterface::class);
$quoteRepository = $objectManager->create(CartRepositoryInterface::class);
$quoteItemManagement = $objectManager->create(NegotiableQuoteItemManagementInterface::class);
$quoteHistory = $objectManager->create(History::class);

$customer = $customerRepository->get('customercompany22@example.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
$quote = $quoteRepository->get($quoteId);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
$product = $productRepository->get('simple');
$quote->addProduct($product, 2);
$quoteRepository->save($quote);

$addressData = [
    'region' => 'CA',
    'postcode' => '11111',
    'lastname' => 'lastname',
    'firstname' => 'firstname',
    'street' => 'street',
    'city' => 'Los Angeles',
    'email' => 'customercompany22@example.com',
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

/** @var NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(
    NegotiableQuoteInterface::class
);
$negotiableQuote->setQuoteId($quoteId);
$negotiableQuote->setQuoteName('quote_customer_send');
$negotiableQuote->setCreatorId($customer->getId());
$negotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(
    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$negotiableQuote->setNegotiatedPriceValue(20);
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$quoteRepository->save($quote);
$quoteItemManagement->updateQuoteItemsCustomPrices($quoteId);
$quoteHistory->createLog($quoteId);

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quoteId);
$quoteIdMask->setMaskedId('nq_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
