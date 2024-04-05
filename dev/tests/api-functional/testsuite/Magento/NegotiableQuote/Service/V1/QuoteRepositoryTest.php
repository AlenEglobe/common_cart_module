<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Exception as HTTPExceptionCodes;

/**
 * Test that quote has negotiable quote extension attribute with correct totals set.
 */
class QuoteRepositoryTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'quoteCartRepositoryV1';

    const SERVICE_NAME = 'negotiableQuoteNegotiableCartRepositoryV1';

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
     * Test that get cart returns correct negotiable quote attributes.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testGetQuote()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/carts/' . $this->quoteId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];
        $cartData = $this->_webApiCall($serviceInfo, ['cartId' => $this->quoteId]);
        $quote = $this->quoteRepository->get($this->quoteId);
        $this->assertEquals($quote->getId(), $cartData['id']);
        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getOriginalTotalPrice(),
            $cartData['extension_attributes']['negotiable_quote']['original_total_price'],
            '',
            0.1
        );
        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getBaseOriginalTotalPrice(),
            $cartData['extension_attributes']['negotiable_quote']['base_original_total_price'],
            '',
            0.1
        );
        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getNegotiatedTotalPrice(),
            $cartData['extension_attributes']['negotiable_quote']['negotiated_total_price'],
            '',
            0.1
        );
        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getBaseNegotiatedTotalPrice(),
            $cartData['extension_attributes']['negotiable_quote']['base_negotiated_total_price'],
            '',
            0.1
        );
    }

    /**
     * Test that negotiable quote data is set after update quote attributes.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testUpdateQuote()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'Save',
            ],
        ];
        $quoteData = $this->getNegotiableAttributesData();
        $cartData = $this->prepareQuoteData($quoteData);
        $this->_webApiCall($serviceInfo, ['quote' => $cartData]);
        $newCartData = $this->prepareQuoteData([]);
        $this->assertTrue(in_array($quoteData['customer_note'], $newCartData));
        $this->assertTrue(in_array($quoteData['extension_attributes']['negotiable_quote']['quote_name'], $newCartData));
    }

    /**
     * Test that correct error messages are returned when trying to update quote attributes that can't be updated.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testUpdateQuoteWithInvalidAttributes()
    {
        $this->_markTestAsRestOnly();
        $noExceptionOccurred = false;
        try {
            $customer = $this->customerRepository->get('email@companyquote.com');
            $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
            $this->quoteId = end($quotes)->getId();
            $serviceInfo = [
                'rest' => [
                    'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId,
                    'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT
                ],
                'soap' => [
                    'service' => self::SERVICE_READ_NAME,
                    'serviceVersion' => self::SERVICE_VERSION,
                    'operation' => self::SERVICE_READ_NAME . 'Save',
                ],
            ];
            $incorrectQuoteData = $this->getIncorrectNegotiableAttributesData(false);
            $cartData = $this->prepareQuoteData($incorrectQuoteData);
            $this->_webApiCall($serviceInfo, ['quote' => $cartData]);
            $noExceptionOccurred = true;
        } catch (\Exception $e) {
            $this->assertInputExceptionMessages($e);
        }

        if ($noExceptionOccurred) {
            $this->fail(
                'Exception was expected to be thrown when providing at least one quote id that doesn\'t exist.'
            );
        }
    }

    /**
     * Test that correct error messages are returned when trying to update quote with invalid negotiable quote id.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testUpdateQuoteWithInvalidNegotiableQuoteId()
    {
        $this->_markTestAsRestOnly();
        $noExceptionOccurred = false;
        try {
            $customer = $this->customerRepository->get('email@companyquote.com');
            $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
            $this->quoteId = end($quotes)->getId();
            $serviceInfo = [
                'rest' => [
                    'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId,
                    'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT
                ],
                'soap' => [
                    'service' => self::SERVICE_READ_NAME,
                    'serviceVersion' => self::SERVICE_VERSION,
                    'operation' => self::SERVICE_READ_NAME . 'Save',
                ],
            ];
            $incorrectQuoteData = $this->getIncorrectNegotiableAttributesData();
            $cartData = $this->prepareQuoteData($incorrectQuoteData);
            $this->_webApiCall($serviceInfo, ['quote' => $cartData]);
            $noExceptionOccurred = true;
        } catch (\Exception $e) {
            $this->assertEquals(HTTPExceptionCodes::HTTP_BAD_REQUEST, $e->getCode());
            $exceptionData = $this->processRestExceptionResult($e);
            $expectedExceptionData = [
                'message' => 'You cannot update the requested attribute. Row ID: %fieldName = %fieldValue.',
                'parameters' => [
                    'fieldName' => 'quote_id', 'fieldValue' => 9999
                ],
            ];
            $this->assertEquals($expectedExceptionData, $exceptionData);
        }

        if ($noExceptionOccurred) {
            $this->fail(
                'Exception was expected to be thrown when providing at least one quote id that doesn\'t exist.'
            );
        }
    }

    /**
     * Prepare quote data for quote update with invalid attribute values.
     *
     * @param array $updateData Quote fields to update with API call
     * @return array
     */
    private function prepareQuoteData(array $updateData)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/carts/' . $this->quoteId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
                null,
                'default'
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];
        $cartData = $this->_webApiCall($serviceInfo, ['cartId' => $this->quoteId]);
        $result = array_replace_recursive($cartData, $updateData);
        unset($result['extension_attributes']['negotiable_quote']['original_total_price']);
        unset($result['extension_attributes']['negotiable_quote']['base_original_total_price']);
        unset($result['extension_attributes']['negotiable_quote']['negotiated_total_price']);
        unset($result['extension_attributes']['negotiable_quote']['base_negotiated_total_price']);
        $result['id'] = $this->quoteId;
        return $result;
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
            'message' => 'One or more input exceptions have occurred.',
            'errors' => [
                [
                    'message' => 'You cannot update the requested attribute. Row ID: %fieldName = %fieldValue.',
                    'parameters' => [
                        'fieldName' => 'created_at', 'fieldValue' => '0000-00-00 00:00:00'
                    ],
                ],
                [
                    'message' => 'You cannot update the requested attribute. Row ID: %fieldName = %fieldValue.',
                    'parameters' => [
                        'fieldName' => 'customer_id', 'fieldValue' => 9999
                    ],
                ],
                [
                    'message' => 'You cannot update the requested attribute. Row ID: %fieldName = %fieldValue.',
                    'parameters' => [
                        'fieldName' => 'store_id', 'fieldValue' => 9999
                    ],
                ],
                [
                    'message' => 'Cannot set the shipping price. You must select a shipping method first.',
                    'parameters' => [],
                ],
            ],
        ];
        $this->assertEquals($expectedExceptionData, $exceptionData);
    }

    /**
     * Return negotiable quote data with attributes that can't be updated.
     *
     * @param bool $testWithInvalidId [optional]
     * @return array
     */
    private function getIncorrectNegotiableAttributesData($testWithInvalidId = true)
    {
        return [
            'created_at' => '0000-00-00 00:00:00',
            'customer' => [
                'id' => 9999
            ],
            'store_id' => 9999,
            'extension_attributes' => [
                'negotiable_quote' => [
                    'quote_id' => $testWithInvalidId ? 9999 : $this->quoteId,
                    'shipping_price' => 15
                ]
            ]
        ];
    }

    /**
     * Return negotiable quote data with attributes that can be updated.
     *
     * @return array
     */
    private function getNegotiableAttributesData()
    {
        return [
            'customer_note' => 'Testing customer note',
            'extension_attributes' => [
                'negotiable_quote' => [
                    'quote_name' => 'Updated My Quote',
                    'negotiated_price_value' => '15',
                ]
            ]
        ];
    }
}
