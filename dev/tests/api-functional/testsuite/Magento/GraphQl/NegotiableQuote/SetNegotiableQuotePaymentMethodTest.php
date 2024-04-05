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
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to set negotiable quote payment method.
 */
class SetNegotiableQuotePaymentMethodTest extends GraphQlAbstract
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
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiableQuoteManagement;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->negotiableQuoteManagement = $objectManager->get(NegotiableQuoteManagementInterface::class);
    }

    /**
     * Tests that company admin can successfully set the payment method on a negotiable method
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testSetNegotiableQuotePaymentMethodCompanyAdmin()
    {
        $customerEmail = 'email@companyquote.com';
        $this->getNegotiableQuoteUpdatedByAdmin($customerEmail);
        $getNegotiableQuotesCustomer = <<< QUERY
{
  negotiableQuotes
  {
    items { uid }
  }
}
QUERY;
        $response = $this->graphQlQuery(
            $getNegotiableQuotesCustomer,
            [],
            '',
            $this->getHeaderMap("email@companyquote.com")
        );
        $negotiableQuoteMaskedId = $response['negotiableQuotes']['items'][0]['uid'];
        $setPaymentQuery = $this->getQuery($negotiableQuoteMaskedId, 'checkmo');
        $response = $this->graphQlMutation(
            $setPaymentQuery,
            [],
            '',
            $this->getHeaderMap("email@companyquote.com")
        );
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuotePaymentMethod']);
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']['quote']['available_payment_methods']);
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']['quote']['selected_payment_method']);
        $this->assertEquals($negotiableQuoteMaskedId, $response['setNegotiableQuotePaymentMethod']['quote']['uid']);
        $this->assertEquals(
            'quote_customer_send',
            $response['setNegotiableQuotePaymentMethod']['quote']['name']
        );
        $this->assertEquals(
            'UPDATED',
            $response['setNegotiableQuotePaymentMethod']['quote']['status']
        );
        $this->assertEquals(
            'checkmo',
            $response['setNegotiableQuotePaymentMethod']['quote']['available_payment_methods'][0]['code']
        );
        $this->assertEquals(
            'checkmo',
            $response['setNegotiableQuotePaymentMethod']['quote']['selected_payment_method']['code']
        );
        $this->assertEquals(
            'simple',
            $response['setNegotiableQuotePaymentMethod']['quote']['items'][0]['product']['sku']
        );
        $this->assertEquals(
            'Simple Product',
            $response['setNegotiableQuotePaymentMethod']['quote']['items'][0]['product']['name']
        );
    }

    /**
     * Helper method to get the negotiable quote in an updated status so that it can proceed to checkout
     *
     * @param string $customerEmail
     * @return void
     */
    private function getNegotiableQuoteUpdatedByAdmin(string $customerEmail): void
    {
        $customer = $this->customerRepository->get($customerEmail);
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());

        $negotiableQuoteId = array_key_first($negotiableQuotes);

        /** @var NegotiableQuoteInterface $negotiableQuoteCompanyAdmin */
        $negotiableQuoteCompanyAdmin = $this->negotiableQuoteRepository->getById($negotiableQuoteId);
        $quoteCompanyAdmin = $this->cartRepository->get($negotiableQuoteCompanyAdmin->getQuoteId());
        $this->negotiableQuoteManagement->recalculateQuote($quoteCompanyAdmin->getId(), true);
        $quoteCompanyAdmin->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
        $this->negotiableQuoteRepository->save($negotiableQuoteCompanyAdmin);
        $negotiableQuoteCompanyAdmin->setStatus('submitted_by_admin');
        $quoteCompanyAdmin->getExtensionAttributes()->setNegotiableQuote($negotiableQuoteCompanyAdmin);
        $this->cartRepository->save($quoteCompanyAdmin);
    }

    /**
     * Tests that company customer with no checkout permission cannot set payment method on negotiable quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_view_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_payment_method.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetNegotiableQuotePaymentMethodCompanyUserWithNoCheckoutPermission(): void
    {
        $query = $this->getQuery('nq_customer_mask', 'checkmo');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The current customer does not have permission to set payment method on the negotiable quote.'
        );
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Tests that payment method cannot be set if shipping address is not available on negotiable quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testSetPaymentMethodWithNoShippingAddress()
    {
        $customerEmail = 'email@companyquote.com';
        $this->getNegotiableQuoteUpdatedByAdmin($customerEmail);
        $query = $this->getQuery('nq_admin_mask', 'checkmo');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The shipping address is missing. Set the address and try again.');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap('email@companyquote.com'));
    }

    /**
     * Tests that payment method can be set if shipping address is not available on neg quote with virtual products
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_virtual.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_virtual_product.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testSetPaymentMethodVirtualProducts()
    {
        $customerEmail = 'email@companyquote.com';
        $this->getNegotiableQuoteUpdatedByAdmin($customerEmail);
        $getNegotiableQuotesCustomer = <<< QUERY
{
  negotiableQuotes
  {
    items { uid }
  }
}
QUERY;
        $response = $this->graphQlQuery(
            $getNegotiableQuotesCustomer,
            [],
            '',
            $this->getHeaderMap("email@companyquote.com")
        );
        $negotiableQuoteMaskedId = $response['negotiableQuotes']['items'][0]['uid'];
        $setPaymentQuery = $this->getQuery($negotiableQuoteMaskedId, 'checkmo');
        $response = $this->graphQlMutation(
            $setPaymentQuery,
            [],
            '',
            $this->getHeaderMap("email@companyquote.com")
        );
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuotePaymentMethod']);
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']['quote']['available_payment_methods']);
        $this->assertNotEmpty($response['setNegotiableQuotePaymentMethod']['quote']['selected_payment_method']);
        $this->assertEquals($negotiableQuoteMaskedId, $response['setNegotiableQuotePaymentMethod']['quote']['uid']);
        $this->assertEquals(
            'quote_customer_send',
            $response['setNegotiableQuotePaymentMethod']['quote']['name']
        );
        $this->assertEquals(
            'UPDATED',
            $response['setNegotiableQuotePaymentMethod']['quote']['status']
        );
        $this->assertEquals(
            'checkmo',
            $response['setNegotiableQuotePaymentMethod']['quote']['available_payment_methods'][0]['code']
        );
        $this->assertEquals(
            'checkmo',
            $response['setNegotiableQuotePaymentMethod']['quote']['selected_payment_method']['code']
        );
        $this->assertEquals(
            'virtual-product',
            $response['setNegotiableQuotePaymentMethod']['quote']['items'][0]['product']['sku']
        );
        $this->assertEquals(
            'Virtual Product',
            $response['setNegotiableQuotePaymentMethod']['quote']['items'][0]['product']['name']
        );
    }

    /**
     * Tests if a guest can set payment on the negotiable quote
     *
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetPaymentMethodWithNoCustomerToken(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask', $paymentMethod);
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
    public function testSetPaymentMethodNegotiableQuoteModuleNotEnabled(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getQuery('nq_customer_mask', $paymentMethod);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Tests that a customer who doesn't belong to the company cannot set payment on the neg quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_no_company.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetPaymentMethodCustomerNoCompany(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getQuery('nq_customer_mask', $paymentMethod);
        $this->graphQlMutation(
            $query,
            [],
            '',
            $this->getHeaderMap("customernocompany@example.com")
        );
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
    public function testSetPaymentMethodNoCompanyFeatureEnabled(): void
    {
        $paymentMethod = 'checkmo';
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

        $query = $this->getQuery('nq_customer_mask', $paymentMethod);
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
    public function testSetPaymentMethodUnownedQuote(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getQuery('nq_admin_mask', $paymentMethod);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Tests if quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_with_item_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetPaymentMethodNonNegotiable(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_item_customer_mask');

        $query = $this->getQuery('cart_item_customer_mask', $paymentMethod);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Tests that payment cannot be set on a negotiable quote with an invalid status like Closed
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetPaymentMethodInvalidStatus(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quote nq_customer_closed_mask is currently locked, and you cannot set ' .
            'the payment method at the moment.'
        );

        $query = $this->getQuery('nq_customer_closed_mask', $paymentMethod);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Tests if an invalid payment method can be set on the negotiable quote
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_for_payment_method.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     */
    public function testSetInvalidPaymentMethodNegotiableQuote()
    {
        $customerEmail = 'customercompany22@example.com';
        $this->getNegotiableQuoteUpdatedByAdmin($customerEmail);
        $paymentMethod = 'invalid';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The requested Payment Method is not available.");

        $query = $this->getQuery('nq_customer_mask', $paymentMethod);
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_checkout_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetPaymentMethodForInvalidNegotiableQuoteId(): void
    {
        $paymentMethod = 'checkmo';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Could not find quotes with the following UIDs: 9999");

        $negotiableQuoteQuery = $this->getQuery('9999', $paymentMethod);
        $this->graphQlMutation($negotiableQuoteQuery, [], '', $this->getHeaderMap());
    }

    /**
     * Generates GraphQl mutation to set payment method
     *
     * @param string $quoteId
     * @param string $paymentMethod
     * @return string
     */
    private function getQuery(string $quoteId, string $paymentMethod): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuotePaymentMethod(
    input: {
      quote_uid: "{$quoteId}"
      payment_method: {code: "{$paymentMethod}"}
    }
  ) {
    quote {
      uid
      name
      status
      created_at
      updated_at
      items { product { name sku } }
      available_payment_methods {
        code
        title
      }
      selected_payment_method {
        code
        title
        purchase_order_number
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
