<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var QuoteIdMaskFactory $quoteIdMaskFactory */
$quoteIdMaskFactory = $objectManager->get(QuoteIdMaskFactory::class);

$quoteCollection = $objectManager->create(\Magento\Quote\Model\ResourceModel\Quote\Collection::class);
foreach ($quoteCollection as $quote) {
    $quote->delete();
    $quoteIdMask = $quoteIdMaskFactory->create();
    $quoteIdMask->setQuoteId($quote->getId())->delete();
}

$quoteItemCollection = $objectManager->create(\Magento\Quote\Model\ResourceModel\Quote\Item\Collection::class);
foreach ($quoteItemCollection as $item) {
    $item->delete();
}

/** @var NegotiableQuoteRepositoryInterface $negotiableQuoteRepository */
$negotiableQuoteRepository = $objectManager->create(NegotiableQuoteRepositoryInterface::class);
/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$customer = $customerRepository->get('customercompany22@example.com');
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder->addFilter(NegotiableQuoteInterface::CREATOR_ID, $customer->getId())->create();
$quotes = $negotiableQuoteRepository->getList($searchCriteria)->getItems();
foreach ($quotes as $quote) {
    $negotiableQuoteRepository->delete($quote);
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
