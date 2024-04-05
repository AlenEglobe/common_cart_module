<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResource;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;

$quotes = [
    [
        'quote_name' => 'quote 1',
        'status' => NegotiableQuoteInterface::STATUS_CREATED,
        'is_regular_quote' => true,
        'snapshot' => 'snapshot 1',
    ],
    [
        'quote_name' => 'quote 2',
        'status' => NegotiableQuoteInterface::STATUS_CREATED,
        'is_regular_quote' => true,
        'snapshot' => 'snapshot 4',
    ],
    [
        'quote_name' => 'quote 3',
        'status' => NegotiableQuoteInterface::STATUS_CREATED,
        'is_regular_quote' => true,
        'snapshot' => 'snapshot 3',
    ],
    [
        'quote_name' => 'quote 4',
        'status' => NegotiableQuoteInterface::STATUS_CREATED,
        'is_regular_quote' => true,
        'snapshot' => 'snapshot 5',
    ],
    [
        'quote_name' => 'quote 5',
        'status' => NegotiableQuoteInterface::STATUS_CREATED,
        'is_regular_quote' => false,
        'snapshot' => 'snapshot 2',
    ],
    [
        'quote_name' => 'quote 6',
        'status' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
        'is_regular_quote' => true,
        'snapshot' => 'snapshot 6',
    ],
];

/** @var NegotiableQuoteResource $negotiableQuoteResource */
$negotiableQuoteResource = Bootstrap::getObjectManager()->get(NegotiableQuoteResource::class);

foreach ($quotes as $data) {
    /** @var Quote $quote */
    $quote = Bootstrap::getObjectManager()->create(Quote::class);
    if ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getNegotiableQuote()) {
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
    }
    $quote->setStoreId(1)
        ->setIsActive(true)
        ->setIsMultiShipping(false)
        ->setReservedOrderId('reserved_order_id')
        ->collectTotals()
        ->save();

    /** @var $negotiableQuote NegotiableQuote */
    $negotiableQuote = Bootstrap::getObjectManager()->create(NegotiableQuote::class);
    $negotiableQuote->setQuoteId($quote->getId());
    $negotiableQuote->setQuoteName($data['quote_name']);
    $negotiableQuote->setStatus($data['status']);
    $negotiableQuote->setIsRegularQuote($data['is_regular_quote']);
    $negotiableQuote->setSnapshot($data['snapshot']);

    $negotiableQuoteResource->saveNegotiatedQuoteData($negotiableQuote);
}
