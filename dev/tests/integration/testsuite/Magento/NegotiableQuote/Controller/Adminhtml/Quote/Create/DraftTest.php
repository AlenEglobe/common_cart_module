<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Controller\Adminhtml\Quote\Create;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\NegotiableQuote\Controller\Adminhtml\AbstractTest;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Create\Draft;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoAppArea adminhtml
 */
class DraftTest extends AbstractTest
{
    /**
     * @var string
     */
    protected $resource = Draft::ADMIN_RESOURCE;

    /**
     * @var string
     */
    protected $uri = 'backend/quotes/quote_create/draft';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_customer.php
     */
    public function testExecuteSuccess()
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get('customercompany22@example.com');
        $storeManager = $objectManager->get(StoreManagerInterface::class);

        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customer->getId(),
            'store_id' => $storeManager->getDefaultStoreView()->getId()
        ]);
        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseContent);
        $this->assertArrayHasKey('quote_id', $responseContent);
        $this->assertIsInt($responseContent['quote_id']);
    }

    /**
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_customer.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/cart_with_item_for_customer.php
     */
    public function testExecuteSuccessNonEmptyCustomerCart()
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get('customercompany22@example.com');
        $storeManager = $objectManager->get(StoreManagerInterface::class);

        $cartManagement = $objectManager->get(CartManagementInterface::class);
        $existingQuote = $cartManagement->getCartForCustomer($customer->getId());
        $this->assertEquals(1, $existingQuote->getItemsCount());

        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customer->getId(),
            'store_id' => $storeManager->getDefaultStoreView()->getId()
        ]);
        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseContent);
        $this->assertArrayHasKey('quote_id', $responseContent);
        $this->assertGreaterThan($existingQuote->getId(), $responseContent['quote_id']);

        $cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $quote = $cartRepository->get($responseContent['quote_id']);
        $this->assertEquals($customer->getId(), $quote->getCustomer()->getId());
        $this->assertEquals(0, $quote->getItemsCount());
        $customerCurrentQuote = $cartManagement->getCartForCustomer($customer->getId());
        $this->assertEquals(1, $customerCurrentQuote->getItemsCount());
        $this->assertEquals($existingQuote->getId(), $customerCurrentQuote->getId());
    }

    /**
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active true
     * @dataProvider executeInvalidCustomerDataProvider
     * @param $customerId
     * @param $message
     * @return void
     */
    public function testExecuteInvalidCustomer($customerId, $message)
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customerId,
            'store_id' => $storeManager->getDefaultStoreView()->getId()
        ]);
        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseContent);
        $this->assertStringContainsString($message, $responseContent['message']);
    }

    public function executeInvalidCustomerDataProvider()
    {
        return [
            [
                'customer_id' => 100500,
                'message' => 'No such entity with customerId = 100500'
            ],
            [
                'customer_id' => null,
                'message' => 'Invalid Customer or Store ID.'
            ],
            [
                'customer_id' => '',
                'message' => 'Invalid Customer or Store ID.'
            ],
        ];
    }

    /**
     * @dataProvider executeInvalidStoreDataProvider
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_customer.php
     *
     * @param $storeId
     * @param $message
     * @return void
     */
    public function testExecuteInvalidStore($storeId, $message)
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get('customercompany22@example.com');
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customer->getId(),
            'store_id' => $storeId
        ]);
        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseContent);
        $this->assertStringContainsString(
            $message,
            $responseContent['message']
        );
    }

    public function executeInvalidStoreDataProvider()
    {
        return [
            [
                'store_id' => 100500,
                'message' => 'The store that was requested wasn\'t found. Verify the store and try again.'
            ],
            [
                'store_id' => null,
                'message' => 'Invalid Customer or Store ID.'
            ],
            [
                'store_id' => '',
                'message' => 'Invalid Customer or Store ID.'
            ],
        ];
    }
}
