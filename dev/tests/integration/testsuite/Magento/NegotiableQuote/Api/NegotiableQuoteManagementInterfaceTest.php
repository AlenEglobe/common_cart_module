<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Api;

use Magento\NegotiableQuote\Api\Data\CommentAttachmentInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\Quote\Api\CartManagementInterface;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\NegotiableQuote\Api\Data\AttachmentContentInterface as Attachment;
use Magento\NegotiableQuote\Api\Data\AttachmentContentInterfaceFactory as AttachmentFactory;
use Magento\Customer\Model\Session as CustomerSession;

class NegotiableQuoteManagementInterfaceTest extends TestCase
{
    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $management;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var AttachmentFactory
     */
    private $attachmentFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CommentLocatorInterface
     */
    private $commentLocator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->management = Bootstrap::getObjectManager()
            ->get(NegotiableQuoteManagementInterface::class);
        $this->cartManagement = Bootstrap::getObjectManager()
            ->get(CartManagementInterface::class);
        $this->attachmentFactory = Bootstrap::getObjectManager()
            ->get(AttachmentFactory::class);
        $this->customerSession = Bootstrap::getObjectManager()
            ->get(CustomerSession::class);
        $this->commentLocator = Bootstrap::getObjectManager()
            ->get(CommentLocatorInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->customerSession->logout();
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/NegotiableQuote/_files/cart.php
     * @magentoAppArea frontend
     */
    public function testCreate()
    {
        $this->customerSession->loginById(1);
        $attachmentExtension = 'txt';
        $attachmentName = 'attachment.' . $attachmentExtension;
        $attachment = $this->attachmentFactory->create(
            [
                'data' => [
                    Attachment::BASE64_ENCODED_DATA => base64_encode(
                        'My Quote Attachment'
                    ),
                    Attachment::TYPE => 'plain/text',
                    Attachment::NAME => $attachmentName,
                ]
            ]
        );
        $cart = $this->cartManagement->getCartForCustomer(1);

        //Create a negotiable quote.
        $this->management->create(
            $cart->getId(),
            $name = 'test quote',
            $commentText = 'my quote is fair',
            [$attachment]
        );
        //NegotiableQuote is created.
        $foundCart = $this->management->getNegotiableQuote($cart->getId());
        $this->assertNotEmpty($foundCart->getExtensionAttributes());
        $this->assertNotEmpty(
            $foundCart->getExtensionAttributes()->getNegotiableQuote()
        );
        /** @var NegotiableQuoteInterface $quote */
        $quote = $foundCart->getExtensionAttributes()->getNegotiableQuote();
        //Quote data is saved correctly
        $this->assertEquals($name, $quote->getQuoteName());
        $comments = $this->commentLocator->getListForQuote(
            $quote->getQuoteId()
        );
        $this->assertCount(1, $comments);
        $comment = array_pop($comments);
        $this->assertEquals($commentText, $comment->getComment());
        $this->assertCount(1, $comment->getAttachments());
        /** @var CommentAttachmentInterface $attachment */
        $attachment = array_values($comment->getAttachments())[0];
        $this->assertStringNotContainsString($attachmentName, $attachment->getFilePath());
        $this->assertStringEndsNotWith(
            '.' .$attachmentExtension,
            $attachment->getFilePath()
        );
    }
}
