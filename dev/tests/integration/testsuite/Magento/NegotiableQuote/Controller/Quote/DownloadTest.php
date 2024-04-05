<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Controller\Quote;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Message\MessageInterface;
use Magento\NegotiableQuote\Api\CommentLocatorInterface;
use Magento\NegotiableQuote\Api\Data\CommentAttachmentInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class DownloadTest extends AbstractController
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);
        $this->quoteRepository = $this->_objectManager->create(NegotiableQuoteRepositoryInterface::class);
        $this->customerSession = $this->_objectManager->get(CustomerSession::class);
        $this->customerRegistry = $this->_objectManager->get(CustomerRegistry::class);
        $this->customerRepository = $this->_objectManager->create(CustomerRepositoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->customerSession->setCustomerId(null);
        $this->customerRegistry->removeByEmail('customer@example.com');
        $this->customerRegistry->removeByEmail('quote_customer_email@example.com');

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testDownloadAttachmentByNotLoggedInCustomer(): void
    {
        $this->dispatch('negotiable_quote/quote/download/attachmentId/1/');

        $this->assertRedirect($this->stringContains('customer/account/login'));
        $this->assertSessionMessages(
            $this->equalTo([$this->getMessageText('Please sign in to download.')]),
            MessageInterface::TYPE_NOTICE
        );
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @return void
     */
    public function testDownloadMissingAttachmentByLoggedInCustomer(): void
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        $this->dispatch('negotiable_quote/quote/download/attachmentId/100500');

        $this->assertRedirect($this->stringContains('negotiable_quote/quote'));
        $this->assertSessionMessages(
            $this->equalTo([$this->getMessageText('We can\'t find the file you requested.')]),
            MessageInterface::TYPE_NOTICE
        );
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_attachment.php
     * @return void
     */
    public function testDownloadAttachmentByNotAuthorizedCustomer(): void
    {
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        $attachment = $this->getQuoteAttachment('quote_with_comment_attachment');
        if ($attachment === false) {
            $this->fail('Could not load attachment by negotiable quote name');
        }

        $this->dispatch('negotiable_quote/quote/download/attachmentId/' . $attachment->getAttachmentId());

        $this->assertRedirect($this->stringContains('negotiable_quote/quote'));
        $this->assertSessionMessages(
            $this->equalTo([$this->getMessageText('We can\'t find the file you requested.')]),
            MessageInterface::TYPE_NOTICE
        );
    }

    /**
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_attachment.php
     * @return void
     */
    public function testDownloadAttachmentByLoggedInAuthorizedCustomer(): void
    {
        $customer = $this->customerRepository->get('quote_customer_email@example.com');
        $this->customerSession->loginById($customer->getId());

        $attachment = $this->getQuoteAttachment('quote_with_comment_attachment');
        if ($attachment === false) {
            $this->fail('Could not load attachment by negotiable quote name');
        }

        ob_start();
        $this->dispatch('negotiable_quote/quote/download/attachmentId/' . $attachment->getAttachmentId());
        ob_end_clean();

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertHeaderPcre('Pragma', '/^public$/');
        $this->assertHeaderPcre('Content-Type', "/^application\/octet-stream$/");
        $this->assertHeaderPcre('Content-Disposition', '/^attachment; filename="' . $attachment->getFileName() . '"$/');
    }

    /**
     * Returns attachment by quote name.
     *
     * @param string $quoteName
     * @return CommentAttachmentInterface|bool
     */
    private function getQuoteAttachment(string $quoteName)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(NegotiableQuoteInterface::QUOTE_NAME, $quoteName)
            ->create();
        /** @var NegotiableQuoteInterface[] $quotes */
        $quotes = $this->quoteRepository->getList($searchCriteria)->getItems();
        $quote = reset($quotes);
        if ($quote === false) {
            return false;
        }

        /** @var CommentLocatorInterface $commentLocator */
        $commentLocator = $this->_objectManager->create(CommentLocatorInterface::class);
        $comments = $commentLocator->getListForQuote($quote->getId());
        $comment = reset($comments);
        if ($comment === false) {
            return false;
        }

        $attachments = $comment->getAttachments();

        return reset($attachments);
    }

    /**
     * @param string $message
     * @return string
     */
    private function getMessageText(string $message): string
    {
        return htmlentities($message, ENT_QUOTES | ENT_SUBSTITUTE);
    }
}
