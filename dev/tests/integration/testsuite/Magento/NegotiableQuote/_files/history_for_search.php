<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\NegotiableQuote\Api\Data\HistoryInterface;
use Magento\NegotiableQuote\Model\History;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResource;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;

/** @var NegotiableQuoteResource $negotiableQuoteResource */
$negotiableQuoteResource = Bootstrap::getObjectManager()->get(NegotiableQuoteResource::class);

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
$negotiableQuote->setQuoteName('quote name');
$negotiableQuote->setStatus('active');
$negotiableQuote->setIsRegularQuote(true);
$negotiableQuote->setSnapshot('snapshot 5');

$negotiableQuoteResource->saveNegotiatedQuoteData($negotiableQuote);

$items = [
    [
        'log_data' => 'log data 1',
        'status' => HistoryInterface::STATUS_CREATED,
        'is_seller' => 1,
        'is_draft' => 1,
        'author_id' => 4,
    ],
    [
        'log_data' => 'log data 2',
        'status' => HistoryInterface::STATUS_CREATED,
        'is_seller' => 1,
        'is_draft' => 1,
        'author_id' => 2,
    ],
    [
        'log_data' => 'log data 3',
        'status' => HistoryInterface::STATUS_CREATED,
        'is_seller' => 1,
        'is_draft' => 1,
        'author_id' => 1,
    ],
    [
        'log_data' => 'log data 4',
        'status' => HistoryInterface::STATUS_CREATED,
        'is_seller' => 1,
        'is_draft' => 1,
        'author_id' => 5,
    ],
    [
        'log_data' => 'log data 5',
        'status' => HistoryInterface::STATUS_CREATED,
        'is_seller' => 0,
        'is_draft' => 1,
        'author_id' => 3,
    ],
];

foreach ($items as $data) {
    /** @var $history History */
    $history = Bootstrap::getObjectManager()->create(History::class);
    $history->setQuoteId($negotiableQuote->getQuoteId());
    $history->setStatus($data['status']);
    $history->setLogData($data['log_data']);
    $history->setIsSeller($data['is_seller']);
    $history->setIsDraft($data['is_draft']);
    $history->setAuthorId($data['author_id']);
    $history->save();
}
