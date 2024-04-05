<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\NegotiableQuote\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Tests for negotiable quote shipping action.
 *
 * @magentoAppIsolation enabled
 */
class NegotiableQuoteShippingManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'negotiableQuoteNegotiableQuoteShippingManagementV1';

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
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    private $quoteManager;

    /**
     * @var \Magento\Quote\Api\CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var \Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface
     */
    private $negotiableManagement;

    /**
     * @var \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface
     */
    private $negotiableRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory
     */
    private $quoteCollectionFactory;

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
        $this->quoteManager = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->cartItemRepository = $this->objectManager->get(\Magento\Quote\Api\CartItemRepositoryInterface::class);
        $this->negotiableRepository = $this->objectManager->get(
            \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface::class
        );
        $this->negotiableManagement = $this->objectManager->get(
            \Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface::class
        );
        $this->quoteCollectionFactory = $this->objectManager->get(
            \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory::class
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
     * Test for add negotiable quote shipping method with API.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testSetShippingMethod()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quote = end($quotes);
        $this->quoteId = $quote->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId . '/shippingMethod',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'setShippingMethod',
            ],
        ];
        $shippingMethod = 'flatrate_flatrate';
        $result = $this->_webApiCall(
            $serviceInfo,
            [
                'quoteId' => $this->quoteId,
                'shippingMethod' => $shippingMethod
            ]
        );
        $this->assertTrue($result, 'Negotiable quote shipping method isn\'t set');

        $updatedQuote = $this->getQuote();
        $addedShippingMethod = $updatedQuote->getShippingAddress()->getShippingMethod();
        $this->assertEquals(
            $shippingMethod,
            $addedShippingMethod,
            'Negotiable quote shipping method isn\'t equal to added via API'
        );
    }

    /**
     * Test for update negotiable quote shipping method with API.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store carriers/freeshipping/active true
     * @magentoConfigFixture default_store carriers/freeshipping/free_shipping_subtotal 0
     */
    public function testUpdateShippingMethod()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quote = end($quotes);
        $this->quoteId = $quote->getId();
        $quote = $this->quoteRepository->get($this->quoteId);
        $quote->getExtensionAttributes()
            ->getShippingAssignments()[0]
            ->getShipping()
            ->setMethod('freeshipping_freeshipping');
        $this->quoteRepository->save($quote);
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/' . $this->quoteId . '/shippingMethod',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'setShippingMethod',
            ],
        ];
        $shippingMethod = 'flatrate_flatrate';
        $result = $this->_webApiCall(
            $serviceInfo,
            [
                'quoteId' => $this->quoteId,
                'shippingMethod' => $shippingMethod
            ]
        );
        $this->assertTrue($result, 'Negotiable quote shipping method isn\'t updated');

        $updatedQuote = $this->getQuote();
        $updatedShippingMethod = $updatedQuote->getShippingAddress()->getShippingMethod();
        $this->assertEquals(
            $shippingMethod,
            $updatedShippingMethod,
            'Negotiable quote shipping method isn\'t equal to updated via API'
        );
    }

    /**
     * Retrieve non-cached quote.
     *
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    private function getQuote()
    {
        $quoteCollection = $this->quoteCollectionFactory->create();
        return $quoteCollection->addFieldToFilter('entity_id', $this->quoteId)->getFirstItem();
    }
}
