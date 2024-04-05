<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Model\CustomerRegistry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/PurchaseOrder/_files/purchase_orders.php');

$objectManager = Bootstrap::getObjectManager();
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->create(CustomerRegistry::class);
$customer = $customerRegistry->retrieve(1);
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quoteResource = $objectManager->get(QuoteResource::class);
/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->get(CartRepositoryInterface::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');
/** @var NegotiableQuoteInterface $negotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(NegotiableQuoteInterface::class);
$negotiableQuote->setQuoteId($quote->getId());
$negotiableQuote->setQuoteName('quote_customer_send');
$negotiableQuote->setCreatorId($customer->getId());
$negotiableQuote->setCreatorType(Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER);
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setNegotiatedPriceType(NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_AMOUNT_DISCOUNT);
$negotiableQuote->setNegotiatedPriceValue(2.00);
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
$quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
$quoteRepository->save($quote);

// Simulate merchant approval by transitioning the negotiable quote to the "submitted_by_admin" status
$negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
$quoteRepository->save($quote);
