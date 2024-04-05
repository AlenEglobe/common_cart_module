<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use DateTime;
use Exception;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\Quote;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\Status;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\NegotiableQuote\Model\CommentRepositoryInterface;
use Magento\NegotiableQuote\Model\CommentManagementInterface;
use Magento\NegotiableQuote\Model\CommentManagement;
use Magento\NegotiableQuote\Model\Quote\History;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Model\User;

/**
 * Test coverage for getting Negotiable Quote data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetNegotiableQuoteTest extends GraphQlAbstract
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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var Status
     */
    private $status;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var User
     */
    private $user;

    /**
     * @var CommentManagement
     */
    private $commentManagement;

    /**
     * @var History
     */
    private $quoteHistory;

    /**
     * @var CommentRepositoryInterface
     */
    private $commentRepository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->status = $objectManager->get(Status::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->user = $objectManager->get(User::class);
        $this->commentManagement = $objectManager->get(CommentManagementInterface::class);
        $this->commentRepository = $objectManager->get(CommentRepositoryInterface::class);
        $this->quoteHistory = $objectManager->get(History::class);
    }

    /**
     * Test for getting quote details for a customer by quoteId
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteById(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $quotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $negotiableQuoteId = array_key_last($quotes);

        $negotiableQuote = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuote']);
        $this->assertArrayHasKey('buyer', $response['negotiableQuote']);
        $this->assertNotEmpty($response['negotiableQuote']['uid']);
        $this->assertNotEmpty($response['negotiableQuote']['items']);
        $this->assertNotEmpty($response['negotiableQuote']['buyer']);
        $this->assertArrayHasKey('status', $response['negotiableQuote']);
        $this->assertArrayHasKey('name', $response['negotiableQuote']);
        $this->assertEquals('nq_customer_mask', $response['negotiableQuote']['uid']);
        $this->assertEquals($negotiableQuote->getQuoteName(), $response['negotiableQuote']['name']);
        $this->assertEquals(
            $negotiableQuote->getStatus(),
            array_keys($this->status->getStatusLabels(), $response['negotiableQuote']['status'])[0]
        );
        $this->assertEquals($customer->getFirstname(), $response['negotiableQuote']['buyer']['firstname']);
        $this->assertEquals($customer->getLastname(), $response['negotiableQuote']['buyer']['lastname']);
        $this->assertEquals('customercompany22@example.com', $response['negotiableQuote']['email']);
        $this->assertNotEmpty($response['negotiableQuote']['created_at']);
        $this->assertNotEmpty($response['negotiableQuote']['updated_at']);
        $this->assertEquals(1, $response['negotiableQuote']['total_quantity']);
        $this->assertFalse($response['negotiableQuote']['is_virtual']);

        // verify initial null expiration values in history
        $this->assertNull($response['negotiableQuote']['history'][0]['changes']['expiration']['old_expiration']);
        $this->assertNull($response['negotiableQuote']['history'][0]['changes']['expiration']['new_expiration']);
    }

    /**
     * Test for getting quote details for a quote with a virtual products
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_virtual.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_virtual_product.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteByIdVirtualOutput(): void
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $negotiableQuoteId = array_key_last($quotes);

        $negotiableQuote = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $quote = $this->cartRepository->get($negotiableQuote->getQuoteId());
        $negotiableQuoteQuery = $this->getQuery('nq_admin_mask');
        $response = $this->graphQlQuery(
            $negotiableQuoteQuery,
            [],
            '',
            $this->getCustomerAuthHeaders('email@companyquote.com')
        );

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuote']);
        $this->assertArrayHasKey('buyer', $response['negotiableQuote']);
        $this->assertNotEmpty($response['negotiableQuote']['uid']);
        $this->assertNotEmpty($response['negotiableQuote']['items']);
        $this->assertNotEmpty($response['negotiableQuote']['buyer']);
        $this->assertArrayHasKey('status', $response['negotiableQuote']);
        $this->assertArrayHasKey('name', $response['negotiableQuote']);
        $this->assertEquals('nq_admin_mask', $response['negotiableQuote']['uid']);
        $this->assertEquals($negotiableQuote->getQuoteName(), $response['negotiableQuote']['name']);
        $this->assertEquals(
            $negotiableQuote->getStatus(),
            array_keys($this->status->getStatusLabels(), $response['negotiableQuote']['status'])[0]
        );
        $this->assertEquals($customer->getFirstname(), $response['negotiableQuote']['buyer']['firstname']);
        $this->assertEquals($customer->getLastname(), $response['negotiableQuote']['buyer']['lastname']);
        $this->assertEquals('email@companyquote.com', $response['negotiableQuote']['email']);
        $this->assertEquals($quote->getCreatedAt(), $response['negotiableQuote']['created_at']);
        $this->assertEquals($quote->getUpdatedAt(), $response['negotiableQuote']['updated_at']);
        $this->assertEquals(1, $response['negotiableQuote']['total_quantity']);
        $this->assertTrue($response['negotiableQuote']['is_virtual']);
    }

    /**
     * Test for getting expiration values in quote history.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_expiration_history.php
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteExpirationHistory(): void
    {
        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertArrayHasKey('history', $response['negotiableQuote']);
        $this->assertNotEmpty($response['negotiableQuote']['history']);
        // verify expiration change to tomorrow
        $tomorrow = (new DateTime())->modify('+1 day');
        $this->assertNull($response['negotiableQuote']['history'][0]['changes']['expiration']['old_expiration']);
        $this->assertEquals(
            $tomorrow->format('Y-m-d'),
            $response['negotiableQuote']['history'][0]['changes']['expiration']['new_expiration']
        );
        // verify expiration change to Never
        $this->assertEquals(
            $tomorrow->format('Y-m-d'),
            $response['negotiableQuote']['history'][1]['changes']['expiration']['old_expiration']
        );
        $this->assertEquals(
            'Never',
            $response['negotiableQuote']['history'][1]['changes']['expiration']['new_expiration']
        );
    }

    /**
     * Test for getting quote details for a customer by quoteId where changes have been saved as draft by admin.
     * Customer should only see the snapshot data.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_saved_as_draft.php
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteSnapshotById(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $negotiableQuoteId = array_key_last($negotiableQuotes);
        $negotiableQuote = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $quote = $this->cartRepository->get($negotiableQuote->getQuoteId());
        /** @var Quote $quoteHelper */
        $quoteHelper = Bootstrap::getObjectManager()->get(Quote::class);
        $snapshotQuote = $quoteHelper->getSnapshotQuote($quote);
        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertArrayHasKey('items', $response['negotiableQuote']);
        $this->assertArrayHasKey('buyer', $response['negotiableQuote']);
        $this->assertNotEmpty($response['negotiableQuote']['uid']);
        $this->assertNotEmpty($response['negotiableQuote']['items']);
        $this->assertNotEmpty($response['negotiableQuote']['buyer']);
        $this->assertArrayHasKey('status', $response['negotiableQuote']);
        $this->assertArrayHasKey('name', $response['negotiableQuote']);
        $this->assertEquals('nq_customer_mask', $response['negotiableQuote']['uid']);
        $this->assertEquals($negotiableQuote->getQuoteName(), $response['negotiableQuote']['name']);
        $this->assertContains(
            $negotiableQuote->getStatus(),
            array_keys($this->status->getStatusLabels(), $response['negotiableQuote']['status'])
        );
        $this->assertEquals($customer->getFirstname(), $response['negotiableQuote']['buyer']['firstname']);
        $this->assertEquals($customer->getLastname(), $response['negotiableQuote']['buyer']['lastname']);
        $this->assertEquals($snapshotQuote->getCreatedAt(), $response['negotiableQuote']['created_at']);
        $this->assertEquals($snapshotQuote->getUpdatedAt(), $response['negotiableQuote']['updated_at']);
        // verify number of items and their quantities
        $snapshotItems = $snapshotQuote->getAllVisibleItems();
        $responseItems = $response['negotiableQuote']['items'];
        $this->assertEquals(count($snapshotItems), count($responseItems));
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
            $response['negotiableQuote']['prices']['grand_total']['value']
        );
        $this->assertNotEquals($negotiableQuote->getNegotiatedPriceValue(), $snapshotQuote->getGrandTotal());

        // adminSend the negotiable quote and verify the updated values are returned
        /** @var NegotiableQuoteManagementInterface $negotiableQuoteManagement */
        $negotiableQuoteManagement = Bootstrap::getObjectManager()->get(NegotiableQuoteManagementInterface::class);
        $negotiableQuoteManagement->adminSend($quote->getId());
        $updatedNegotiableQuote = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $updatedResponse = $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
        $this->assertArrayHasKey('negotiableQuote', $updatedResponse);
        $this->assertArrayHasKey('items', $updatedResponse['negotiableQuote']);
        $this->assertArrayHasKey('buyer', $updatedResponse['negotiableQuote']);
        $this->assertNotEmpty($updatedResponse['negotiableQuote']['uid']);
        $this->assertNotEmpty($updatedResponse['negotiableQuote']['items']);
        $this->assertNotEmpty($updatedResponse['negotiableQuote']['buyer']);
        $this->assertArrayHasKey('status', $updatedResponse['negotiableQuote']);
        $this->assertArrayHasKey('name', $updatedResponse['negotiableQuote']);
        $this->assertEquals('nq_customer_mask', $updatedResponse['negotiableQuote']['uid']);
        $this->assertEquals($updatedNegotiableQuote->getQuoteName(), $updatedResponse['negotiableQuote']['name']);
        $this->assertContains(
            $updatedNegotiableQuote->getStatus(),
            array_keys($this->status->getStatusLabels(), $updatedResponse['negotiableQuote']['status'])
        );
        $this->assertEquals($customer->getFirstname(), $updatedResponse['negotiableQuote']['buyer']['firstname']);
        $this->assertEquals($customer->getLastname(), $updatedResponse['negotiableQuote']['buyer']['lastname']);
        $this->assertEquals($quote->getCreatedAt(), $updatedResponse['negotiableQuote']['created_at']);
        $this->assertEquals($quote->getUpdatedAt(), $updatedResponse['negotiableQuote']['updated_at']);
        // verify number of items and their quantities
        $numUpdatedItems = count($quote->getItems());
        $numUpdatedResponseItems = count($updatedResponse['negotiableQuote']['items']);
        $this->assertEquals($numUpdatedItems, $numUpdatedResponseItems);
        for ($index = 0; $index < $numUpdatedItems; $index++) {
            $this->assertEquals(
                $quote->getItems()[$index]->getItemId(),
                $updatedResponse['negotiableQuote']['items'][$index]['id']
            );
            $this->assertEquals(
                $quote->getItems()[$index]->getQty(),
                $updatedResponse['negotiableQuote']['items'][$index]['quantity']
            );
        }
        // verify prices
        $this->assertEquals(
            $quote->getGrandTotal(),
            $updatedResponse['negotiableQuote']['prices']['grand_total']['value']
        );
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
    public function testGetNegotiableQuoteWithInvalidCustomerToken(
        string $customerEmail,
        string $customerPassword,
        string $message
    ): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders($customerEmail, $customerPassword));
    }

    /**
     * @return array
     */
    public function dataProviderInvalidInfo(): array
    {
        return [
            'invalid_customer_email' => [
                'customer$example.com',
                'Magento777',
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
    public function testGetNegotiableQuoteWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($negotiableQuoteQuery);
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
    public function testGetNegotiableQuoteNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
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
    public function testGetNegotiableQuoteCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders('customernocompany@example.com'));
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
    public function testGetNegotiableQuoteNoCompanyFeatureEnabled(): void
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

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
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
    public function testGetNegotiableQuoteNoViewPermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to view negotiable quotes.');

        $negotiableQuoteQuery = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing for quote ownership
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testGetNegotiableQuoteUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $negotiableQuoteQuery = $this->getQuery('nq_admin_mask');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing that quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_empty_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_empty_customer_mask');

        $negotiableQuoteQuery = $this->getQuery('cart_empty_customer_mask');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
    }

    /**
     * Testing for invalid quoteId
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetInvalidNegotiableQuoteId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find quotes with the following UIDs: 9999');

        $negotiableQuoteQuery = $this->getQuery('9999');
        $this->graphQlQuery($negotiableQuoteQuery, [], '', $this->getCustomerAuthHeaders());
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
    public function testGetNegotiableQuoteForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getCustomerAuthHeaders();
        $headers['Store'] = 'secondstore';

        $query = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($query, [], '', $headers);

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertEquals('nq_customer_mask', $response['negotiableQuote']['uid']);
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
    public function testGetNegotiableQuoteForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getCustomerAuthHeaders();
        $headers['Store'] = 'secondwebsitestore';

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($query, [], '', $headers);
    }

    /**
     * Testing that a company admin can view quotes created by subordinates
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteForSubordinateByAdmin(): void
    {
        $query = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("email@companyquote.com"));

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertEquals('nq_customer_mask', $response['negotiableQuote']['uid']);
        $this->assertEquals('Customer', $response['negotiableQuote']['buyer']['firstname']);
    }

    /**
     * Testing that a manager can view quotes created by subordinates if they have view_quotes_sub permission
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteForSubordinateByManager(): void
    {
        $query = $this->getQuery('nq_customer_mask');
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("companymanager@example.com"));

        $this->assertArrayHasKey('negotiableQuote', $response);
        $this->assertEquals('nq_customer_mask', $response['negotiableQuote']['uid']);
        $this->assertEquals('Customer', $response['negotiableQuote']['buyer']['firstname']);
    }

    /**
     * Testing that a manager cannot view quotes created by subordinates if they do not have view_quotes_sub permission
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_no_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteForSubordinateByManagerNoPermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("companymanager@example.com"));
    }

    /**
     * Testing that a manager cannot view quotes created by admin even if they have view_quotes_sub permission
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGetNegotiableQuoteForAdminByManager(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_admin_mask');
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("companymanager@example.com"));
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
    public function testGetNegotiableQuoteForSecondCompanyByAdmin(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_customer_mask');
        $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("email@secondcompany.com"));
    }

    /**
     * Authentication header mapping
     *
     * @param string $email
     * @param string $password
     * @return array
     * @throws AuthenticationException
     */
    private function getCustomerAuthHeaders(
        string $email = 'customercompany22@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($email, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }

    /**
     * Returns GraphQl Query string to get a negotiable quote
     *
     * @param string $negotiableQuoteId
     * @return string
     */
    private function getQuery(string $negotiableQuoteId): string
    {
        return <<<QUERY
{
  negotiableQuote(uid: "{$negotiableQuoteId}") {
    uid
    name
    status
    created_at
    updated_at
    items {
      id
      quantity
    }
    history {
      changes {
        expiration {
          old_expiration
          new_expiration
        }
      }
    }
    buyer {
        firstname
        lastname
    }
    email
    total_quantity
    is_virtual
    prices {
        grand_total {value}
    }
  }
}
QUERY;
    }
}
