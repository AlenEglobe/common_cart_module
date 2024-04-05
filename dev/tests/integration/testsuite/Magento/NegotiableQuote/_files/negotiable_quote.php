<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
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
$item->setQty(1);
$cartItemRepository->save($item);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::class
);
$negotiableQuote->setQuoteId($quoteId);
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

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quoteId);
$quoteIdMask->setMaskedId('nq_admin_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
