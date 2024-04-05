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
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Permission\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to set negotiable quote shipping address.
 */
class SetNegotiableQuoteShippingAddressTest extends GraphQlAbstract
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
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CollectionFactory
     */
    private $permissionCollection;

    /**
     * @var PermissionManagementInterface
     */
    private $permissionManagement;
    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->permissionCollection = $objectManager->get(CollectionFactory::class);
        $this->permissionManagement = $objectManager->get(PermissionManagementInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the shipping address can be set on a negotiable quote using an existing customer address id.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressWithCustomerAddressId(): void
    {
        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode((string)2));
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        // Assert the quote data is present and correct
        $this->assertNotEmpty($response['setNegotiableQuoteShippingAddress']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuoteShippingAddress']);
        $this->assertEquals('nq_customer_mask', $response['setNegotiableQuoteShippingAddress']['quote']['uid']);

        // Assert the shipping address data is present and correct
        $this->assertArrayHasKey('shipping_addresses', $response['setNegotiableQuoteShippingAddress']['quote']);
        $responseShippingAddress = $response['setNegotiableQuoteShippingAddress']['quote']['shipping_addresses'][0];
        $this->assertEquals('SUBMITTED', $response['setNegotiableQuoteShippingAddress']['quote']['status']);
        $this->assertResponseFields($responseShippingAddress, [
            'firstname' => 'John',
            'lastname' => 'Smith',
            'street' => ['Green str, 67'],
            'city' => 'CityM',
            'region' => ['code' => 'AL'],
            'postcode' => 75477,
            'country' => ['code' => 'US'],
            'telephone' => 3468676,
            'company' => 'CompanyName'
        ]);
    }

    /**
     * Test that the shipping address can be set on a negotiable quote using new address input data.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressWithNewAddressInput(): void
    {
        $query = $this->getMutationWithNewAddressInput('nq_customer_mask');
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        // Assert the quote data is present and correct
        $this->assertNotEmpty($response['setNegotiableQuoteShippingAddress']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuoteShippingAddress']);
        $this->assertEquals('nq_customer_mask', $response['setNegotiableQuoteShippingAddress']['quote']['uid']);

        // Assert the shipping address data is present and correct
        $this->assertArrayHasKey('shipping_addresses', $response['setNegotiableQuoteShippingAddress']['quote']);
        $responseShippingAddress = $response['setNegotiableQuoteShippingAddress']['quote']['shipping_addresses'][0];
        $this->assertEquals('SUBMITTED', $response['setNegotiableQuoteShippingAddress']['quote']['status']);
        $this->assertResponseFields($responseShippingAddress, [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => ['6161 West Centinela Ave.'],
            'city' => 'Culver City',
            'region' => ['code' => 'CA'],
            'postcode' => 90230,
            'country' => ['code' => 'US'],
            'telephone' => 5555555555,
            'company' => 'Magento'
        ]);
    }

    /**
     * Test that attempting to set multiple different shipping addresses on a negotiable quote throws an error.
     *
     * This is currently unsupported in GraphQL.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetMultipleShippingAddresses(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You cannot specify multiple shipping addresses.');

        $query = $this->getMutationWithMultipleAddresses('nq_customer_mask');
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Test that attempting to set a shipping address on a negotiable quote with both an existing
     * customer address id AND new address input throws an error.
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressWithCustomerAddressIdAndAddressInput(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The shipping address cannot contain "customer_address_uid" and "address" at the same time.'
        );

        $query = $this->getMutationWithCustomerAddressIdAndNewAddressInput(
            'nq_customer_mask',
            base64_encode((string)1)
        );
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
    public function testSetShippingAddressWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('2'));
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
    public function testSetShippingAddressNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('2'));
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
    public function testSetShippingAddressCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('2'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap("customernocompany@example.com"));
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
    public function testSetShippingAddressNoCompanyFeatureEnabled(): void
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

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('2'));
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
    public function testSetShippingAddressNoManagePermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to manage negotiable quotes.');

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('2'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing for quote ownership
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testSetShippingAddressUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $query = $this->getMutationWithCustomerAddressId('nq_admin_mask', base64_encode('2'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_with_item_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_item_customer_mask');

        $query = $this->getMutationWithCustomerAddressId('cart_item_customer_mask', base64_encode('2'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that quote is in a valid status
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer_closed.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressBadStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs have a status that does not allow them to be edited or submitted: '
            . 'nq_customer_closed_mask'
        );

        $query = $this->getMutationWithCustomerAddressId('nq_customer_closed_mask', base64_encode('2'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that address id is valid
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressInvalidAddressId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No address exists with the specified customer address ID.");

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('9999'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that address is owned by the customer
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressUnownedAddress(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No address exists with the specified customer address ID.");

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode('1'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressForInvalidNegotiableQuoteId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Could not find quotes with the following UIDs: 9999");

        $negotiableQuoteQuery = $this->getMutationWithCustomerAddressId('9999', base64_encode('0'));
        $this->graphQlMutation($negotiableQuoteQuery, [], '', $this->getHeaderMap());
    }

    /**
     * Testing that a quote for a different store on the same website is accessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_store.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode((string)2));
        $response = $this->graphQlMutation($query, [], '', $headers);

        // Assert the quote data is present and correct
        $this->assertNotEmpty($response['setNegotiableQuoteShippingAddress']);
        $this->assertArrayHasKey('quote', $response['setNegotiableQuoteShippingAddress']);
        $this->assertEquals('nq_customer_mask', $response['setNegotiableQuoteShippingAddress']['quote']['uid']);

        // Assert the shipping address data is present and correct
        $this->assertArrayHasKey('shipping_addresses', $response['setNegotiableQuoteShippingAddress']['quote']);
        $responseShippingAddress = $response['setNegotiableQuoteShippingAddress']['quote']['shipping_addresses'][0];
        $this->assertResponseFields($responseShippingAddress, [
            'firstname' => 'John',
            'lastname' => 'Smith',
            'street' => ['Green str, 67'],
            'city' => 'CityM',
            'region' => ['code' => 'AL'],
            'postcode' => 75477,
            'country' => ['code' => 'US'],
            'telephone' => 3468676,
            'company' => 'CompanyName'
        ]);
    }

    /**
     * Testing that a quote for a different website is inaccessible
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/second_website.php
     * @magentoConfigFixture secondwebsitestore_store customer/account_share/scope 0
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture secondwebsite_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode((string)2));
        $this->graphQlMutation($query, [], '', $headers);
    }

    /**
     * Test that shipping address cannot be set when manage permission is revoked after negotiable quote is created
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/customer_address_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testSetShippingAddressWithManagePermissionRevoked(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $roleName = 'role with Magento_NegotiableQuote';
        $permissionToDeny = [
            "Magento_NegotiableQuote::manage"
        ];
        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        // Get the user's role
        $this->searchCriteriaBuilder->addFilter('company_id', $companyId);
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName . '%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->roleRepository->getList($searchCriteria)->getItems();

        /** @var RoleInterface $role */
        $role = reset($results);

        $rolePermissions = $this->permissionCollection
            ->create()
            ->addFieldToFilter('role_id', ['eq' => $role->getId()])
            ->getColumnValues('resource_id');

        // Disable negotiable quote manage access for this role
        foreach ($permissionToDeny as $permission) {
            if (in_array($permission, $rolePermissions, true)) {
                $key = array_search($permission, $rolePermissions, true);
                unset($rolePermissions[$key]);
            }
        }
        $role->setPermissions($this->permissionManagement->populatePermissions($rolePermissions));
        $this->roleRepository->save($role);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current customer does not have permission to manage negotiable quotes.");
        $headers = $this->getHeaderMap();
        $query = $this->getMutationWithCustomerAddressId('nq_customer_mask', base64_encode((string)2));
        $this->graphQlMutation($query, [], '', $headers);
    }

    /**
     * Generates the GraphQl mutation to set the negotiable quote shipping address based on an existing
     * customer address id.
     *
     * @param string $quoteId
     * @param string $customerAddressId
     * @return string
     */
    private function getMutationWithCustomerAddressId(string $quoteId, string $customerAddressId): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingAddress(
    input: {
      quote_uid: "{$quoteId}"
      shipping_addresses: [{
        customer_address_uid: "{$customerAddressId}"
      }]
    }
  ) {
    quote {
      uid
      status
      shipping_addresses {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
        telephone
        company
      }
    }
  }
}
MUTATION;
    }

    /**
     * Generates the GraphQl mutation to set the shipping address based on new address input.
     *
     * @param string $quoteId
     * @return string
     */
    private function getMutationWithNewAddressInput(string $quoteId): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingAddress(
    input: {
      quote_uid: "{$quoteId}"
      shipping_addresses: [{
        address: {
          firstname: "John",
          lastname: "Doe",
          country_code: "US",
          street: ["6161 West Centinela Ave."],
          city: "Culver City",
          region_id: 12,
          region: "CA",
          postcode: "90230",
          telephone: "5555555555",
          company: "Magento"
          save_in_address_book: false
        }
      }]
    }
  ) {
    quote {
      uid
      status
      shipping_addresses {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
        telephone
        company
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
    private function getMutationWithMultipleAddresses(string $quoteId): string
    {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingAddress(
    input: {
      quote_uid: "{$quoteId}"
      shipping_addresses: [{
        address: {
          firstname: "John",
          lastname: "Doe",
          country_code: "US",
          street: ["6161 West Centinela Ave."],
          city: "Culver City",
          region_id: 12,
          region: "CA",
          postcode: "90230",
          telephone: "5555555555",
          company: "Magento"
          save_in_address_book: false
        }
      },{
        address: {
          firstname: "Jane",
          lastname: "Doe",
          country_code: "US",
          street: ["11501 Domain Dr #150"],
          city: "Austin",
          region_id: 57,
          region: "TX",
          postcode: "78758",
          telephone: "5555555555",
          company: "Adobe"
          save_in_address_book: false
        }
      }]
    }
  ) {
    quote {
      uid
      shipping_addresses {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
        telephone
        company
      }
    }
  }
}
MUTATION;
    }

    /**
     * Generates the GraphQl mutation to set the shipping address based on both a customer address id
     * and new address input.
     *
     * @param string $quoteId
     * @return string
     */
    private function getMutationWithCustomerAddressIdAndNewAddressInput(
        string $quoteId,
        string $customerAddressId
    ): string {
        return <<<MUTATION
mutation {
  setNegotiableQuoteShippingAddress(
    input: {
      quote_uid: "{$quoteId}"
      shipping_addresses: [{
        customer_address_uid:  "{$customerAddressId}"
        address: {
          firstname: "John",
          lastname: "Doe",
          country_code: "US",
          street: ["6161 West Centinela Ave."],
          city: "Culver City",
          region_id: 12,
          region: "CA",
          postcode: "90230",
          telephone: "5555555555",
          company: "Magento"
          save_in_address_book: false
        }
      }]
    }
  ) {
    quote {
      uid
      status
      shipping_addresses {
        firstname
        lastname
        street
        city
        region {
          code
        }
        postcode
        country {
          code
        }
        telephone
        company
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
