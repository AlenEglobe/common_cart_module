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
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to update negotiable quote quantity
 */
class UpdateNegotiableQuoteQuantityTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

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

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->quoteRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
    }

    /**
     * Test UpdateNegotiableQuoteQuantities
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testUpdateNegotiableQuoteQuantitiesPositiveCase(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $quotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());

        $quoteId = array_key_last($quotes);
        $quote = $this->quoteRepository->get($quoteId);
        $quoteItemId = $quote->getItems()[0]->getItemId();

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)$quoteItemId), 5.00);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        $reflection = new \ReflectionClass($this->quoteRepository);
        $property = $reflection->getProperty('quotesById');
        $property->setAccessible(true);
        $property->setValue($this->quoteRepository, []);

        $quote = $this->quoteRepository->get($quoteId);
        $quoteItems = $quote->getItems();

        $this->assertNotEmpty($response['updateNegotiableQuoteQuantities']);
        $this->assertArrayHasKey('quote', $response['updateNegotiableQuoteQuantities']);
        $this->assertEquals('nq_customer_mask', $response['updateNegotiableQuoteQuantities']['quote']['uid']);
        $this->assertEquals(
            (string)$quoteItems[0]->getItemId(),
            $response['updateNegotiableQuoteQuantities']['quote']['items'][0]['id']
        );
        $this->assertEquals(
            $quoteItems[0]->getQty(),
            $response['updateNegotiableQuoteQuantities']['quote']['items'][0]['quantity']
        );
    }

    /**
     * Test UpdateNegotiableQuoteQuantities as a guest user
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testGuestUserUpdateNegotiableQuoteQuantities(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask', base64_encode('1'), 5.00);
        $this->graphQlMutation($query);
    }

    /**
     * Test UpdateNegotiableQuoteQuantities with invalid quote UID
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider invalidQuoteIdDataProvider
     *
     * @param string $quoteId
     * @param string $errorMessage
     *
     * @throws Exception
     */
    public function testInvalidQuoteIdUpdateNegotiableQuoteQuantities(string $quoteId, string $errorMessage): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($errorMessage);

        $query = $this->getQuery($quoteId, base64_encode('1'), 5.00);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Data provider for invalid quote UID
     *
     * @return array
     */
    public function invalidQuoteIdDataProvider(): array
    {
        return [
            'missing_quote_uid' => [
                '',
                'GraphQL response contains errors: Required parameter "quote_uid" is missing.'
            ],
            'invalid_quote_uid' => [
                '-1',
                'Could not find quotes with the following UIDs: -1'
            ]
        ];
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
    public function testUpdateNegotiableQuoteQuantitiesNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getQuery('nq_customer_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getQuery('nq_customer_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesNoCompanyFeatureEnabled(): void
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

        $query = $this->getQuery('nq_customer_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesNoManagePermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to manage negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_admin_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_empty_customer_mask');

        $query = $this->getQuery('cart_empty_customer_mask', base64_encode('1'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesBadStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs have a status that does not allow them to be edited or submitted: '
            . 'nq_customer_closed_mask'
        );

        $query = $this->getQuery('nq_customer_closed_mask', base64_encode('1'), 5.00);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Test item ids belong to the quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testUpdateNegotiableQuoteQuantitiesInvalidItem(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The following item IDs were not found on the specified quote: ' . base64_encode('9999')
        );

        $query = $this->getQuery('nq_customer_mask', base64_encode('9999'), 5.00);
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
    public function testUpdateNegotiableQuoteQuantitiesForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);
        $quoteItemId = $quote->getItems()[0]->getItemId();

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)$quoteItemId), 5.00);
        $response = $this->graphQlMutation($query, [], '', $headers);

        $this->assertNotEmpty($response['updateNegotiableQuoteQuantities']);
        $this->assertArrayHasKey('quote', $response['updateNegotiableQuoteQuantities']);
        $this->assertEquals('nq_customer_mask', $response['updateNegotiableQuoteQuantities']['quote']['uid']);
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
    public function testUpdateNegotiableQuoteQuantitiesForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);
        $quoteItemId = $quote->getItems()[0]->getItemId();

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)$quoteItemId), 5.00);
        $this->graphQlMutation($query, [], '', $headers);
    }

    /**
     * Test invalid item quantities
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @dataProvider invalidQuantitiesDataProvider
     *
     * @param float $qty
     * @param string $errorMessage
     */
    public function testUpdateNegotiableQuoteQuantitiesInvalidQuantities(float $qty, string $errorMessage): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $quotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());

        $quoteId = array_key_last($quotes);
        $quote = $this->quoteRepository->get($quoteId);
        $quoteItemId = $quote->getItems()[0]->getItemId();

        $this->expectException(Exception::class);
        if ($qty <= 0) {
            $errorMessage .= base64_encode((string)$quoteItemId);
        }
        $this->expectExceptionMessage($errorMessage);

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)$quoteItemId), $qty);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Data provider for invalid item quantities
     *
     * @return array
     */
    public function invalidQuantitiesDataProvider(): array
    {
        return [
            [0, 'Quantity less than or equal to 0 is not allowed for item uids: '],
            [-1, 'Quantity less than or equal to 0 is not allowed for item uids: '],
            [101, 'The requested qty is not available'],
            [999999999, 'The requested qty exceeds the maximum qty allowed in shopping cart']
        ];
    }

    /**
     * Generates GraphQl mutation to update negotiable quote item quantity
     *
     * @param string $quoteId
     * @param string $quoteItemId
     * @param float $quantity
     *
     * @return string
     */
    private function getQuery(string $quoteId, string $quoteItemId, float $quantity): string
    {
        return <<<MUTATION
mutation {
  updateNegotiableQuoteQuantities(
    input: {
      quote_uid: "{$quoteId}"
      items: [
        {
          quote_item_uid: "{$quoteItemId}",
          quantity: {$quantity},
        }
      ]
    }
  ) {
    quote {
      uid
      items {
        id
        quantity
      }
    }
  }
}
MUTATION;
    }

    /**
     * Get authentication header
     *
     * @param string $username
     * @param string $password
     * @return array
     *
     * @throws AuthenticationException
     */
    private function getHeaderMap(
        string $username = 'customercompany22@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ["Authorization" => "Bearer $customerToken"];
    }
}
