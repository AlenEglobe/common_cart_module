<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$quoteManager = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartManagementInterface::class);
$quoteRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$cartItemRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartItemRepositoryInterface::class);

$manager = $customerRepository->get('companymanager@example.com');
$managerQuoteId = $quoteManager->createEmptyCartForCustomer($manager->getId());
$managerQuote = $quoteRepository->get($managerQuoteId);
/** @var \Magento\Quote\Api\Data\CartItemInterface $managerItem */
$managerItem = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\CartItemInterface::class);
$managerItem->setQuoteId($managerQuoteId);
$managerItem->setSku('simple');
$managerItem->setQty(1);
$cartItemRepository->save($managerItem);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $managerNegotiableQuote */
$managerNegotiableQuote = Bootstrap::getObjectManager()->create(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::class
);
$managerNegotiableQuote->setQuoteId($managerQuoteId);
$managerNegotiableQuote->setQuoteName('quote_customer_send');
$managerNegotiableQuote->setCreatorId($manager->getId());
$managerNegotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$managerNegotiableQuote->setIsRegularQuote(true);
$managerNegotiableQuote->setNegotiatedPriceType(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$managerNegotiableQuote->setNegotiatedPriceValue(20);
$managerNegotiableQuote->setStatus(\Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::STATUS_CREATED);
$managerQuote->getExtensionAttributes()->setNegotiableQuote($managerNegotiableQuote);
$quoteRepository->save($managerQuote);

$customer = $customerRepository->get('customercompany22@example.com');
$customerQuoteId = $quoteManager->createEmptyCartForCustomer($customer->getId());
$customerQuote = $quoteRepository->get($customerQuoteId);
/** @var \Magento\Quote\Api\Data\CartItemInterface $customerItem */
$customerItem = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\Data\CartItemInterface::class);
$customerItem->setQuoteId($customerQuoteId);
$customerItem->setSku('simple');
$customerItem->setQty(1);
$cartItemRepository->save($customerItem);

/** @var \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $customerNegotiableQuote */
$customerNegotiableQuote = Bootstrap::getObjectManager()->create(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::class
);
$customerNegotiableQuote->setQuoteId($customerQuoteId);
$customerNegotiableQuote->setQuoteName('quote_customer_send');
$customerNegotiableQuote->setCreatorId($customer->getId());
$customerNegotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$customerNegotiableQuote->setIsRegularQuote(true);
$customerNegotiableQuote->setNegotiatedPriceType(
    \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT
);
$customerNegotiableQuote->setNegotiatedPriceValue(20);
$customerNegotiableQuote->setStatus(\Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface::STATUS_CREATED);
$customerQuote->getExtensionAttributes()->setNegotiableQuote($customerNegotiableQuote);
$quoteRepository->save($customerQuote);
