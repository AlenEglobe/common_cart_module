<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Exception as HTTPExceptionCodes;

/**
 * Tests negotiable quote update prices WebApi call.
 */
class NegotiableQuotePriceManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'negotiableQuoteNegotiableQuotePriceManagementV1';

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
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

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
        $this->productRepository = $this->objectManager->get(
            \Magento\Catalog\Api\ProductRepositoryInterface::class
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
     * Update quote prices.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testPricesUpdate()
    {
        $product = $this->productRepository->get('simple');
        $product->setPrice(100);
        $this->productRepository->save($product);
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/pricesUpdated',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'pricesUpdated',
            ],
        ];
        $result = $this->_webApiCall($serviceInfo, ['quoteIds' => [$this->quoteId]]);

        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);
        $this->assertTrue($result);
        $this->assertEquals($negotiableQuote->getOriginalTotalPrice(), 100);
        $this->assertEquals($negotiableQuote->getBaseOriginalTotalPrice(), 100);
        $this->assertEquals($negotiableQuote->getBaseNegotiatedTotalPrice(), 80);
        $this->assertEquals($negotiableQuote->getNegotiatedTotalPrice(), 80);
    }

    /**
     * Update quote prices with invalid quote ids.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testPricesUpdateWithInvalidQuoteIds()
    {
        $noExceptionOccurred = false;
        try {
            $customer = $this->customerRepository->get('email@companyquote.com');
            $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
            $this->quoteId = end($quotes)->getId();
            $invalidQuoteIds = array_merge([999, 1000], [$this->quoteId]);
            $serviceInfo = [
                'rest' => [
                    'resourcePath' => '/V1/negotiableQuote/pricesUpdated',
                    'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
                ],
                'soap' => [
                    'service' => self::SERVICE_READ_NAME,
                    'serviceVersion' => self::SERVICE_VERSION,
                    'operation' => self::SERVICE_READ_NAME . 'pricesUpdated',
                ],
            ];
            $this->_webApiCall($serviceInfo, ['quoteIds' => $invalidQuoteIds]);
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
            $this->fail("Exception was expected to be thrown when providing at least one quote id that doesn't exist.");
        }
    }

    /**
     * Assert for presence of Input exception messages.
     *
     * @param \Exception $e
     * @return void
     */
    private function assertInputExceptionMessages($e)
    {
        $this->assertEquals(HTTPExceptionCodes::HTTP_BAD_REQUEST, $e->getCode());
        $exceptionData = $this->processRestExceptionResult($e);
        $expectedExceptionData = [
            'message' => 'Cannot obtain the requested data. You must fix the errors listed below first.',
            'errors' => [
                [
                    'message' => 'Requested quote is not found. Row ID: %fieldName = %fieldValue',
                    'parameters' => [
                        'fieldName' => 'QuoteID', 'fieldValue' => 999
                    ],
                ],
                [
                    'message' => 'Requested quote is not found. Row ID: %fieldName = %fieldValue',
                    'parameters' => [
                        'fieldName' => 'QuoteID', 'fieldValue' => 1000
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedExceptionData, $exceptionData);
    }
}
