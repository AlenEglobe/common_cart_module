<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test that quote totals have negotiable quote totals extension attribute with correct totals set.
 */
class QuoteTotalsTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'quoteCartTotalRepositoryV1';

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
     * Test that get cart totals returns correct negotiable quote totals extension attributes.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testGetQuoteTotals()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/carts/' . $this->quoteId . '/totals',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => 'V1',
                'operation' => self::SERVICE_READ_NAME . 'get',
            ],
        ];

        $cartTotals = $this->_webApiCall($serviceInfo, ['cartId' => $this->quoteId]);
        $quote = $this->quoteRepository->get($this->quoteId);

        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getNegotiatedPriceType(),
            $cartTotals['extension_attributes']['negotiable_quote_totals']['negotiated_price_type']
        );
        $this->assertEquals(
            $quote->getExtensionAttributes()->getNegotiableQuote()->getNegotiatedPriceValue(),
            $cartTotals['extension_attributes']['negotiable_quote_totals']['negotiated_price_value']
        );
    }
}
