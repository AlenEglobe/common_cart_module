<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Model\CommentAttachmentFactory;
use Magento\NegotiableQuote\Model\CommentAttachment;
use Magento\NegotiableQuote\Model\CommentRepositoryInterface;
use Magento\NegotiableQuote\Model\ResourceModel\CommentAttachment as CommentAttachmentResource;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Model\Comment;
use Magento\NegotiableQuote\Model\CommentFactory;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\NegotiableQuoteFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var CustomerInterface $customer */
$customer = $objectManager->get(CustomerInterfaceFactory::class)->create(
    [
        'data' => [
            'email' => 'quote_customer_email@example.com',
            'password' => 'password',
            'firstname' => 'John',
            'lastname' => 'Smith',
            'group_id' => 1,
            'store_id' => 1,
            'is_active' => 1,
        ]
    ]
);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
$customer = $customerRepository->save($customer);

/** @var NegotiableQuoteRepositoryInterface $negotiableQuoteResource */
$negotiableQuoteRepository = $objectManager->create(NegotiableQuoteRepositoryInterface::class);
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->create(CartRepositoryInterface::class);

/** @var Quote $quote */
$quote = $objectManager->create(QuoteFactory::class)->create();
if ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getNegotiableQuote()) {
    $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
}
$quote->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(0)
    ->setCustomerId($customer->getId())
    ->setReservedOrderId('reserved_order_id')
    ->collectTotals();
$cartRepository->save($quote);

/** @var NegotiableQuote $negotiableQuote */
$negotiableQuote = $objectManager->create(NegotiableQuoteFactory::class)->create(
    [
        'data' => [
            'quote_id' => $quote->getId(),
            'quote_name' => 'quote_with_comment_attachment',
            'status' => NegotiableQuoteInterface::STATUS_CREATED,
            'is_regular_quote' => 1,
            'snapshot' => 'snapshot 1',
            'creator_type' => 1,
            'negotiated_price_type' => 'fixed',
            'creator_id' => 1,
        ],
    ]
);

$negotiableQuoteRepository->save($negotiableQuote);

/** @var Comment $comment */
$comment = $objectManager->create(CommentFactory::class)->create(
    [
        'data' => [
            'entity_id' => 1,
            'parent_id' => $negotiableQuote->getQuoteId(),
            'comment' => 'Comment #1',
            'creator_type' => UserContextInterface::USER_TYPE_CUSTOMER,
            'is_decline' => 0,
            'is_draft' => 0,
            'creator_id' => $customer->getId(),
        ],
    ]
);

/** @var CommentRepositoryInterface $commentRepository */
$commentRepository = $objectManager->create(CommentRepositoryInterface::class);
$commentRepository->save($comment);

[$imageName, $fileType, $filePath] = require __DIR__ . '/negotiable_quote_attachment.php';

/** @var CommentAttachment $attachment */
$attachment = $objectManager->create(CommentAttachmentFactory::class)->create();
$attachment->setCommentId($comment->getId());
$attachment->setFileName($imageName);
$attachment->setFilePath($filePath);
$attachment->setFileType($fileType);

/** @var CommentAttachmentResource $commentAttachmentResource */
$commentAttachmentResource = $objectManager->get(CommentAttachmentResource::class);
$commentAttachmentResource->save($attachment);
