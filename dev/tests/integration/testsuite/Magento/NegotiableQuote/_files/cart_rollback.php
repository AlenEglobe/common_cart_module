<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/product_simple_rollback.php');
Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/company_with_customer_for_quote_rollback.php'
);

$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$quoteCollection = $objectManager->create(\Magento\Quote\Model\ResourceModel\Quote\Collection::class);
foreach ($quoteCollection as $quote) {
    $quote->delete();
}

$quoteItemCollection = $objectManager->create(\Magento\Quote\Model\ResourceModel\Quote\Item\Collection::class);
foreach ($quoteItemCollection as $item) {
    $item->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
