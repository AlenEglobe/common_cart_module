<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\ViewModel\Quote\Create;

use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Test\Fixture\Store;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 */
class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $viewModel;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->viewModel = Bootstrap::getObjectManager()->create(Data::class);
        parent::setUp();
    }

    /**
     * Test get quote data without customer id provided and with 1 store
     */
    #[
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE)
    ]
    public function testGetQuoteData()
    {
        $responseContent = \json_decode($this->viewModel->getQuoteData(), true);

        $this->assertStringContainsString('quotes/quote/view', $responseContent['view_url']);
        $this->assertStringContainsString(
            'quotes/quote_create/draft',
            $responseContent['create_draft_quote_url']
        );
        $this->assertStringContainsString(
            'quotes/quote_create/storeselect',
            $responseContent['store_select_url']
        );
        $this->assertEquals(1, $responseContent['store_id']);
        $this->assertArrayNotHasKey('customer_id', $responseContent);
        $this->assertArrayNotHasKey('customer_name', $responseContent);
        $this->assertArrayNotHasKey('create_url', $responseContent);
    }

    /**
     * Test get quote data without customer id provided and with 1 store where Negotiable Quote is not active
     */
    #[
        Config('btob/website_configuration/negotiablequote_active', '0', ScopeInterface::SCOPE_WEBSITE)
    ]
    public function testGetQuoteDataWithNegotiableQuoteDisabled()
    {
        $responseContent = \json_decode($this->viewModel->getQuoteData(), true);

        $this->assertStringContainsString('quotes/quote/view', $responseContent['view_url']);
        $this->assertStringContainsString(
            'quotes/quote_create/draft',
            $responseContent['create_draft_quote_url']
        );
        $this->assertStringContainsString(
            'quotes/quote_create/storeselect',
            $responseContent['store_select_url']
        );
        $this->assertArrayNotHasKey('store_id', $responseContent);
        $this->assertArrayNotHasKey('customer_id', $responseContent);
        $this->assertArrayNotHasKey('customer_name', $responseContent);
        $this->assertArrayNotHasKey('create_url', $responseContent);
    }

    /**
     * Test get quote data with customer id provided
     *
     * @dataProvider getQuoteDataWithCustomerIdDataProvider
     */
    #[
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE),
        DataFixture(Customer::class, as: 'customer')
    ]
    public function testGetQuoteDataWithCustomerId($param)
    {
        /** @var RequestInterface $request */
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);

        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        $customerId = $customer->getId();

        $request->setParam($param, $customerId);
        $responseContent = \json_decode($this->viewModel->getQuoteData(), true);

        $this->assertStringContainsString('quotes/quote/view', $responseContent['view_url']);
        $this->assertStringContainsString(
            'quotes/quote_create/draft',
            $responseContent['create_draft_quote_url']
        );
        $this->assertStringContainsString(
            'quotes/quote_create/storeselect',
            $responseContent['store_select_url']
        );
        $this->assertStringContainsString('quotes/quote_create/', $responseContent['create_url']);
        $this->assertEquals($customerId, $responseContent['customer_id']);
        $this->assertEquals($customer->getName(), $responseContent['customer_name']);
        $this->assertEquals(1, $responseContent['store_id']);
    }

    public function getQuoteDataWithCustomerIdDataProvider(): array
    {
        return [
            ['customer_id'],
            ['id'],
        ];
    }

    /**
     * Test get quote data with multiple stores
     */
    #[
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE),
        DataFixture(Store::class, as: 'store')
    ]
    public function testGetQuoteDataWithMultipleStores()
    {
        $responseContent = \json_decode($this->viewModel->getQuoteData(), true);

        $this->assertStringContainsString('quotes/quote/view', $responseContent['view_url']);
        $this->assertStringContainsString(
            'quotes/quote_create/draft',
            $responseContent['create_draft_quote_url']
        );
        $this->assertStringContainsString(
            'quotes/quote_create/storeselect',
            $responseContent['store_select_url']
        );
        $this->assertArrayNotHasKey('store_id', $responseContent);
        $this->assertArrayNotHasKey('customer_id', $responseContent);
        $this->assertArrayNotHasKey('customer_name', $responseContent);
        $this->assertArrayNotHasKey('create_url', $responseContent);
    }
}
