<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\NegotiableQuote\Api\Data\AttachmentContentInterface;

/**
 * Tests negotiable quote get comments WebApi call.
 */
class CommentManagementTest extends WebapiAbstract
{
    /**
     * Service read name constant.
     */
    const SERVICE_READ_NAME = 'negotiableQuoteCommentLocatorV1';

    /**
     * Service version constant.
     */
    const SERVICE_VERSION = 'V1';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface
     */
    private $negotiableRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Magento\NegotiableQuote\Model\ResourceModel\Comment\CollectionFactory
     */
    private $commentsCollection;

    /**
     * @var \Magento\NegotiableQuote\Model\CommentManagementInterface
     */
    private $commentManagement;

    /**
     * @var int
     */
    private $quoteId;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(
            \Magento\Customer\Api\CustomerRepositoryInterface::class
        );
        $this->commentManagement = $this->objectManager->get(
            \Magento\NegotiableQuote\Model\CommentManagementInterface::class
        );
        $this->commentsCollection = $this->objectManager->get(
            \Magento\NegotiableQuote\Model\ResourceModel\Comment\CollectionFactory::class
        );
        $this->negotiableRepository = $this->objectManager->get(
            \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface::class
        );
        $this->quoteRepository = $this->objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        try {
            $quote = $this->quoteRepository->get($this->quoteId);
            $this->quoteRepository->delete($quote);
        } catch (\InvalidArgumentException $e) {
            // Do nothing if cart fixture was not used
        }
        parent::tearDown();
    }

    /**
     * Get quote comments via API and check wth comments from database.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testGet()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());

        $this->quoteId = end($quotes)->getId();
        $this->sendQuoteToCustomer();

        $comments = $this->getCommentsWithAttachmentsByQuoteId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId . '/comments',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getListForQuote',
            ],
        ];
        $result = $this->_webApiCall($serviceInfo, ['quoteId' => $this->quoteId]);
        $this->assertEquals($comments, $result);
        foreach ($comments as $key => $comment) {
            $this->assertEquals($comment['attachments'], $result[$key]['attachments']);
        }
    }

    /**
     * Send negotiable quote with comment and attachment to the customer.
     *
     * @return void
     */
    private function sendQuoteToCustomer()
    {
        $attachment =  [
            AttachmentContentInterface::BASE64_ENCODED_DATA => base64_encode('encoded file text'),
            AttachmentContentInterface::TYPE => 'text/plain',
            AttachmentContentInterface::NAME => 'text.txt',
        ];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/submitToCustomer',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableQuoteManagementV1',
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => 'negotiableQuoteNegotiableQuoteManagementV1adminSend',
            ],
        ];
        $this->_webApiCall(
            $serviceInfo,
            ['quoteId' => $this->quoteId, 'comment' => 'comment', 'files' => [$attachment]]
        );
    }

    /**
     * Retrieve quote comments with loaded attachments. Build the structure similar to one that API should return.
     *
     * @return array
     */
    private function getCommentsWithAttachmentsByQuoteId()
    {
        $commentCollection = $this->commentsCollection->create();
        $commentCollection->addFieldToFilter('parent_id', $this->quoteId);

        $res = [];
        foreach ($commentCollection as $comment) {
            $attachments = $this->commentManagement->getCommentAttachments($comment->getId())->getItems();
            $attachmentData = [];
            foreach ($attachments as $item) {
                $attachmentData[] = $item->getData();
            }
            $data = $comment->getData();

            foreach ($data as &$val) {
                $val = (string)$val;
            }
            $data['attachments'] = $attachmentData;
            $res[] = $data;
        }

        return $res;
    }
}
