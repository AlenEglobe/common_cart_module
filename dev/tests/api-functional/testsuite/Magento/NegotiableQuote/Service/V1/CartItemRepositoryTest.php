<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Exception as HTTPExceptionCodes;

/**
 * Test negotiable quote prices recalculation after updating/removing quote items.
 */
class CartItemRepositoryTest extends WebapiAbstract
{
    const SERVICE_VERSION = 'V1';
    const SERVICE_NAME = 'quoteCartItemRepositoryV1';
    const RESOURCE_PATH = '/V1/carts/';

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
        $this->negotiableRepository = $this->objectManager->get(
            \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface::class
        );
        $this->productRepository = $this->objectManager->get(
            \Magento\Catalog\Api\ProductRepositoryInterface::class
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
     * Test that negotiable quote prices are recalculated correctly after adding item to quote.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testAddItem()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $product = $this->productRepository->get('simple');
        $product->setPrice(100);
        $this->productRepository->save($product);
        $requestData = [
            'cartItem' => [
                'sku' => $product->getSku(),
                'qty' => 7,
                'quote_id' => $this->quoteId,
            ],
        ];
        $this->addItemToCartRequest($requestData);
        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);
        $this->assertEquals($negotiableQuote->getOriginalTotalPrice(), 800);
        $this->assertEquals($negotiableQuote->getBaseOriginalTotalPrice(), 800);
        $this->assertEquals($negotiableQuote->getBaseNegotiatedTotalPrice(), 640);
        $this->assertEquals($negotiableQuote->getNegotiatedTotalPrice(), 640);
    }

    /**
     * Test that negotiable quote prices are recalculated correctly after removing item from quote.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_virtual.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testRemoveItem()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quote = end($quotes);
        $this->quoteId = $quote->getId();
        $simpleProduct = $this->productRepository->get('simple');
        $virtualProduct = $this->productRepository->get('virtual-product');
        $productToAddData = [
            'cartItem' => [
                'sku' => $virtualProduct->getSku(),
                'qty' => 7,
                'quote_id' => $this->quoteId,
            ],
        ];
        $this->addItemToCartRequest($productToAddData);
        $simpleProduct->setPrice(100);
        $this->productRepository->save($simpleProduct);
        $itemId = $quote->getItemByProduct($virtualProduct)->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . $this->quoteId . '/items/' . $itemId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'DeleteById',
            ],
        ];
        $requestData = [
            'cartId' => $this->quoteId,
            'itemId' => $itemId,
        ];
        $this->assertTrue($this->_webApiCall($serviceInfo, $requestData));
        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);
        $this->assertEquals($negotiableQuote->getOriginalTotalPrice(), 100);
        $this->assertEquals($negotiableQuote->getBaseOriginalTotalPrice(), 100);
        $this->assertEquals($negotiableQuote->getBaseNegotiatedTotalPrice(), 80);
        $this->assertEquals($negotiableQuote->getNegotiatedTotalPrice(), 80);
    }

    /**
     * Test that item can't be removed from a B2B quote if there is less than 2 items added.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testRemoveLastItemInCart()
    {
        $noExceptionOccurred = false;
        try {
            $customer = $this->customerRepository->get('email@companyquote.com');
            $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
            $quote = end($quotes);
            $this->quoteId = $quote->getId();
            $simpleProduct = $this->productRepository->get('simple');
            $itemId = $quote->getItemByProduct($simpleProduct)->getId();
            $serviceInfo = [
                'rest' => [
                    'resourcePath' => self::RESOURCE_PATH . $this->quoteId . '/items/' . $itemId,
                    'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
                ],
                'soap' => [
                    'service' => self::SERVICE_NAME,
                    'serviceVersion' => self::SERVICE_VERSION,
                    'operation' => self::SERVICE_NAME . 'DeleteById',
                ],
            ];
            $requestData = [
                'cartId' => $this->quoteId,
                'itemId' => $itemId,
            ];
            $this->_webApiCall($serviceInfo, $requestData);
            $noExceptionOccurred = true;
        } catch (\SoapFault $e) {
            $this->assertStringContainsString(
                'Cannot delete all items from a B2B quote. The quote must contain at least one item.',
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
     * Perform add item to cart request.
     *
     * @param array $requestData
     * @return void
     */
    private function addItemToCartRequest(array $requestData)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH .  $this->quoteId . '/items',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'Save',
            ],
        ];
        $this->_webApiCall($serviceInfo, $requestData);
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
            'message' => 'Cannot delete all items from a B2B quote. The quote must contain at least one item.',
        ];
        $this->assertEquals($expectedExceptionData, $exceptionData);
    }
}
