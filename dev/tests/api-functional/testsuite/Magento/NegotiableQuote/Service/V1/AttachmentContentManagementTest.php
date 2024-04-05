<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\NegotiableQuote\Api\Data\AttachmentContentInterface;
use Magento\Framework\Webapi\Exception as HTTPExceptionCodes;

/**
 * Tests negotiable quote get attachments WebApi call.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AttachmentContentManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'negotiableQuoteAttachmentContentManagementV1';

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
     * Get quote attachment contents.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testGet()
    {
        $attachmentContent = [
            'base64_encoded_data' => base64_encode('encoded file text'),
            'type' => 'text/plain',
            'name' => 'text.txt',
        ];
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $this->sendQuoteToCustomer();
        $requestData = ['attachmentIds' => [$this->getAttachmentIdByQuoteId()]];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/attachmentContent'. '?' . http_build_query($requestData),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'get',
            ],
        ];
        $result = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertEquals([$attachmentContent], $result);
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
     * Retrieve quote attachment id.
     *
     * @return int
     */
    private function getAttachmentIdByQuoteId()
    {
        $commentCollection = $this->commentsCollection->create();
        $commentCollection->addFieldToFilter('parent_id', $this->quoteId);
        $commentId = $commentCollection->getFirstItem()->getId();
        $commentAttachments = $this->commentManagement->getCommentAttachments($commentId);

        return $commentAttachments->getFirstItem()->getId();
    }

    /**
     * Get quote attachment contents with invalid attachment ids.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testPricesUpdateWithInvalidAttachmentIds()
    {
        $noExceptionOccurred = false;
        try {
            $customer = $this->customerRepository->get('email@companyquote.com');
            $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
            $this->quoteId = end($quotes)->getId();
            $this->sendQuoteToCustomer();
            $invalidAttachmentIds = array_merge([999, 1000], [$this->getAttachmentIdByQuoteId()]);
            $requestData = ['attachmentIds' => $invalidAttachmentIds];
            $serviceInfo = [
                'rest' => [
                    'resourcePath' => '/V1/negotiableQuote/attachmentContent' . '?' . http_build_query($requestData),
                    'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
                ],
                'soap' => [
                    'service' => self::SERVICE_READ_NAME,
                    'serviceVersion' => self::SERVICE_VERSION,
                    'operation' => self::SERVICE_READ_NAME . 'get',
                ],
            ];
            $this->_webApiCall($serviceInfo, $requestData);
            $noExceptionOccurred = true;
        } catch (\SoapFault $e) {
            $this->assertStringContainsString(
                'Cannot obtain the requested data. You must fix the errors listed below first.',
                $e->getMessage(),
                "SoapFault does not contain expected message."
            );
        } catch (\Exception $e) {
            $this->assertInputExceptionMessages($e);
        }

        if ($noExceptionOccurred) {
            $this->fail(
                "Exception was expected to be thrown when providing at "
                . "least one quote attachment id that doesn't exist."
            );
        }
    }

    /**
     * Assert for presence of Input exception messages.
     *
     * @param \Exception $e
     * @return void
     */
    private function assertInputExceptionMessages(\Exception $e)
    {
        $this->assertEquals(HTTPExceptionCodes::HTTP_BAD_REQUEST, $e->getCode());
        $exceptionData = $this->processRestExceptionResult($e);
        $expectedExceptionData = [
            'message' => 'Cannot obtain the requested data. You must fix the errors listed below first.',
            'errors' => [
                [
                    'message' => 'Requested attachment is not found. Row ID: %fieldName = %fieldValue',
                    'parameters' => [
                        'fieldName' => 'AttachmentID', 'fieldValue' => 999
                    ],
                ],
                [
                    'message' => 'Requested attachment is not found. Row ID: %fieldName = %fieldValue',
                    'parameters' => [
                        'fieldName' => 'AttachmentID', 'fieldValue' => 1000
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedExceptionData, $exceptionData);
    }
}
