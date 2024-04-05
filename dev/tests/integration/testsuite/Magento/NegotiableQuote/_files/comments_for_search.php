<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\NegotiableQuote\Model\Comment;
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

$comments = [
    [
        'comment' => 'comment 1',
        'creator_type' => 3,
        'is_decline' => 1,
        'is_draft' => 1,
        'creator_id' => 4,
    ],
    [
        'comment' => 'comment 2',
        'creator_type' => 1,
        'is_decline' => 1,
        'is_draft' => 1,
        'creator_id' => 2,
    ],
    [
        'comment' => 'comment 3',
        'creator_type' => 2,
        'is_decline' => 1,
        'is_draft' => 1,
        'creator_id' => 1,
    ],
    [
        'comment' => 'comment 4',
        'creator_type' => 1,
        'is_decline' => 1,
        'is_draft' => 1,
        'creator_id' => 5,
    ],
    [
        'comment' => 'comment 5',
        'creator_type' => 1,
        'is_decline' => 0,
        'is_draft' => 1,
        'creator_id' => 3,
    ],
];

foreach ($comments as $data) {
    /** @var $comment Comment */
    $comment = Bootstrap::getObjectManager()->create(Comment::class);
    $comment->setParentId($negotiableQuote->getQuoteId());
    $comment->setComment($data['comment']);
    $comment->setCreatorType($data['creator_type']);
    $comment->setIsDecline($data['is_decline']);
    $comment->setIsDraft($data['is_draft']);
    $comment->setCreatorId($data['creator_id']);
    $comment->save();
}
