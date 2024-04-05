<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Exception;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\Quote;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\ResourceModel\QuoteIdMask;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\Status;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\GraphQl\NegotiableQuote\Fixtures\CustomerRequestNegotiableQuote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for getting Negotiable Quotes by filter
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetNegotiableQuoteByFilterTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;

    /**
     * @var Status
     */
    private $status;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMask
     */
    private $quoteIdMaskResource;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    private const SIMPLE_QUERY = <<<QUERY
{
  negotiableQuotes(
    filter: {name: {match: "quote_customer_send"}}
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      uid
      name
      buyer {
        firstname
      }
    }
  }
}
QUERY;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->status = $objectManager->get(Status::class);
        $this->quote = $objectManager->get(Quote::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->quoteIdMaskResource = $objectManager->get(QuoteIdMask::class);
    }

    /**
     * Test for getting quote details for a company admin filtered by quoteIds
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuotes
     *
     * @param array $negotiableQuoteData
     * @throws NoSuchEntityException
     * @throws AuthenticationException
     * @throws Exception
     */
    public function testGetNegotiableQuotesByIds(array $negotiableQuoteData): void
    {
        $customerEmail = 'email@companyquote.com';
        /** @var CustomerRequestNegotiableQuote $requestNegotiableQuoteFixture */
        $requestNegotiableQuoteFixture = Bootstrap::getObjectManager()->create(CustomerRequestNegotiableQuote::class);
        $requestedNegotiableQuotes = $requestNegotiableQuoteFixture->requestNegotiableQuotes(
            ['email' => 'email@companyquote.com', 'password' => 'password'],
            $negotiableQuoteData
        );
        $negotiableQuoteIds = [];
        foreach ($requestedNegotiableQuotes as $requestedNegotiableQuote) {
            array_push($negotiableQuoteIds, $requestedNegotiableQuote['uid']);
        }

        $query = <<<QUERY
{
  negotiableQuotes(filter: {ids: {in: ["{$negotiableQuoteIds[0]}", "{$negotiableQuoteIds[1]}"]}}) {
    total_count
    page_info{
      total_pages
      current_page
      page_size
    }
    items
    {
      uid
      name
      status
      created_at
      updated_at
      buyer {
          firstname
          lastname
      }
      comments{text}
     }
   }
}
QUERY;
        /** @var CustomerInterface $customer */
        $customer = $this->customerRepository->get($customerEmail);
        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders($customerEmail, 'password')
        );
        $this->assertNotEmpty($response['negotiableQuotes']['items'], 'No negotiable quotes returned');
        $this->assertCount(2, $response['negotiableQuotes']['items']);
        $this->assertArrayNotHasKey('errors', $response);
        $negotiableQuotes = $response['negotiableQuotes']['items'];
        $this->assertEquals(2, $response['negotiableQuotes']['total_count']);

        $this->assertArrayHasKey('page_info', $response['negotiableQuotes']);
        $pageInfo = $response['negotiableQuotes']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(20, $pageInfo['page_size']);

        foreach ($negotiableQuotes as $negotiableQuote) {
            $this->assertArrayHasKey('uid', $negotiableQuote);
            $this->assertArrayHasKey('name', $negotiableQuote);
            $this->assertArrayHasKey('status', $negotiableQuote);
            $this->assertArrayHasKey('created_at', $negotiableQuote);
            $this->assertArrayHasKey('updated_at', $negotiableQuote);

            $quoteId = $this->quoteIdMaskResource->getUnmaskedQuoteId($negotiableQuote['uid']);
            $quote = $this->cartRepository->get($quoteId);
            $negotiableQuoteModel = $quote->getExtensionAttributes()->getNegotiableQuote();

            $this->assertEquals($negotiableQuoteModel->getQuoteName(), $negotiableQuote['name']);
            $this->assertEquals(
                $negotiableQuoteModel->getStatus(),
                array_keys($this->status->getStatusLabels(), $negotiableQuote['status'])[0]
            );
            $this->assertEquals($customer->getFirstname(), $negotiableQuote['buyer']['firstname']);
            $this->assertEquals($customer->getLastname(), $negotiableQuote['buyer']['lastname']);
            $this->assertEquals($quote->getCreatedAt(), $negotiableQuote['created_at']);
            $this->assertEquals($quote->getUpdatedAt(), $negotiableQuote['updated_at']);
        }

        $this->deleteQuotes();
    }

    /**
     * @return array|array[]
     */
    public function dataProviderNegotiableQuotes(): array
    {
        return [
            'negotiableQuotes'=> [
                'items' =>[
                    [
                        'name' => 'Test Quote Name 1',
                        'comment' => 'Test Quote comment 1',
                        'productSku' => 'simple',
                        'productQuantity' => 2
                    ],
                    [
                        'name' => 'Test Quote Name 2',
                        'comment' => 'Test Quote comment 2',
                        'productSku' => 'simple_for_quote',
                        'productQuantity' => 2
                    ]
                ]
            ]
        ];
    }

    /**
     * Test for getting quote details for a customer by quoteIds
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_saved_as_draft.php
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableSnapshotQuotesByIds(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $negotiableQuoteId = array_key_last($negotiableQuotes);
        $negotiableQuote = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $quote = $this->cartRepository->get($negotiableQuote->getQuoteId());
        $snapshotQuote = $this->quote->getSnapshotQuote($quote);
        $maskedNegotiableQuoteId = $this->quoteIdMaskResource->getMaskedQuoteId((int)$quote->getId());
        $query = <<<QUERY
{
  negotiableQuotes(filter: {ids: {in: ["$maskedNegotiableQuoteId"]}}) {
    total_count
    page_info {
      total_pages
      current_page
      page_size
    }
    items {
        uid
        name
        status
        created_at
        updated_at
        items {
          id
          quantity
        }
        buyer {
            firstname
            lastname
        }
        prices {
            grand_total {value}
        }
     }
   }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
        $this->assertArrayNotHasKey('errors', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuotes']);
        $this->assertNotEmpty($response['negotiableQuotes']['items']);
        $negotiableQuoteResponse = $response['negotiableQuotes']['items'][0];

        $this->assertArrayHasKey('items', $negotiableQuoteResponse);
        $this->assertArrayHasKey('buyer', $negotiableQuoteResponse);
        $this->assertNotEmpty($negotiableQuoteResponse['uid']);
        $this->assertNotEmpty($negotiableQuoteResponse['items']);
        $this->assertNotEmpty($negotiableQuoteResponse['buyer']);
        $this->assertArrayHasKey('status', $negotiableQuoteResponse);
        $this->assertArrayHasKey('name', $negotiableQuoteResponse);
        $this->assertEquals('nq_customer_mask', $negotiableQuoteResponse['uid']);
        $this->assertEquals($negotiableQuote->getQuoteName(), $negotiableQuoteResponse['name']);
        $this->assertContains(
            $negotiableQuote->getStatus(),
            array_keys($this->status->getStatusLabels(), $negotiableQuoteResponse['status'])
        );
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $this->assertEquals($customer->getFirstname(), $negotiableQuoteResponse['buyer']['firstname']);
        $this->assertEquals($customer->getLastname(), $negotiableQuoteResponse['buyer']['lastname']);
        $this->assertEquals($snapshotQuote->getCreatedAt(), $negotiableQuoteResponse['created_at']);
        $this->assertEquals($snapshotQuote->getUpdatedAt(), $negotiableQuoteResponse['updated_at']);
        // verify number of items and their quantities
        $snapshotItems = $snapshotQuote->getAllVisibleItems();
        $this->assertEquals(count($snapshotItems), 2);
        $keyedSnapshotItems = array_reduce(
            $snapshotItems,
            function ($carry, $item) {
                $carry[$item['item_id']] = $item;
                return $carry;
            }
        );
        $keyedResponseItems = array_reduce(
            $snapshotItems,
            function ($carry, $item) {
                $carry[$item['item_id']] = $item;
                return $carry;
            }
        );
        foreach ($keyedSnapshotItems as $id => $snapshotItem) {
            $this->assertEquals($snapshotItem['qty'], $keyedResponseItems[$id]['qty']);
        }
        // verify prices
        $this->assertEquals(
            $snapshotQuote->getGrandTotal(),
            $negotiableQuoteResponse['prices']['grand_total']['value']
        );
        $this->assertNotEquals($negotiableQuote->getNegotiatedPriceValue(), $snapshotQuote->getGrandTotal());
    }

    /**
     * Test for getting quote details for a customer by quoteName
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @dataProvider dataProviderNegotiableQuotes
     * @param array $negotiableQuoteData
     * @throws Exception
     */
    public function testGetNegotiableQuotesByName(array $negotiableQuoteData): void
    {
        $customerEmail = 'email@companyquote.com';
        /** @var CustomerRequestNegotiableQuote $requestNegotiableQuoteFixture */
        $requestNegotiableQuoteFixture = Bootstrap::getObjectManager()->create(CustomerRequestNegotiableQuote::class);
        $requestNegotiableQuoteFixture->requestNegotiableQuotes(
            ['email' => 'email@companyquote.com', 'password' => 'password'],
            $negotiableQuoteData
        );
        $query = <<<QUERY
{
  negotiableQuotes(filter: {name: {match: "Test Quote Name 1"}}) {
    total_count
    page_info{
      total_pages
      current_page
      page_size
    }
    items
    {
      uid
      name
      status
      comments {
         uid
         author {lastname firstname}
         creator_type text
       }
      items {
      product { name sku}
      quantity
      }
       prices {
        grand_total { currency value}
        subtotal_including_tax {currency value}
        subtotal_excluding_tax{ currency value}
      }
      created_at
      updated_at
      buyer {
          firstname
          lastname
      }
     }
   }
}
QUERY;
        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders($customerEmail, 'password')
        );

        $this->assertArrayNotHasKey('errors', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuotes']);
        $this->assertArrayHasKey('page_info', $response['negotiableQuotes']);
        $this->assertEquals(1, $response['negotiableQuotes']['total_count']);
        $pageInfo = $response['negotiableQuotes']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(20, $pageInfo['page_size']);
        $this->assertEquals(1, $pageInfo['total_pages']);

        $this->assertNotEmpty($response['negotiableQuotes']['items'], 'No negotiable quotes returned');
        $this->assertCount(1, $response['negotiableQuotes']['items']);
        $negotiableQuote = $response['negotiableQuotes']['items'][0];
        $this->assertArrayHasKey('uid', $negotiableQuote);
        $this->assertArrayHasKey('name', $negotiableQuote);
        $this->assertArrayHasKey('status', $negotiableQuote);
        $this->assertArrayHasKey('created_at', $negotiableQuote);
        $this->assertArrayHasKey('updated_at', $negotiableQuote);
        $this->assertEquals('Test Quote Name 1', $negotiableQuote['name']);

        $this->assertArrayHasKey('comments', $negotiableQuote);
        $this->assertNotEmpty($negotiableQuote['comments']);
        $negotiableQuoteComment = $negotiableQuote['comments'][0];
        $this->assertEquals('Smith', $negotiableQuoteComment['author']['lastname']);
        $this->assertEquals('John', $negotiableQuoteComment['author']['firstname']);
        $this->assertEquals('BUYER', $negotiableQuoteComment['creator_type']);
        $this->assertEquals('Test Quote comment 1', $negotiableQuoteComment['text']);
        $this->assertEquals('SUBMITTED', $negotiableQuote['status']);
        $this->assertArrayHasKey('prices', $negotiableQuote);
        $negotiableQuotePrices = $negotiableQuote['prices'];
        $expectedPrice =
            [
                'grand_total' =>
                    [
                            'currency' => 'USD',
                            'value' => 20,
                    ],
                'subtotal_including_tax' =>
                    [
                            'currency' => 'USD',
                            'value' => 20,
                    ],
                'subtotal_excluding_tax' =>
                    [
                            'currency' => 'USD',
                            'value' => 20,
                    ]
                ];
        $this->assertResponseFields($negotiableQuotePrices, $expectedPrice);
        $negotiableQuoteItem = $negotiableQuote['items'][0];
        $this->assertEquals(2, $negotiableQuoteItem['quantity']);
        $this->assertEquals('Simple Product 1', $negotiableQuoteItem['product']['name']);
        $this->assertEquals('simple', $negotiableQuoteItem['product']['sku']);

        $this->deleteQuotes();
    }

    /**
     * Test for getting quote details for a customer by quoteIds and quoteName
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuoteIdsQuoteNames
     *
     * @param bool $includeQuoteIds
     * @param string $quoteName
     * @throws Exception
     */
    public function testGetNegotiableQuotesByQuoteIdsQuoteName(bool $includeQuoteIds, string $quoteName): void
    {
        $negotiableQuoteIds = $includeQuoteIds
            ? $this->generateMaskedQuoteIdsForCustomer('email@companyquote.com')
            : [];
        $stringNegotiableQuoteIds = '"' . implode('","', $negotiableQuoteIds) . '"';

        $query = <<<QUERY
{
  negotiableQuotes(filter: {ids: {in: [$stringNegotiableQuoteIds]}, name: {match: "{$quoteName}"}}) {
    total_count
    page_info{
      total_pages
      current_page
      page_size
    }
    items
    {
      uid
      name
      status
      created_at
      updated_at
      buyer {
          firstname
          lastname
      }
     }
   }
}
QUERY;

        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders('email@companyquote.com', 'password')
        );
        $negotiableQuotes = $response['negotiableQuotes']['items'];

        $this->assertArrayNotHasKey('errors', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuotes']);
        $this->assertArrayHasKey('page_info', $response['negotiableQuotes']);
        $this->assertCount(1, $response['negotiableQuotes']['items']);
        $this->assertEquals(1, $response['negotiableQuotes']['total_count']);
        $pageInfo = $response['negotiableQuotes']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(20, $pageInfo['page_size']);
        $customer = $this->customerRepository->get('email@companyquote.com');

        foreach ($negotiableQuotes as $negotiableQuote) {
            $this->assertArrayHasKey('uid', $negotiableQuote);
            $this->assertArrayHasKey('name', $negotiableQuote);
            $this->assertArrayHasKey('status', $negotiableQuote);
            $this->assertArrayHasKey('created_at', $negotiableQuote);
            $this->assertArrayHasKey('updated_at', $negotiableQuote);
            $this->assertCount(1, $response['negotiableQuotes']['items']);
            $this->assertEquals('nq_admin_mask', $negotiableQuote['uid']);
            $this->assertEquals('quote_customer_send', $negotiableQuote['name']);
            $this->assertEquals('SUBMITTED', $negotiableQuote['status']);
            $this->assertEquals($customer->getFirstname(), $negotiableQuote['buyer']['firstname']);
            $this->assertEquals($customer->getLastname(), $negotiableQuote['buyer']['lastname']);
        }
    }

    /**
     * @return array
     */
    public function dataProviderNegotiableQuoteIdsQuoteNames(): array
    {
        return [
            'empty_quote_uids' => [
                false,
                'quote_customer_send'
            ],
            'empty_quote_name' => [
                true,
                ''
            ],
            'quote_uids_quote_name' => [
                true,
                'quote_customer_send'
            ]
        ];
    }

    /**
     * @return array
     */
    public function dataProviderNegotiableQuoteSortOrder(): array
    {
        return [
            'sort_asc_by_name' => [
                'QUOTE_NAME',
                'ASC',
                ['nq_one', 'nq_three', 'nq_two', 'quote_customer_send_closed']
            ],
            'sort_desc_by_name' => [
                'QUOTE_NAME',
                'DESC',
                ['quote_customer_send_closed', 'nq_two', 'nq_three', 'nq_one']
            ]
        ];
    }

    /**
     * @return array
     */
    public function dataProviderNegotiableQuoteSortOrderWithFilter(): array
    {
        return [
            'sort_asc_by_name' => [
                'QUOTE_NAME',
                'ASC',
                ['nq_one', 'nq_three', 'nq_two']
            ],
            'sort_desc_by_name' => [
                'QUOTE_NAME',
                'DESC',
                ['nq_two', 'nq_three', 'nq_one']
            ],
        ];
    }

    /**
     * Test for getting quote details for a customer by invalid quoteIds
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderInvalidNegotiableQuoteIds
     *
     * @param array $negotiableQuoteIds
     * @throws Exception
     */
    public function testGetNegotiableQuotesByInvalidIds(array $negotiableQuoteIds): void
    {
        $stringNegotiableQuoteIds = '"' . implode('","', $negotiableQuoteIds) . '"';
        $query = <<<QUERY
{
  negotiableQuotes(
    filter: {ids: {in: [$stringNegotiableQuoteIds]}}
  ) {
    total_count
    page_info{
      total_pages
      current_page
      page_size
    }
    items
    {
      uid
     }
   }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
        $this->assertEquals(0, $response['negotiableQuotes']['total_count']);
    }

    /**
     * @return array
     */
    public function dataProviderInvalidNegotiableQuoteIds(): array
    {
        return [
            'not_available_quote_uids' => [
                [9999, 23423]
            ],
            'invalid_quote_uids' => [
                ['MQWE#$@765', '#$%@']
            ]
        ];
    }

    /**
     * Test for getting quote details for a customer by invalid quoteName
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderInvalidNegotiableQuoteName
     *
     * @param string $quoteName
     * @throws Exception
     */
    public function testGetNegotiableQuotesByInvalidName(string $quoteName): void
    {
        $query = <<<QUERY
{
  negotiableQuotes(filter: {name: {match: "{$quoteName}"}}) {
    total_count
    page_info{
      total_pages
      current_page
      page_size
    }
    items
    {
      uid
      name
      status
      created_at
      updated_at
      buyer {
          firstname
          lastname
      }
     }
   }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
        $this->assertEquals(0, $response['negotiableQuotes']['total_count']);
    }

    /**
     * @return array
     */
    public function dataProviderInvalidNegotiableQuoteName(): array
    {
        return [
            'invalid_quote_name' => [
                '123$%#$'
            ],
            'not_available' => [
                'MagentoInvalidData'
            ]
        ];
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuotes
     *
     * @param array $negotiableQuoteData
     * @param int $pageSize
     * @throws Exception
     */
    public function testPaging(array $negotiableQuoteData, int $pageSize = 1)
    {
        $customerEmail = 'email@companyquote.com';
        /** @var CustomerRequestNegotiableQuote $requestNegotiableQuoteFixture */
        $requestNegotiableQuoteFixture = Bootstrap::getObjectManager()->create(CustomerRequestNegotiableQuote::class);
        $requestNegotiableQuoteFixture->requestNegotiableQuotes(
            ['email' => 'email@companyquote.com', 'password' => 'password'],
            $negotiableQuoteData
        );

        $query = <<<QUERY
{
  negotiableQuotes(
    pageSize: {$pageSize}
    currentPage: %s
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      uid
      name
      status
      comments {
         uid
         author {lastname firstname}
         creator_type text
       }
    }
  }
}
QUERY;

        $page1Query = sprintf($query, 1);
        $page1Result = $this->graphQlQuery(
            $page1Query,
            [],
            '',
            $this->getCustomerAuthHeaders($customerEmail, 'password')
        );
        $this->assertArrayNotHasKey('errors', $page1Result);
        $this->assertEquals(2, $page1Result['negotiableQuotes']['total_count']);
        $pageInfo = $page1Result['negotiableQuotes']['page_info'];
        $this->assertEquals(1, $pageInfo['current_page']);
        $this->assertEquals(2, $pageInfo['total_pages']);
        $this->assertNotEmpty($page1Result['negotiableQuotes']['items']);
        $this->assertCount(1, $page1Result['negotiableQuotes']['items']);
        $this->assertEquals('SUBMITTED', $page1Result['negotiableQuotes']['items'][0]['status']);

        $lastPageQuery = sprintf($query, $pageInfo['total_pages']);
        $lastPageResult = $this->graphQlQuery(
            $lastPageQuery,
            [],
            '',
            $this->getCustomerAuthHeaders($customerEmail, 'password')
        );
        $this->assertEquals(2, $page1Result['negotiableQuotes']['total_count']);
        $pageInfo = $lastPageResult['negotiableQuotes']['page_info'];
        $this->assertEquals(2, $pageInfo['current_page']);
        $this->assertEquals(2, $pageInfo['total_pages']);
        $this->assertNotEmpty($lastPageResult['negotiableQuotes']['items']);
        $this->assertCount(1, $lastPageResult['negotiableQuotes']['items']);
        $this->assertEquals('SUBMITTED', $lastPageResult['negotiableQuotes']['items'][0]['status']);

        $this->deleteQuotes();
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/multiple_negotiable_quotes_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuoteSortOrder
     *
     * @param string $sortCode
     * @param string $sortDirection
     * @param array $expectedOrder
     * @throws Exception
     */
    public function testSorting(string $sortCode, string $sortDirection, array $expectedOrder)
    {
        $query = <<<QUERY
{
  negotiableQuotes(sort: {
    sort_field: $sortCode
    sort_direction: $sortDirection
  }) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      uid
      name
    }
    sort_fields {
      default
      options {
        value
        label
      }
    }
  }
}
QUERY;
        $page1Query = sprintf($query, 1);
        $page1Result = $this->graphQlQuery($page1Query, [], '', $this->getCustomerAuthHeaders());
        $i = 0;
        foreach ($page1Result['negotiableQuotes']['items'] as $item) {
            $this->assertEquals($expectedOrder[$i], $item['name']);
            $i++;
        }
        $sortFields = $page1Result['negotiableQuotes']['sort_fields'];
        $this->assertEquals($sortFields['default'], 'CREATED_AT');
        $sortFieldOptions = $sortFields['options'];
        $this->assertEquals(4, count($sortFieldOptions));
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/multiple_negotiable_quotes_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuoteSortOrderWithFilter
     *
     * @param string $sortCode
     * @param string $sortDirection
     * @param array $expectedOrder
     *
     * @throws Exception
     */
    public function testSortingWithFiltering(string $sortCode, string $sortDirection, array $expectedOrder)
    {
        $query = <<<QUERY
{
  negotiableQuotes(sort: {
    sort_field: $sortCode
    sort_direction: $sortDirection
  },
  filter: {
    name: {
      match: "nq"
    }
  }) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      uid
      name
    }
  }
}
QUERY;
        $i = 0;
        $query = sprintf($query, 1);
        $result = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
        foreach ($result['negotiableQuotes']['items'] as $item) {
            $this->assertEquals($expectedOrder[$i], $item['name']);
            $i++;
        }
    }

    /**
     * Test for the current page value to be 0
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCurrentPageZero(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('currentPage value must be greater than 0.');

        $query = <<<QUERY
{
  negotiableQuotes(
    filter: {name: {match: "quote_customer_send"}}
    currentPage: 0
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      name
    }
  }
}
QUERY;
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Test for the page size value to be 0
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testPageSizeZero(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('pageSize value must be greater than 0.');

        $query = <<<QUERY
{
  negotiableQuotes(
    filter: {name: {match: "quote_customer_send"}}
    pageSize: 0
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      name
    }
  }
}
QUERY;
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCurrentPageTooLarge()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The specified currentPage value 99 is greater than the number of pages available.'
        );

        $query = <<<QUERY
{
  negotiableQuotes(
    filter: {name: {match: "quote_customer_send"}}
    pageSize: 2
    currentPage: 99
  ) {
    total_count
    page_info {
      current_page
      page_size
      total_pages
    }
    items {
      name
    }
  }
}
QUERY;
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing for guest customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuotesWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $this->graphQlQuery(self::SIMPLE_QUERY);
    }

    /**
     * Testing for module enabled
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 0
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuotesNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing for customer belongs to a company
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_no_company.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuotesCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $this->getCustomerAuthHeaders('customernocompany@example.com'));
    }

    /**
     * Testing for feature enabled on company
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuotesNoCompanyFeatureEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Negotiable quotes are not enabled for the current customer\'s company.');

        /** @var CompanyInterfaceFactory $companyFactory */
        $companyFactory = Bootstrap::getObjectManager()->get(CompanyInterfaceFactory::class);
        /** @var CompanyInterface $company */
        $company = $companyFactory->create()->load('email@companyquote.com', 'company_email');
        $company->getExtensionAttributes()->getQuoteConfig()->setIsQuoteEnabled(false);
        /** @var CompanyRepositoryInterface $companyRepository */
        $companyRepository = Bootstrap::getObjectManager()->create(CompanyRepositoryInterface::class);
        $companyRepository->save($company);

        $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing for view permissions
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_no_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuotesNoViewPermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to view negotiable quotes.');

        $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing that a quote for a different store on the same website is accessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_store.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getCustomerAuthHeaders();
        $headers['Store'] = 'secondstore';

        $response = $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $headers);

        $this->assertEquals(1, $response['negotiableQuotes']['total_count']);
        $negotiableQuotes = $response['negotiableQuotes']['items'];
        $this->assertEquals('nq_customer_mask', $negotiableQuotes[0]['uid']);
    }

    /**
     * Testing that a quote for a different website is inaccessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_website.php
     * @magentoConfigFixture secondwebsitestore_store customer/account_share/scope 0
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForInvalidWebsite(): void
    {
        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getCustomerAuthHeaders();
        $headers['Store'] = 'secondwebsitestore';

        $response = $this->graphQlQuery(self::SIMPLE_QUERY, [], '', $headers);

        $this->assertEquals(0, $response['negotiableQuotes']['total_count']);
    }

    /**
     * Testing that a company admin can view quotes created by subordinates
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_no_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_manager.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForSubordinateByAdmin(): void
    {
        $query = self::SIMPLE_QUERY;
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("email@companyquote.com"));

        $this->assertEquals(3, $response['negotiableQuotes']['total_count']);
        $negotiableQuotes = [];
        foreach ($response['negotiableQuotes']['items'] as $negotiableQuote) {
            $negotiableQuotes[$negotiableQuote['uid']] = $negotiableQuote;
        }

        $this->assertArrayHasKey('nq_admin_mask', $negotiableQuotes);
        $this->assertEquals('John', $negotiableQuotes['nq_admin_mask']['buyer']['firstname']);
        $this->assertArrayHasKey('nq_manager_mask', $negotiableQuotes);
        $this->assertEquals('Manager', $negotiableQuotes['nq_manager_mask']['buyer']['firstname']);
        $this->assertArrayHasKey('nq_customer_mask', $negotiableQuotes);
        $this->assertEquals('Customer', $negotiableQuotes['nq_customer_mask']['buyer']['firstname']);
    }

    /**
     * Testing that a manager can view quotes created by subordinates if they have view_quotes_sub permission and not
     * quotes created by admin
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_manager.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForSubordinateByManager(): void
    {
        $query = self::SIMPLE_QUERY;
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("companymanager@example.com"));

        $this->assertEquals(2, $response['negotiableQuotes']['total_count']);
        $negotiableQuotes = [];
        foreach ($response['negotiableQuotes']['items'] as $negotiableQuote) {
            $negotiableQuotes[$negotiableQuote['uid']] = $negotiableQuote;
        }

        $this->assertArrayNotHasKey('nq_admin_mask', $negotiableQuotes);
        $this->assertArrayHasKey('nq_manager_mask', $negotiableQuotes);
        $this->assertEquals('Manager', $negotiableQuotes['nq_manager_mask']['buyer']['firstname']);
        $this->assertArrayHasKey('nq_customer_mask', $negotiableQuotes);
        $this->assertEquals('Customer', $negotiableQuotes['nq_customer_mask']['buyer']['firstname']);
    }

    /**
     * Testing that a manager cannot view quotes created by subordinates if they do not have view_quotes_sub permission
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_no_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_manager.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForSubordinateByManagerNoPermissions(): void
    {
        $query = self::SIMPLE_QUERY;
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("companymanager@example.com"));

        $this->assertEquals(1, $response['negotiableQuotes']['total_count']);
        $negotiableQuote = $response['negotiableQuotes']['items'][0];
        $this->assertEquals('nq_manager_mask', $negotiableQuote['uid']);
        $this->assertEquals('Manager', $negotiableQuote['buyer']['firstname']);
    }

    /**
     * Testing that even an admin cannot view quotes created by a user from another company
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_company.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuotesForSecondCompanyByAdmin(): void
    {
        $query = self::SIMPLE_QUERY;
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("email@secondcompany.com"));

        $this->assertEquals(0, $response['negotiableQuotes']['total_count']);
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws AuthenticationException
     */
    private function getCustomerAuthHeaders(
        string $username = 'customercompany22@example.com',
        string $password = 'password'
    ): array {
         $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }

    /**
     * Get masked ids for the customer's negotiable quotes
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateMaskedQuoteIdsForCustomer(string $customerEmail): array
    {
        $customer = $this->customerRepository->get($customerEmail);
        $customerQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteIds = [];

        foreach ($customerQuotes as $quote) {
            $quoteIds[] = (int)$quote->getId();
        }

        $maskedQuoteIds = $this->quoteIdMaskResource->getMaskedQuoteIds($quoteIds);

        return $maskedQuoteIds;
    }

    /**
     * Clean up the quotes
     *
     * @return void
     */
    private function deleteQuotes(): void
    {
        /** @var \Magento\Framework\Registry $registry */
        $registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->create();
        $quoteRepository = Bootstrap::getObjectManager()->create(QuoteRepository::class);
        $quotes = $quoteRepository->getList($searchCriteria)->getItems();
        foreach ($quotes as $quote) {
            $quote->delete();
        }
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', false);
    }
}
