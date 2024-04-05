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
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Model\HistoryManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\GraphQl\NegotiableQuote\Fixtures\CustomerRequestNegotiableQuote;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to delete negotiable quotes.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeleteNegotiableQuotesTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiableQuoteManagement;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var HistoryManagementInterface
     */
    private $negotiableQuoteHistoryManagement;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->negotiableQuoteManagement = $objectManager->get(NegotiableQuoteManagementInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->negotiableQuoteHistoryManagement = $objectManager->get(HistoryManagementInterface::class);
    }

    /**
     *  Test delete single negotiable quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testDeleteNegotiableQuote(): void
    {
        // Execute mutation
        $mutation = $this->getMutation('"nq_customer_closed_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_SUCCESS, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultSuccess($responseData['operation_results'][0], 'nq_customer_closed_mask');

        // Assert negotiable quotes search results are empty
        $this->assertEquals(0, $responseData['negotiable_quotes']['total_count']);
        $this->assertEmpty($responseData['negotiable_quotes']['items']);
    }

    /**
     *  Test delete multiple negotiable quotes
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
    public function testDeleteMultipleNegotiableQuotes(array $negotiableQuoteData): void
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

            // Collect negotiable quote uids
            $negotiableQuoteUids = [];
            foreach ($fixtureQuotes as $fixtureQuote) {
                $negotiableQuoteUids[] = $fixtureQuote['uid'];
            }

            // Update the last quote to CLOSED status
            $this->closeNegotiableQuoteQuery($fixtureQuotes[2]['uid']);

            // Update the first quote to SUBMITTED_BY_ADMIN status
            $customer = $this->customerRepository->get('customercompany22@example.com');
            $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
            $firstNegotiableQuoteId = array_key_first($negotiableQuotes);
            /** @var NegotiableQuoteInterface $firstNegotiableQuote */
            $firstNegotiableQuote = $this->negotiableQuoteRepository->getById($firstNegotiableQuoteId);
            $firstQuote = $this->cartRepository->get($firstNegotiableQuote->getQuoteId());
            $this->negotiableQuoteManagement->recalculateQuote($firstQuote->getId(), true);
            $firstNegotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
            $this->negotiableQuoteRepository->save($firstNegotiableQuote);
            $this->negotiableQuoteHistoryManagement->updateLog($firstQuote->getId());

            // Execute mutation - attempt to delete the first and last negotiable quotes out of the three created
            $uidsToDelete = '"' . $negotiableQuoteUids[0] . '","' . $negotiableQuoteUids[2] . '"';
            $mutation = $this->getMutation($uidsToDelete);
            $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
            $responseData = $response['deleteNegotiableQuotes'] ?? [];

            // Assert has expected fields
            $this->assertHasExpectedFields($responseData);

            // Assert result_status
            $this->assertEquals(BatchResult::STATUS_SUCCESS, $responseData['result_status']);

            // Assert operation_results
            $this->assertCount(2, $responseData['operation_results']);
            $this->assertOperationResultSuccess($responseData['operation_results'][0], $negotiableQuoteUids[0]);
            $this->assertOperationResultSuccess($responseData['operation_results'][1], $negotiableQuoteUids[2]);

            // Assert negotiable quotes search results has only 1 remaining quote
            $this->assertEquals(1, $responseData['negotiable_quotes']['total_count']);
            $pageInfo = $responseData['negotiable_quotes']['page_info'];
            $this->assertEquals(1, $pageInfo['total_pages']);
            $this->assertNotEmpty($responseData['negotiable_quotes']['items']);
            $responseNegotiableQuoteItems = $responseData['negotiable_quotes']['items'];
            $this->assertEquals('Test Quote Name 2', $responseNegotiableQuoteItems[0]['name']);

            // Assert that only the expected quotes were deleted
            $leftOverNegotiableQuoteId = $responseNegotiableQuoteItems[0]['uid'];
            $deletedNegotiableQuoteIds = [$negotiableQuoteUids[0], $negotiableQuoteUids[2]];
            $this->assertTrue(
                in_array(
                    $leftOverNegotiableQuoteId,
                    array_diff($negotiableQuoteUids, $deletedNegotiableQuoteIds)
                )
            );
        } finally {
            //clean up the created quotes
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
    public function testDeleteMultipleNegotiableQuotesMixedResults(): void
    {
        $mutation = $this->getMutation('"nq_customer_mask","nq_customer_closed_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_MIXED, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(2, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_customer_mask',
            'NegotiableQuoteInvalidStateError',
            'The quote has a status that does not allow it to be deleted.'
        );
        $this->assertOperationResultSuccess(
            $responseData['operation_results'][1],
            'nq_customer_closed_mask'
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
                    ],
                    [
                        'name' => 'Test Quote Name 3',
                        'comment' => 'Test Quote comment 3',
                        'productSku' => 'simple',
                        'productQuantity' => 3
                    ],
                ]
            ]
        ];
    }

    /**
     * Schema mutation to close negotiable quotes.
     *
     * @param string $quoteUids
     */
    private function closeNegotiableQuoteQuery(string $quoteUids)
    {
        $closeNegotiableQuoteQuery = <<<MUTATION
mutation {
  closeNegotiableQuotes(
    input: {
      quote_uids: ["{$quoteUids}"]
    }
  ) {
    closed_quotes {
      uid
      status
      name
      history {
       change_type
        changes {
          statuses {
            changes { new_status old_status } }
      }
    }
  }
    negotiable_quotes {
        total_count
        items { uid name status history { change_type } }
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $closeNegotiableQuoteQuery,
            [],
            '',
            $this->getHeaderMap()
        );
        $this->assertArrayHasKey('closed_quotes', $response['closeNegotiableQuotes']);
    }

    /**
     * Testing for guest customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testDeleteNegotiableQuoteWithNoCustomerToken(): void
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
    public function testDeleteNegotiableQuoteNoModuleEnabled(): void
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
    public function testDeleteNegotiableQuoteCustomerNoCompany(): void
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
    public function testDeleteNegotiableQuoteNoCompanyFeatureEnabled(): void
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
    public function testDeleteNegotiableQuoteNoManagePermissions(): void
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
    public function testDeleteNegotiableQuoteUnownedQuote(): void
    {
        $mutation = $this->getMutation('"nq_admin_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

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
    public function testDeleteNegotiableQuoteNonNegotiable(): void
    {
        $mutation = $this->getMutation('"cart_empty_customer_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

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
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testDeleteNegotiableQuoteBadStatus(): void
    {
        $mutation = $this->getMutation('"nq_customer_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $this->getHeaderMap());
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

        // Assert has expected fields
        $this->assertHasExpectedFields($responseData);

        // Assert result_status
        $this->assertEquals(BatchResult::STATUS_FAILURE, $responseData['result_status']);

        // Assert operation_results
        $this->assertCount(1, $responseData['operation_results']);
        $this->assertOperationResultFailure(
            $responseData['operation_results'][0],
            'nq_customer_mask',
            'NegotiableQuoteInvalidStateError',
            'The quote has a status that does not allow it to be deleted.'
        );
    }

    /**
     * Testing that a quote for a different store on the same website is accessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_store.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testDeleteNegotiableQuoteForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $query = $this->getMutation('"nq_customer_closed_mask"');
        $response = $this->graphQlMutation($query, [], '', $headers);

        $this->assertNotEmpty($response['deleteNegotiableQuotes']);
        $this->assertArrayHasKey('items', $response['deleteNegotiableQuotes']['negotiable_quotes']);
        $this->assertArrayHasKey('page_info', $response['deleteNegotiableQuotes']['negotiable_quotes']);
        $this->assertArrayHasKey('total_count', $response['deleteNegotiableQuotes']['negotiable_quotes']);
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
    public function testDeleteNegotiableQuoteForInvalidWebsite(): void
    {
        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        // Execute mutation
        $mutation = $this->getMutation('"nq_customer_mask"');
        $response = $this->graphQlMutation($mutation, [], '', $headers);
        $responseData = $response['deleteNegotiableQuotes'] ?? [];

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
     * Generates GraphQl mutation to delete negotiable quotes
     *
     * @param string $quoteUids
     * @return string
     */
    private function getMutation(string $quoteUids): string
    {
        return <<<MUTATION
mutation {
  deleteNegotiableQuotes(
    input: {
       quote_uids: [{$quoteUids}]
    }
  ) {
    result_status
    operation_results {
      ...on NegotiableQuoteUidOperationSuccess{
        __typename
        quote_uid
      }
      ...on DeleteNegotiableQuoteOperationFailure{
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
    }
    negotiable_quotes (sort:{ sort_direction:ASC sort_field:QUOTE_NAME }) {
      total_count
      page_info {
        page_size
        current_page
        total_pages
      }
      items {
        uid
        name
        status
        comments {
          text
          creator_type
          author {
            firstname
          }
        }
        history {
          change_type
          changes {
            comment_added {
              comment
            }
            statuses {
              changes {
                new_status
                old_status
              }
            }
          }
        }
      }
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
        $this->assertArrayHasKey('negotiable_quotes', $responseData);
        $this->assertArrayHasKey('items', $responseData['negotiable_quotes']);
        $this->assertArrayHasKey('page_info', $responseData['negotiable_quotes']);
        $this->assertArrayHasKey('total_count', $responseData['negotiable_quotes']);
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
        $this->assertEquals('DeleteNegotiableQuoteOperationFailure', $operationResult['__typename']);
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
