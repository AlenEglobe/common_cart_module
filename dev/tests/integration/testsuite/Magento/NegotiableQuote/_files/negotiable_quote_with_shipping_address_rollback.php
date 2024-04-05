<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Framework\Registry $registry */
$registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var $quote \Magento\Quote\Model\Quote */
$searchCriteriaBuilder = Bootstrap::getObjectManager()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder->create();
$quoteRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\QuoteRepository::class);
$quotes = $quoteRepository->getList($searchCriteria)->getItems();
foreach ($quotes as $quote) {
    $quote->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
