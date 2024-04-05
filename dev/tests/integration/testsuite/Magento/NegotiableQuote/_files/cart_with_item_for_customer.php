<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$quoteManager = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartManagementInterface::class);
$cartItemRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartItemRepositoryInterface::class);
$customer = $customerRepository->get('customercompany22@example.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
/** @var \Magento\Quote\Api\Data\CartItemInterface $item */
$item = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(1);
$cartItemRepository->save($item);

/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
$quote = $quoteRepository->get($quoteId);
$quote->setIsActive(true);
$quoteResource = Bootstrap::getObjectManager()->create(QuoteResource::class);

$quoteResource->save($quote);

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setMaskedId('cart_item_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
