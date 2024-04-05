<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Model\Comment;
use Magento\NegotiableQuote\Model\CommentManagement;
use Magento\NegotiableQuote\Model\CommentManagementInterface;
use Magento\NegotiableQuote\Model\CommentRepositoryInterface;
use Magento\NegotiableQuote\Model\Quote\History;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\Status;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Model\User;

/**
 * Test coverage to send a negotiable quote for review
 */
class SendNegotiableQuoteForReviewTest extends GraphQlAbstract
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
     * @var Status
     */
    private $status;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var CommentManagement
     */
    private $commentManagement;
    /**
     * @var CommentRepositoryInterface
     */
    private $commentRepository;

    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiabeQuoteManagement;
    /**
     * @var User
     */
    private $user;
    /**
     * @var History
     */
    private $quoteHistory;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->status = $objectManager->get(Status::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->commentManagement = $objectManager->get(CommentManagementInterface::class);
        $this->commentRepository = $objectManager->get(CommentRepositoryInterface::class);
        $this->negotiabeQuoteManagement = $objectManager->get(NegotiableQuoteManagementInterface::class);
        $this->user = $objectManager->get(User::class);
        $this->quoteHistory = $objectManager->get(History::class);
    }

    /**
     * Test that sending a negotiable quote updates status and history and adds a comment
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSendNegotiableQuoteWithComment(): void
    {
        $response = $this->graphQlMutation($this->getQuery('nq_customer_mask'), [], '', $this->getHeaderMap());

        self::assertNotEmpty($response['sendNegotiableQuoteForReview']);
        self::assertArrayHasKey('quote', $response['sendNegotiableQuoteForReview']);
        $this->assertEquals('nq_customer_mask', $response['sendNegotiableQuoteForReview']['quote']['uid']);

        $responseComment = $response['sendNegotiableQuoteForReview']['quote']['comments'][0];
        $this->assertEquals('Quote Comment', $responseComment['text']);
        $this->assertEquals('BUYER', $responseComment['creator_type']);
        $this->assertEquals('Customer', $responseComment['author']['firstname']);

        $responseStatus = $response['sendNegotiableQuoteForReview']['quote']['status'];
        $expectedStatus = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedStatus, $responseStatus);

        $responseHistory = array_last($response['sendNegotiableQuoteForReview']['quote']['history']);
        $this->assertEquals('UPDATED', $responseHistory['change_type']);
        $this->assertEquals('Customer', $responseHistory['author']['firstname']);
        $this->assertEquals('Quote Comment', $responseHistory['changes']['comment_added']['comment']);

        $historyStatus = array_last($responseHistory['changes']['statuses']['changes']);
        $expectedNew = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedNew, $historyStatus['new_status']);
    }

    /**
     * Test that sending a negotiable quote updates status and history and works without adding a comment
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSendNegotiableQuoteWithoutComment(): void
    {
        $response = $this->graphQlMutation($this->getQuery('nq_customer_mask', ''), [], '', $this->getHeaderMap());

        self::assertNotEmpty($response['sendNegotiableQuoteForReview']);
        self::assertArrayHasKey('quote', $response['sendNegotiableQuoteForReview']);
        $this->assertEquals('nq_customer_mask', $response['sendNegotiableQuoteForReview']['quote']['uid']);
        $this->assertEmpty($response['sendNegotiableQuoteForReview']['quote']['comments']);

        $responseStatus = $response['sendNegotiableQuoteForReview']['quote']['status'];
        $expectedStatus = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedStatus, $responseStatus);

        $responseHistory = array_last($response['sendNegotiableQuoteForReview']['quote']['history']);
        $this->assertEquals('UPDATED', $responseHistory['change_type']);
        $this->assertEquals('Customer', $responseHistory['author']['firstname']);
        $this->assertEmpty($responseHistory['changes']['comment_added']);

        $historyStatus = array_last($responseHistory['changes']['statuses']['changes']);
        $expectedNew = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedNew, $historyStatus['new_status']);
    }

    /**
     * Test that a negotiable quote cannot be re-sent after it's already been submitted by the customer
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSendNegotiableQuoteCannotResend(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs have a status that does not allow them to be edited or submitted: '
            . 'nq_customer_mask'
        );

        $query = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        self::assertNotEmpty($response['sendNegotiableQuoteForReview']);
        self::assertArrayHasKey('quote', $response['sendNegotiableQuoteForReview']);
        $this->assertEquals('nq_customer_mask', $response['sendNegotiableQuoteForReview']['quote']['uid']);

        $responseStatus = $response['sendNegotiableQuoteForReview']['quote']['status'];
        $expectedStatus = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedStatus, $responseStatus);

        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for invalid customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider dataProviderInvalidInfo
     * @param string $customerEmail
     * @param string $customerPassword
     * @param string $message
     * @throws AuthenticationException
     */
    public function testSendNegotiableQuoteWithInvalidCustomerToken(
        string $customerEmail,
        string $customerPassword,
        string $message
    ): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap($customerEmail, $customerPassword));
    }

    /**
     * @return array
     */
    public function dataProviderInvalidInfo(): array
    {
        return [
            'invalid_customer_email' => [
                'customer$example.com',
                'magento777',
                'The account sign-in was incorrect or your account is disabled temporarily. ' .
                'Please wait and try again later.'
            ],
            'invalid_customer_password' => [
                'customercompany22@example.com',
                '__--++#$@',
                'The account sign-in was incorrect or your account is disabled temporarily. ' .
                'Please wait and try again later.'
            ],
            'no_such_email' => [
                'customerNoSuch@example.com',
                'password',
                'The account sign-in was incorrect or your account is disabled temporarily. ' .
                'Please wait and try again later.'
            ]
        ];
    }

    /**
     * Testing for guest customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSendNegotiableQuoteWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask');
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
    public function testSendNegotiableQuoteNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getQuery('nq_customer_mask');
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
    public function testSendNegotiableQuoteCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getQuery('nq_customer_mask');
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
    public function testSendNegotiableQuoteNoCompanyFeatureEnabled(): void
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

        $query = $this->getQuery('nq_customer_mask');
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
    public function testSendNegotiableQuoteNoManagePermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to manage negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask');
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
    public function testSendNegotiableQuoteUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_admin_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
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
    public function testSendNegotiableQuoteNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs are not negotiable: cart_empty_customer_mask'
        );

        $query = $this->getQuery('cart_empty_customer_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
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
    public function testSendNegotiableQuoteBadStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs have a status that does not allow them to be edited or submitted: '
            . 'nq_customer_closed_mask'
        );

        $query = $this->getQuery('nq_customer_closed_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSendNegotiableQuoteForInvalidNegotiableQuoteId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find quotes with the following UIDs: 9999999');

        $query = $this->getQuery('9999999');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
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
    public function testSendNegotiableQuoteForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlMutation($negotiableQuoteQuery, [], '', $headers);

        self::assertNotEmpty($response['sendNegotiableQuoteForReview']);
        self::assertArrayHasKey('quote', $response['sendNegotiableQuoteForReview']);
        $this->assertEquals('nq_customer_mask', $response['sendNegotiableQuoteForReview']['quote']['uid']);

        $responseStatus = $response['sendNegotiableQuoteForReview']['quote']['status'];
        $expectedStatus = $this->status->getStatusLabels()[NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER];
        $this->assertEquals($expectedStatus, $responseStatus);
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
    public function testSendNegotiableQuoteForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $this->graphQlMutation($negotiableQuoteQuery, [], '', $headers);
    }

    /**
     * Test for sending negotiable quote after removing product from quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_multiple_items_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSendNegotiableQuoteAfterProductRemovedFromQuote()
    {
        $customerEmail = 'customercompany22@example.com';
        $negotiableQuoteUid = 'nq_customer_mask';
        $negotiableQuoteQuery = $this->getNegotiableQuoteQuery($negotiableQuoteUid);
        $response = $this->graphQlQuery(
            $negotiableQuoteQuery,
            [],
            '',
            $this->getHeaderMap($customerEmail, 'password')
        );
        $negotiableQuoteItems = $response['negotiableQuote']['items'];
        $itemIdToRemove = null;
        foreach($negotiableQuoteItems as $negotiableQuoteItem) {
            if($negotiableQuoteItem['product']['name']=== 'Simple Product') {
                $itemIdToRemove = $negotiableQuoteItem['uid'];
                break;
            }
        }

        $removeItemFromNegQuote = <<<MUTATION
mutation {
  removeNegotiableQuoteItems(
    input: {
      quote_uid: "{$negotiableQuoteUid}"
      quote_item_uids: ["{$itemIdToRemove}"]
    }
  ) {
    quote {
      uid
      name
      status
      created_at
      updated_at
      items {
        uid
        product {sku name}
      }
    }
  }
}
MUTATION;
        $response = $this->graphQlMutation($removeItemFromNegQuote, [], '', $this->getHeaderMap());
        $this->assertCount(1, $response['removeNegotiableQuoteItems']['quote']['items']);
        $remainingItem = $response['removeNegotiableQuoteItems']['quote']['items'][0];
        $this->assertNotEquals($itemIdToRemove, $remainingItem['uid']);
        $this->assertEquals('Simple Product2', $remainingItem['product']['name']);
        //Send this negotiable quote back for review after removing an item
        $sendForReviewResponse = $this->graphQlMutation($this->getQuery('nq_customer_mask', 'Comment after removing an item'),
            [],
            '',
            $this->getHeaderMap()
        );
        $this->assertNotEmpty($sendForReviewResponse['sendNegotiableQuoteForReview']);

        //Query for negotiable quote for history logs with removed products
        $negotiableQuoteQuery = $this->getNegotiableQuoteQuery('nq_customer_mask');
        $negotiableQuoteResponse = $this->graphQlQuery(
            $negotiableQuoteQuery,
            [],
            '',
            $this->getHeaderMap($customerEmail, 'password')
        );
        $responseHistoryChanges = $negotiableQuoteResponse['negotiableQuote']['history'];
        $this->assertCount(2, $responseHistoryChanges);

        $expectedHistoryChanges =
            [
                [
                    'change_type'=> 'CREATED',
                    'author'=> ['firstname'=> 'Customer'],
                    'changes'=> [
                        'products_removed' => [
                            'products_removed_from_quote' => [],
                            'products_removed_from_catalog' => []
                        ],
                        'comment_added' => null
                    ]
                ],
                [
                    'change_type'=> 'UPDATED',
                    'author' => ['firstname'=> 'Customer'],
                    'changes'=> [
                        'products_removed' => [
                            'products_removed_from_quote' => [
                                ['sku' => 'simple',
                                 'name' => 'Simple Product'
                                ]
                            ],
                            'products_removed_from_catalog' => []
                        ],
                        'comment_added' => ['comment' => 'Comment after removing an item']]
                ]
            ];
        $this->assertResponseFields($responseHistoryChanges, $expectedHistoryChanges);
    }

    /**
     * Generates GraphQl mutation to send a negotiable quote for review
     *
     * @param string $quoteId
     * @param string $comment
     * @return string
     */
    private function getQuery(string $quoteId, string $comment = 'Quote Comment'): string
    {
        $commentParameter = <<<COMMENT_MUTATION
      comment: {
        comment: "{$comment}"
      }
COMMENT_MUTATION;

        $comment = $comment ? $commentParameter : '';
        return <<<MUTATION
mutation {
  sendNegotiableQuoteForReview(
    input: {
      quote_uid: "{$quoteId}"
      {$comment}
    }
  ) {
    quote {
      uid
      status
      comments {
        author {
          firstname
        }
        creator_type
        text
      }
      history {
        author {
          firstname
        }
        change_type
        changes {
          statuses {
            changes {
              old_status
              new_status
            }
          }
          comment_added {
            comment
          }
        }
      }
    }
  }
}
MUTATION;
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
     * Returns GraphQl Query string to get negotiable quote with comments from seller and buyer
     *
     * @param string $negotiableQuoteId
     * @return string
     */
    private function getNegotiableQuoteQuery(string $negotiableQuoteId): string
    {
        return <<<QUERY
{
negotiableQuote(uid: "{$negotiableQuoteId}") {
uid
name
status
items {
    uid
    quantity
    product { sku name}
  }
comments {
      creator_type
      text
      author {firstname lastname}
      }
  history {
           change_type
           author {firstname}
           changes {
            products_removed {
              products_removed_from_quote { sku name }
              products_removed_from_catalog
            }
             comment_added{comment}
           }
        }
  }
}
QUERY;
    }
}
