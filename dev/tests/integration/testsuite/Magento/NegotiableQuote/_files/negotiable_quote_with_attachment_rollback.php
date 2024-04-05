<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var NegotiableQuoteRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->create(NegotiableQuoteRepositoryInterface::class);
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder->addFilter(NegotiableQuoteInterface::QUOTE_NAME, 'quote_customer_send')
    ->create();
$quotes = $quoteRepository->getList($searchCriteria)->getItems();
foreach ($quotes as $quote) {
    $quoteRepository->delete($quote);
}

/** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
$customerRepository = Bootstrap::getObjectManager()->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
try {
    $customer = $customerRepository->get('quote_customer_email@example.com');
    $customerRepository->delete($customer);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    //
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture(
    'Magento/NegotiableQuote/_files/negotiable_quote_attachment_rollback.php'
);
