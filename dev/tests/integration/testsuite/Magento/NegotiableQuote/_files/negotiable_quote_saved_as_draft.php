<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteItemManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

Bootstrap::getInstance()->loadArea(Magento\Framework\App\Area::AREA_ADMINHTML);

$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
/** @var CartManagementInterface $quoteManager */
$quoteManager = $objectManager->create(CartManagementInterface::class);
/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->create(CartRepositoryInterface::class);
/** @var CartItemRepositoryInterface $cartItemRepository */
$cartItemRepository = $objectManager->create(CartItemRepositoryInterface::class);
/** @var NegotiableQuoteItemManagementInterface $quoteItemManagement */
$quoteItemManagement = $objectManager->create(NegotiableQuoteItemManagementInterface::class);

$customer = $customerRepository->get('customercompany22@example.com');
$quoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
/** @var CartInterface $quote */
$quote = $quoteRepository->get($quoteId);
/** @var CartItemInterface $item */
$item = $objectManager->create(CartItemInterface::class);
$item->setQuoteId($quoteId);
$item->setSku('simple');
$item->setQty(1);
$cartItem = $cartItemRepository->save($item);
/** @var CartItemInterface $item2 */
$item2 = $objectManager->create(CartItemInterface::class);
$item2->setQuoteId($quoteId);
$item2->setSku('simple2');
$item2->setQty(1);
$cartItem2 = $cartItemRepository->save($item2);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = $objectManager->create(NegotiableQuoteInterface::class);
$negotiableQuote->setQuoteId($quoteId);
$negotiableQuote->setQuoteName('quote_admin_draft');
$negotiableQuote->setCreatorId($customer->getId());
$negotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT);
$negotiableQuote->setNegotiatedPriceValue(20);
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$quoteRepository->save($quote);
$quoteItemManagement->updateQuoteItemsCustomPrices($quoteId);

$quoteIdMask = $objectManager->create(QuoteIdMask::class);
$quoteIdMask->setQuoteId($quoteId);
$quoteIdMask->setMaskedId('nq_customer_mask');
$quoteIdMask->setDataChanges(true);

/** @var QuoteIdMaskResource $maskedIdResource */
$maskedIdResource = $objectManager->create(QuoteIdMaskFactory::class)->create();
$maskedIdResource->save($quoteIdMask);

/** @var NegotiableQuoteManagementInterface $negotiableQuoteManagement */
$negotiableQuoteManagement = $objectManager->get(NegotiableQuoteManagementInterface::class);
$negotiableQuoteManagement->send($quoteId);

// update qty of 'simple' and remove 'simple2'
$updatedQuoteData = [
    'items' => [
        [
            'config' => '',
            'id' => $cartItem->getItemId(),
            'productSku' => 'simple',
            'qty' => 5,
            'sku' => 'simple'
        ]
    ],
    'proposed' => [
        'type' => NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
        'value' => 50
    ],
    'recalcPrice' => 1,
    'update' => 1
];
$negotiableQuoteManagement->saveAsDraft($quoteId, $updatedQuoteData);
