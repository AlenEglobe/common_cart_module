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
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\Model\Mutation\BatchResult;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\ResourceModel\QuoteIdMask;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\GraphQl\NegotiableQuote\Fixtures\CustomerRequestNegotiableQuote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to close negotiable quotes.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CloseNegotiableQuotesTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiableQuoteManagement;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var QuoteIdMask
     */
    private $quoteIdMaskResource;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->negotiableQuoteManagement = $objectManager->get(NegotiableQuoteManagementInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
        $this->quoteIdMaskResource = $objectManager->get(QuoteIdMask::class);
    }

    /**
     * Tests that a single negotiable quote can successfully be closed.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseNegotiableQuote(): void
    {
        // Execute mutation
        $query = $this->getMutation('"nq_customer_mask"');
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_SUCCESS, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultSuccess($responseData['operation_results'][0], 'nq_customer_mask');

        // Assert closed_quotes data
        $this->assertCount(1, $responseData['closed_quotes']);
        $this->assertEquals('nq_customer_mask', $responseData['closed_quotes'][0]['uid']);
        $this->assertEquals('quote_customer_send', $responseData['closed_quotes'][0]['name']);
        $this->assertCount(2, $responseData['closed_quotes'][0]['history']);
        $closedQuotesHistory = $responseData['closed_quotes'][0]['history'];
        $this->assertEquals('CREATED', array_first($closedQuotesHistory)['change_type']);
        $this->assertEquals('CLOSED', array_last($closedQuotesHistory)['change_type']);
        $this->assertEquals(1, $responseData['negotiable_quotes']['total_count']);
        $negotiableQuoteItems = $responseData['negotiable_quotes']['items'];
        $this->assertEquals('nq_customer_mask', $negotiableQuoteItems[0]['uid']);
        $this->assertEquals('quote_customer_send', $negotiableQuoteItems[0]['name']);
        $this->assertEquals('CLOSED', $negotiableQuoteItems[0]['status']);
        $this->assertCount(2, $negotiableQuoteItems[0]['history']);
        $this->assertEquals('CLOSED', array_last($negotiableQuoteItems[0]['history'])['change_type']);
        $this->assertEquals('CREATED', array_first($negotiableQuoteItems[0]['history'])['change_type']);
    }

    /**
     * Tests that multiple negotiable quotes can successfully be closed.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderNegotiableQuotes
     *
     * @param array $negotiableQuoteData
     * @throws Exception
     */
    public function testCloseMultipleNegotiableQuotesSuccess(array $negotiableQuoteData): void
    {
        try {
            // Create negotiable quotes
            /** @var CustomerRequestNegotiableQuote $requestNegotiableQuoteFixture */
            $requestNegotiableQuoteFixture = Bootstrap::getObjectManager()->create(
                CustomerRequestNegotiableQuote::class
            );
            $fixtureQuotes = $requestNegotiableQuoteFixture->requestNegotiableQuotes(
                ['email' => 'customercompany22@example.com', 'password' => 'password'],
                $negotiableQuoteData
            );

            // Execute mutation
            $uidsToClose = '"' . $fixtureQuotes[0]['uid'] . '","' . $fixtureQuotes[1]['uid'] . '"';
            $mutation = $this->getMutation($uidsToClose);
            $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
            $responseData = $response['closeNegotiableQuotes'] ?? [];

            // Assert has expected fields
            $this->assertHasExpectedFields($responseData);

            // Assert result_status
            $this->assertEquals(BatchResult::STATUS_SUCCESS, $responseData['result_status']);

            // Assert operation_results
            $this->assertCount(2, $responseData['operation_results']);
            $this->assertOperationResultSuccess($responseData['operation_results'][0], $fixtureQuotes[0]['uid']);
            $this->assertOperationResultSuccess($responseData['operation_results'][1], $fixtureQuotes[1]['uid']);

            // Assert closed_quotes data
            $this->assertCount(2, $response['closeNegotiableQuotes']['closed_quotes']);
            $this->assertEquals(2, $response['closeNegotiableQuotes']['negotiable_quotes']['total_count']);
            $negotiableQuoteItems = $response['closeNegotiableQuotes']['negotiable_quotes']['items'];
            $this->assertCount(2, $response['closeNegotiableQuotes']['negotiable_quotes']['items']);
            foreach ($negotiableQuoteItems as $negotiableQuoteItem) {
                $this->assertEquals('CLOSED', $negotiableQuoteItem['status']);
                $expectedQuoteHistory = [
                    ['change_type' => 'CREATED'],
                    ['change_type' => 'CLOSED' ]
                ];
                $this->assertResponseFields($negotiableQuoteItem['history'], $expectedQuoteHistory);
            }
            foreach ($negotiableQuoteItems as $index => $negotiableQuoteItem) {
                $this->assertEquals($fixtureQuotes[$index]['name'], $negotiableQuoteItems[$index]['name']);
            }
        } finally {
            $this->deleteQuotes();
        }
    }

    /**
     * Tests that closing multiple quotes can result in mixed results.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseMultipleNegotiableQuotesMixedResults(): void
    {
        $mutation = $this->getMutation('"nq_customer_mask","nq_customer_closed_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_MIXED, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(2, $responseData['operation_results']);
        $this->assertOperationResultSuccess(
            $responseData['operation_results'][0],
            'nq_customer_mask'
        );
        $this->assertOperationResultFailure(
            $responseData['operation_results'][1],
            'nq_customer_closed_mask',
            'NegotiableQuoteInvalidStateError',
            'The quote has a status that does not allow it to be closed.'
        );
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

    /**
     * Testing for guest customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testCloseNegotiableQuoteWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getMutation('"nq_customer_mask"');
        $this->graphQlMutation($query);
    }

    /**
     * Testing for module enabled
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 0
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testCloseNegotiableQuoteNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getMutation('"nq_customer_mask"');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
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
    public function testCloseNegotiableQuoteCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getMutation('"nq_customer_mask"');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap('customernocompany@example.com'));
    }

    /**
     * Testing for feature enabled on company
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testCloseNegotiableQuoteNoCompanyFeatureEnabled(): void
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

        $query = $this->getMutation('"nq_customer_mask"');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for manage permissions
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testCloseNegotiableQuoteNoManagePermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to manage negotiable quotes.');

        $query = $this->getMutation('"nq_customer_mask"');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for quote ownership
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testCloseNegotiableQuoteUnownedQuote(): void
    {
        $mutation = $this->getMutation('"nq_admin_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_admin_mask',
            'NoSuchEntityUidError',
            'Could not find a quote with the specified UID.'
        );
    }

    /**
     * Testing that quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_empty_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseNegotiableQuoteNonNegotiable(): void
    {
        $mutation = $this->getMutation('"cart_empty_customer_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'cart_empty_customer_mask',
            'NoSuchEntityUidError',
            'Could not find a quote with the specified UID.'
        );
    }

    /**
     * Testing that quote is in a valid status
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseNegotiableQuoteBadStatus(): void
    {
        $mutation = $this->getMutation('"nq_customer_closed_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_customer_closed_mask',
            'NegotiableQuoteInvalidStateError',
            'The quote has a status that does not allow it to be closed.'
        );
    }

    /**
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/two_simple_products_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_declined_status.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testCloseNegotiableQuoteDeclinedStatus(): void
    {
        $username = 'email@companyquote.com';
        $password = 'password';
        $mutation = $this->getMutation('"nq_customer_declined_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap($username, $password));
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_customer_declined_mask',
            'NegotiableQuoteInvalidStateError',
            'The quote has a status that does not allow it to be closed.'
        );
    }

    /**
     * Testing that a quote for a different store on the same website is accessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_store.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseNegotiableQuoteForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $query = $this->getMutation('"nq_customer_mask"');
        $response = $this->graphQlMutation($query, [], '', $headers);

        $this->assertNotEmpty($response['closeNegotiableQuotes']);
        $this->assertArrayHasKey('closed_quotes', $response['closeNegotiableQuotes']);
    }

    /**
     * Testing that a quote for a different website is inaccessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_website.php
     * @magentoConfigFixture secondwebsitestore_store customer/account_share/scope 0
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testCloseNegotiableQuoteForInvalidWebsite(): void
    {
        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        // Execute mutation
        $mutation = $this->getMutation('"nq_customer_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $headers);
        $responseData = $response['closeNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_customer_mask',
            'NoSuchEntityUidError',
            'Could not find a quote with the specified UID.'
        );
    }

    /**
     * Schema mutation to close negotiable quotes.
     *
     * @param string $quoteUids
     * @return string
     */
    private function getMutation(string $quoteUids): string
    {
        return <<<MUTATION
mutation {
  closeNegotiableQuotes(
    input: {
      quote_uids: [{$quoteUids}]
    }
  ) {
    result_status,
    operation_results {
        ...on NegotiableQuoteUidOperationSuccess{
            __typename
            quote_uid
        }
        ...on CloseNegotiableQuoteOperationFailure{
            __typename
            quote_uid
            errors {
                __typename
                ...on ErrorInterface{
                    message
                }
                ...on NoSuchEntityUidError{
                    uid
                }
            }
        }
    },
    closed_quotes {
      uid,
      status,
      name,
      created_at
      history {
       change_type
        changes {
          statuses {
            changes { new_status old_status } }
      }
    }
  }
    negotiable_quotes(sort:{ sort_direction:ASC sort_field:QUOTE_NAME }) {
        total_count
        items { uid name status history { change_type } }
    }
  }
}
MUTATION;
    }

    /**
     * Assert that the response data has the expected fields.
     *
     * @param array $responseData
     */
    public function assertHasExpectedFields(array $responseData)
    {
        $this->assertNotEmpty($responseData);
        $this->assertArrayHasKey('result_status', $responseData);
        $this->assertArrayHasKey('operation_results', $responseData);
        $this->assertArrayHasKey('closed_quotes', $responseData);
        $this->assertArrayHasKey('negotiable_quotes', $responseData);
    }

    /**
     * Assert that an operation result is a success.
     *
     * @param array $operationResult
     * @param string $expectedQuoteUid
     */
    public function assertOperationResultSuccess(array $operationResult, string $expectedQuoteUid)
    {
        // Assert result type is a success for the expected quote_uid
        $this->assertEquals('NegotiableQuoteUidOperationSuccess', $operationResult['__typename']);
        $this->assertArrayHasKey('quote_uid', $operationResult);
        $this->assertEquals($expectedQuoteUid, $operationResult['quote_uid']);

        // Assert that no errors are present
        $this->assertArrayNotHasKey('errors', $operationResult);
    }

    /**
     * Assert that an operation result is a failure.
     *
     * @param array $operationResult
     * @param string $expectedUid
     * @param string $expectedErrorType
     * @param string $expectedErrorMessage
     */
    public function assertOperationResultFailure(
        array $operationResult,
        string $expectedUid,
        string $expectedErrorType,
        string $expectedErrorMessage
    ) {
        // Assert result type is a failure for the expected quote_uid
        $this->assertEquals('CloseNegotiableQuoteOperationFailure', $operationResult['__typename']);
        $this->assertArrayHasKey('quote_uid', $operationResult);
        $this->assertEquals($expectedUid, $operationResult['quote_uid']);

        // Assert exactly 1 error is present
        $this->assertArrayHasKey('errors', $operationResult);
        $this->assertCount(1, $operationResult['errors']);

        // Assert error type and message
        $error = $operationResult['errors'][0];
        $this->assertEquals($expectedErrorType, $error['__typename']);
        $this->assertArrayHasKey('message', $error);
        $this->assertEquals($expectedErrorMessage, $error['message']);
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     * @throws AuthenticationException
     */
    private function getHeaderMap(
        string $username = 'customercompany22@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }
}
