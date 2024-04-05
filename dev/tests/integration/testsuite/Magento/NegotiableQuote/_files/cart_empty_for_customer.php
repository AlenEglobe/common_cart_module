<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResource;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

/** @var $customerRepository CustomerRepositoryInterface */
$customerRepository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);
$customer = $customerRepository->get('customercompany22@example.com');

/** @var NegotiableQuoteResource $negotiableQuoteResource */
$negotiableQuoteResource = Bootstrap::getObjectManager()->get(NegotiableQuoteResource::class);

/** @var Quote $quote */
$quote = Bootstrap::getObjectManager()->create(Quote::class);
$quoteResource = Bootstrap::getObjectManager()->create(QuoteResource::class);
$quote->setCustomerId($customer->getId())
    ->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(0)
    ->setReservedOrderId('reserved_order_id')
    ->collectTotals();

$quoteResource->save($quote);

/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
$quote = $quoteRepository->getForCustomer($customer->getId());

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setMaskedId('cart_empty_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
