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
 * Test coverage to set negotiable quote shipping address.
 */
class SetNegotiableQuoteShippingMethodTest extends GraphQlAbstract
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
     * Test that the shipping address can be set on a negotiable quote using an existing customer address id.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethods(): void
    {
        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        // Assert the quote data is present and correct
        $this->assertNotEmpty($response['setNegotiableQuoteShippingMethods']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuoteShippingMethods']);
        $this->assertEquals('nq_customer_mask', $response['setNegotiableQuoteShippingMethods']['quote']['uid']);

        // Assert the shipping address data is present and correct
        $this->assertArrayHasKey('shipping_addresses', $response['setNegotiableQuoteShippingMethods']['quote']);
        $responseShippingAddress = $response['setNegotiableQuoteShippingMethods']['quote']['shipping_addresses'][0];
        $this->assertArrayHasKey('selected_shipping_method', $responseShippingAddress);
        $responseShippingMethods = $responseShippingAddress['selected_shipping_method'];
        $this->assertResponseFields($responseShippingMethods, [
            'carrier_code' => 'flatrate',
            'method_code' => 'flatrate'
        ]);
    }

    /**
     * Test that attempting to set a shipping methods on a negotiable quote without a shipping address throws an error.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout_without_shipping_address.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsWithoutShippingAddress(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The shipping address is missing. Set the address and try again.');

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Test that attempting to set multiple different shipping methods on a negotiable quote throws an error.
     *
     * This is currently unsupported in GraphQL.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetMultipleShippingMethods(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You cannot specify multiple shipping methods.');

        $query = $this->getMutationWithMultipleMethods('nq_customer_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for guest customer token
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetShippingMethodsWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query);
    }

    /**
     * Testing for module enabled
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 0
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetShippingMethodsNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
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
    public function testSetShippingMethodsCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap("customernocompany@example.com"));
    }

    /**
     * Testing for feature enabled on company
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetShippingMethodsNoCompanyFeatureEnabled(): void
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

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
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
    public function testSetShippingMethodsNoCheckoutPermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The current customer does not have permission to set shipping method on the negotiable quote.'
        );

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for quote ownership
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetShippingMethodsUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getMutation('nq_admin_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_with_item_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_item_customer_mask');

        $query = $this->getMutation('cart_item_customer_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that quote is in a valid status
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsBadStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quote nq_customer_closed_mask is currently locked, '
            . 'and you cannot set the shipping method at the moment.'
        );

        $query = $this->getMutation('nq_customer_closed_mask', 'flatrate', 'flatrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that address id is valid
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsInvalidMethod(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Carrier with such method not found: dummyrate, dummyrate");

        $query = $this->getMutation('nq_customer_mask', 'dummyrate', 'dummyrate');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsForInvalidNegotiableQuoteId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Could not find quotes with the following UIDs: 9999");

        $negotiableQuoteQuery = $this->getMutation('9999', 'flatrate', 'flatrate');
        $this->graphQlMutation($negotiableQuoteQuery, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that a quote for a different store on the same website is accessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_store.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $query = $this->getMutation('nq_customer_mask', 'flatrate', 'flatrate');
        $response = $this->graphQlMutation($query, [], '', $headers);

        // Assert the quote data is present and correct
        $this->assertNotEmpty($response['setNegotiableQuoteShippingMethods']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuoteShippingMethods']);
        $this->assertEquals('nq_customer_mask', $response['setNegotiableQuoteShippingMethods']['quote']['uid']);

        // Assert the shipping address data is present and correct
        $this->assertArrayHasKey('shipping_addresses', $response['setNegotiableQuoteShippingMethods']['quote']);
        $responseShippingAddress = $response['setNegotiableQuoteShippingMethods']['quote']['shipping_addresses'][0];
        $this->assertArrayHasKey('selected_shipping_method', $responseShippingAddress);
        $responseShippingMethods = $responseShippingAddress['selected_shipping_method'];
        $this->assertResponseFields($responseShippingMethods, [
            'carrier_code' => 'flatrate',
            'method_code' => 'flatrate'
        ]);
    }

    /**
     * Testing that a quote for a different website is inaccessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_checkout.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_website.php
     * @magentoConfigFixture secondwebsitestore_store customer/account_share/scope 0
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingMethodsForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        $query = $this->getMutation('nq_customer_mask', 'dummyrate', 'dummyrate');
        $this->graphQlMutation($query, [], '', $headers);
    }

    /**
     * Generates the GraphQl mutation to set the negotiable quote shipping address based on an existing
     * customer address id.
     *
     * @param string $quoteId
     * @param string $carrierCode
     * @return string
     */
    private function getMutation(string $quoteId, string $carrierCode, string $methodCode): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingMethods(
    input: {
      quote_uid: "{$quoteId}"
      shipping_methods: [{
        carrier_code: "{$carrierCode}"
        method_code: "{$methodCode}"
      }]
    }
  ) {
    quote {
      uid
      shipping_addresses {
        selected_shipping_method{carrier_code method_code}
      }
    }
  }
}
MUTATION;
    }

    /**
     * Generates the GraphQl mutation to set multiple shipping addresses on the negotiable quote.
     *
     * @param string $quoteId
     * @return string
     */
    private function getMutationWithMultipleMethods(string $quoteId): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingMethods(
    input: {
      quote_uid: "{$quoteId}"
      shipping_methods: [{
        carrier_code: "flatrate"
        method_code: "flatrate"
      },
      {
        carrier_code: "tablerate"
        method_code: "bestway"
      }]
    }
  ) {
    quote {
      uid
      shipping_addresses {
        selected_shipping_method {
          carrier_code method_code
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
}
