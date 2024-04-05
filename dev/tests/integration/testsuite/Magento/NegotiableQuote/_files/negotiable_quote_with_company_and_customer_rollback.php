<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/company_with_customer_for_quote_rollback.php'
);

$objectManager = Bootstrap::getObjectManager();

$quoteRepository = $objectManager->get(QuoteRepository::class);

$quoteResource = $objectManager->get(QuoteResource::class);
$quoteFactory = $objectManager->get(QuoteFactory::class);
$quote = $quoteFactory->create();

$quoteResource->load($quote, 'reserved_order_id', 'reserved_order_id');
$quoteRepository->delete($quote);
