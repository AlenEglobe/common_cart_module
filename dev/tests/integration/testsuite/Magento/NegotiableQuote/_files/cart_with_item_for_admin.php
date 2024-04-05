<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(CustomerRepositoryInterface::class);
/** @var CartManagementInterface $quoteManager */
$quoteManager = Bootstrap::getObjectManager()->create(CartManagementInterface::class);
/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
/** @var CartItemRepositoryInterface $cartItemRepository */
$cartItemRepository = Bootstrap::getObjectManager()->create(CartItemRepositoryInterface::class);
$customer = $customerRepository->get('email@companyquote.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
$quote = $quoteRepository->get($quoteId);

/** @var CartItemInterface $item */
$item = Bootstrap::getObjectManager()->create(CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(5);
$cartItemRepository->save($item);

$quoteIdMask = Bootstrap::getObjectManager()->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quoteId);
$quoteIdMask->setMaskedId('cart_item_admin_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = Bootstrap::getObjectManager()->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);
