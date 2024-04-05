<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\NegotiableQuote\Api\Data\AttachmentContentInterface;
use Magento\NegotiableQuote\Model\AttachmentContent;
use Magento\NegotiableQuote\Model\CommentAttachment;
use Magento\NegotiableQuote\Model\CommentManagement;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResource;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;

/** @var NegotiableQuoteResource $negotiableQuoteResource */
$negotiableQuoteResource = Bootstrap::getObjectManager()->get(NegotiableQuoteResource::class);

/** @var Quote $quote */
$quote = Bootstrap::getObjectManager()->create(Quote::class);
$quoteResource = Bootstrap::getObjectManager()->create(QuoteResource::class);
if ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getNegotiableQuote()) {
    $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
}
$quote->setCustomerId(567)
    ->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(0)
    ->setReservedOrderId('reserved_order_id')
    ->collectTotals();

$quoteResource->save($quote);

/** @var $negotiableQuote NegotiableQuote */
$negotiableQuote = Bootstrap::getObjectManager()->create(NegotiableQuote::class);
$negotiableQuote->setQuoteId($quote->getId());
$negotiableQuote->setQuoteName('quote name');
$negotiableQuote->setStatus('active');
$negotiableQuote->setIsRegularQuote(true);

$negotiableQuoteResource->saveNegotiatedQuoteData($negotiableQuote);

/** @var AttachmentContentInterface $file */
$file = Bootstrap::getObjectManager()->create(AttachmentContentInterface::class);
$file->setBase64EncodedData(base64_encode('hello world'));
$file->setType('text/plain');
$file->setName('Foobar.txt');

/** @var CommentManagement $commentManagment */
$commentManagment = Bootstrap::getObjectManager()->create(CommentManagement::class);
$commentManagment->update($negotiableQuote->getQuoteId(), 'A file is attached', [$file]);
