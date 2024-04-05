<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Controller\Adminhtml\Quote\Create;

use Magento\Customer\Test\Fixture\Customer;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\LayoutInterface;
use Magento\NegotiableQuote\Block\Adminhtml\Quote\Create\Store\Select;
use Magento\NegotiableQuote\Controller\Adminhtml\AbstractTest;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Test\Fixture\Group as StoreGroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;

class StoreSelectTest extends AbstractTest
{
    /**
     * @var string
     */
    protected $resource = Draft::ADMIN_RESOURCE;

    /**
     * @var string
     */
    protected $uri = 'backend/quotes/quote_create/storeselect';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Test load store select success
     */
    #[
        AppArea('adminhtml'),
        DataFixture(WebsiteFixture::class, ['code' => 'website2', 'name' => 'Test Website'], as: 'website2'),
        DataFixture(
            StoreGroupFixture::class,
            ['website_id' => '$website2.id$', 'name' => 'Test Store'],
            as: 'store_group2'
        ),
        DataFixture(
            StoreFixture::class,
            ['store_group_id' => '$store_group2.id$' , 'name' => 'Test Store View'],
            as: 'store2'
        ),
        DataFixture(CustomerFixture::class, ['website_id' => '$website2.id$'], as: 'customer'),
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE, 'website2'),
    ]
    public function testExecuteSuccess()
    {
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();

        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customerId
        ]);

        /** @var LayoutInterface $layout */
        $layout = Bootstrap::getObjectManager()->get(LayoutInterface::class);
        $result = $layout->createBlock(Select::class)
            ->setTemplate('Magento_NegotiableQuote::quote/create/store/select.phtml')
            ->setData('customer_id', $customerId)
            ->toHtml();

        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals(true, $responseContent['success']);
        $this->assertEquals($result, $responseContent['content']);
        $this->assertStringContainsString('Test Website', $responseContent['content']);
        $this->assertStringContainsString('Test Store', $responseContent['content']);
        $this->assertStringContainsString('Test Store View', $responseContent['content']);
    }

    /**
     * Test load store select with invalid customer id
     *
     * @dataProvider executeInvalidCustomerDataProvider
     */
    #[
        AppArea('adminhtml'),
        DataFixture(Customer::class, as: 'customer'),
    ]
    public function testExecuteInvalidCustomerId($customerId, $message)
    {
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue([
            'customer_id' => $customerId
        ]);
        $this->dispatch($this->uri);
        $responseContent = \json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals(true, $responseContent['error']);
        $this->assertEquals($message, $responseContent['message']);
    }

    /**
     * @return array
     */
    public function executeInvalidCustomerDataProvider(): array
    {
        return [
            [
                'customer_id' => 100500,
                'message' => 'No such entity with customerId = 100500'
            ],
            [
                'customer_id' => null,
                'message' => 'Invalid Customer ID.'
            ],
            [
                'customer_id' => '',
                'message' => 'Invalid Customer ID.'
            ],
        ];
    }
}
