<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Controller\Adminhtml;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Logging\Model\Event;
use Magento\Logging\Model\ResourceModel\Event\Collection;
use Magento\Logging\Model\ResourceModel\Event\CollectionFactory;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @magentoConfigFixture default_store btob/website_configuration/company_active true
 * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
 */
class LoggingTest extends AbstractBackendController
{
    /**
     * @var array
     */
    private $loggingHelper = [
        'quote_save'        => [
            'uri'        => 'backend/quotes/quote/save',
            'action'     => 'save',
            'fullAction' => 'quotes_quote_save'
        ],
        'quote_send'        => [
            'uri'        => 'backend/quotes/quote/send',
            'action'     => 'save',
            'fullAction' => 'quotes_quote_send'
        ],
        'quote_decline'     => [
            'uri'        => 'backend/quotes/quote/decline/quote_id/%s',
            'action'     => 'save',
            'fullAction' => 'quotes_quote_decline'
        ],
        'quote_massDecline' => [
            'uri'        => 'backend/quotes/quote/massDeclineCheck',
            'action'     => 'massUpdate',
            'fullAction' => 'quotes_quote_massDeclineCheck'
        ],
    ];

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var Collection
     */
    private $eventCollectionFactory;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableRepository;

    /**
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store btob/website_configuration/company_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     *
     */
    public function testSaveAction(): void
    {
        $helperData = $this->loggingHelper['quote_save'];
        $customer   = $this->customerRepository->get('email@companyquote.com');

        $quotes  = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quoteId = end($quotes)->getId();

        // Prepare request data
        $postData = [
            'quote_id'                     => $quoteId,
            'quote'                        => [
                'expiration_period' => date('Y-m-d')
            ],
            'dataSend'                     => json_encode(
                [
                    'quote_id'                     => $quoteId,
                    'quote'                        => [
                        'items'       => [
                            0 => [
                                'id'         => 1,
                                'qty'        => 1,
                                'sku'        => 'simple',
                                'productSku' => 'simple',
                                'config'     => ''
                            ]
                        ],
                        'addItems'    => [],
                        'proposed'    => [
                            "type"  => 1,
                            "value" => ""
                        ],
                        'recalcPrice' => 1
                    ],
                    'negotiable_quote_update_flag' => true
                ]
            ),
            'negotiable_quote_update_flag' => true,
            'comment'                      => 'Negotiable Quote Save Draft'
        ];

        // dispatch save action request
        $this->getRequest()->setPostValue($postData)->setMethod(Http::METHOD_POST);
        $this->dispatch($helperData['uri'] . '/?isAjax=true');

        // assert the save action success log
        $this->assert($helperData, Event::RESULT_SUCCESS);
    }

    /**
     * Assert the given event log, or last event log
     *
     * @param array $assertData
     * @param string $status 'success' | 'failure'
     * @param $event
     *
     */
    private function assert($assertData, $status, $event = null)
    {
        // Get the recent event object to assert
        $event = $event ?? $this->getRecentEvent();

        $this->assertEquals($assertData['action'], $event->getAction());
        $this->assertEquals($assertData['fullAction'], $event->getFullaction());
        $this->assertEquals($status, $event->getStatus());
    }

    /**
     * Returns latest logging entry
     *
     * @return Event
     */
    private function getRecentEvent(): Event
    {
        $eventCollection = $this->eventCollectionFactory->create();
        $eventCollection->setOrder('log_id', Collection::SORT_ORDER_DESC);

        return $eventCollection->getFirstItem();
    }

    /**
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store btob/website_configuration/company_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_can_decline.php
     *
     */
    public function testDeclineAction(): void
    {
        $helperData = $this->loggingHelper['quote_decline'];
        $customer   = $this->customerRepository->get('email@companyquote.com');

        $quotes  = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quoteId = end($quotes)->getId();

        $uri = sprintf($helperData['uri'], $quoteId);

        // Prepare request data
        $postData = [
            'quote_message' => 'Negotiable Quote Decline'
        ];

        // dispatch decline action request
        $this->getRequest()->setPostValue($postData)->setMethod(Http::METHOD_POST);
        $this->dispatch($uri);

        // assert the decline action success log
        $this->assert($helperData, Event::RESULT_SUCCESS);
    }

    /**
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store btob/website_configuration/company_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     *
     */
    public function testSendAction(): void
    {
        $helperData = $this->loggingHelper['quote_send'];
        $customer   = $this->customerRepository->get('email@companyquote.com');

        $quotes  = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quoteId = end($quotes)->getId();

        // Prepare request data
        $postData = [
            'quote_id'                     => $quoteId,
            'quote'                        => [
                'expiration_period' => date('Y-m-d')
            ],
            'dataSend'                     => json_encode(
                [
                    'quote_id'                     => $quoteId,
                    'quote'                        => [
                        'items'       => [
                            0 => [
                                'id'         => 1,
                                'qty'        => 1,
                                'sku'        => 'simple',
                                'productSku' => 'simple',
                                'config'     => ''
                            ]
                        ],
                        'addItems'    => [],
                        'proposed'    => [
                            "type"  => 1,
                            "value" => 10
                        ],
                        'recalcPrice' => 1
                    ],
                    'negotiable_quote_update_flag' => true
                ]
            ),
            'negotiable_quote_update_flag' => true,
            'comment'                      => 'Negotiable Quote Send'
        ];

        // dispatch send action request
        $this->getRequest()->setPostValue($postData)->setMethod(Http::METHOD_POST);
        $this->dispatch($helperData['uri'] . '/?isAjax=true');

        // assert the send action success log
        $this->assert($helperData, Event::RESULT_SUCCESS);
    }

    /**
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store btob/website_configuration/company_active true
     * @magentoDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_can_decline.php
     *
     */
    public function testMassDeclineAction(): void
    {
        $helperData = $this->loggingHelper['quote_massDecline'];
        $customer   = $this->customerRepository->get('email@companyquote.com');

        $quotes  = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $quoteId = end($quotes)->getId();

        // Prepare request data
        $postData = [
            'selected'  => [$quoteId],
            'namespace' => 'negotiable_quote_grid'
        ];

        // dispatch Mass Decline action request
        $this->getRequest()->setPostValue($postData)->setMethod(Http::METHOD_POST);
        $this->dispatch($helperData['uri']);

        // assert the mass decline action success log
        $this->assert($helperData, Event::RESULT_SUCCESS);
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->searchCriteriaBuilder  = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $this->eventCollectionFactory = $this->_objectManager->get(CollectionFactory::class);
        $this->customerRepository     = $this->_objectManager->get(CustomerRepositoryInterface::class);
        $this->negotiableRepository   = $this->_objectManager->get(NegotiableQuoteRepositoryInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->searchCriteriaBuilder  = null;
        $this->eventCollectionFactory = null;
        $this->customerRepository     = null;
        $this->negotiableRepository   = null;
    }
}
