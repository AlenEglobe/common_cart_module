<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResource;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/company_with_customer_for_quote.php');

$objectManager = Bootstrap::getObjectManager();

$negotiableQuoteResource = $objectManager->get(NegotiableQuoteResource::class);

$quote = $objectManager->create(Quote::class);
$quoteResource = $objectManager->create(QuoteResource::class);

$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);

$customer = $customerRepository->get('email@companyquote.com');
$quote->setCustomerId($customer->getId())
    ->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(0)
    ->setReservedOrderId('reserved_order_id')
    ->collectTotals();

$quoteResource->save($quote);

$negotiableQuote = $objectManager->create(NegotiableQuote::class);
$negotiableQuote->setQuoteId($quote->getId());
$negotiableQuote->setQuoteName('quote name');
$negotiableQuote->setStatus('active');
$negotiableQuote->setIsRegularQuote(true);

$negotiableQuoteResource->saveNegotiatedQuoteData($negotiableQuote);
