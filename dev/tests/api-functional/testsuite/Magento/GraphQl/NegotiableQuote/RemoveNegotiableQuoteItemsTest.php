<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\NegotiableQuotePriceManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage to remove negotiable quote items.
 */
class RemoveNegotiableQuoteItemsTest extends GraphQlAbstract
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
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;


    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->quoteRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testRemoveNegotiableQuoteItem(): void
    {
        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);

        $response = $this->runSimpleMutation('nq_customer_mask', $quote);

        $this->assertNotEmpty($response['removeNegotiableQuoteItems']);
        $this->assertArrayHasKey('quote', $response['removeNegotiableQuoteItems']);
        $this->assertEquals('nq_customer_mask', $response['removeNegotiableQuoteItems']['quote']['uid']);
        $this->assertEmpty($response['removeNegotiableQuoteItems']['quote']['items']);
    }

    /**
     * Test for removing an item from negotiable quote with multiple products/items
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_multiple_items_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testRemoveNegotiableQuoteMultipleItems()
    {
        $product = $this->productRepository->get('simple');
        $product->setPrice(100);
        $this->productRepository->save($product);
        $customerEmail = 'customercompany22@example.com';
        $negotiableQuoteUid = 'nq_customer_mask';
        $customerNegotiableQuote = $this->getNegotiableQuoteQuery($negotiableQuoteUid);
        $response = $this->graphQlQuery(
            $customerNegotiableQuote,
            [],
            '',
            $this->getHeaderMap($customerEmail, 'password')
        );
        $this->assertNotEmpty($response['negotiableQuote']['items']);
        $this->assertCount(2, $response['negotiableQuote']['items']);
        $negotiableQuoteItems = $response['negotiableQuote']['items'];
        $itemIdToRemove = null;
        // making sure that Simple Product is selected for removal
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
      prices { grand_total{value currency} }
      history {
       change_type
       author {firstname }
       changes {
         statuses { changes {old_status new_status } }
         products_removed {
           products_removed_from_quote{ sku name}
         }
      }
    }
      items {
        uid
        quantity
        product {sku name}
      }
    }
  }
}
MUTATION;
        $response = $this->graphQlMutation($removeItemFromNegQuote, [], '', $this->getHeaderMap());
        $this->assertEquals('nq_customer_mask', $response['removeNegotiableQuoteItems']['quote']['uid']);
        $this->assertEquals('quote_customer_send', $response['removeNegotiableQuoteItems']['quote']['name']);
        $this->assertEquals('SUBMITTED', $response['removeNegotiableQuoteItems']['quote']['status']);
        $this->assertCount(1, $response['removeNegotiableQuoteItems']['quote']['items']);
        $remainingItem = $response['removeNegotiableQuoteItems']['quote']['items'][0];
        $this->assertNotEquals($itemIdToRemove, $remainingItem['uid']);
        $this->assertEquals('Simple Product2', $remainingItem['product']['name']);
        $this->assertEquals(35.2, $response['removeNegotiableQuoteItems']['quote']['prices']['grand_total']['value']);
        $this->assertEquals('USD', $response['removeNegotiableQuoteItems']['quote']['prices']['grand_total']['currency']);
    }

    /**
     * Returns GraphQl Query string to get negotiable quote
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
prices { grand_total { value } }
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
             statuses {
               changes { old_status new_status } }
               total{ new_price {value} old_price {value} }
             }
           }
        }
  }
QUERY;
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
    public function testRemoveNegotiableQuoteItemWithInvalidToken(
        string $customerEmail,
        string $customerPassword,
        string $message
    ): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     *
     * @throws Exception
     */
    public function testRemoveNegotiableQuoteItemWithNoCustomerToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current user is not a registered customer and cannot perform operations '
            . 'on negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
    public function testRemoveNegotiableQuoteItemNoModuleEnabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The Negotiable Quote module is not enabled.');

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
    public function testRemoveNegotiableQuoteItemCustomerNoCompany(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not belong to a company.');

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
    public function testRemoveNegotiableQuoteItemNoCompanyFeatureEnabled(): void
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

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
    public function testRemoveNegotiableQuoteItemNoManagePermissions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current customer does not have permission to manage negotiable quotes.');

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)1));
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
    public function testRemoveNegotiableQuoteItemUnownedQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId(1);
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);
        $this->runSimpleMutation('nq_admin_mask', $quote);
    }

    /**
     * Testing that quote is negotiable
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/cart_with_item_for_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testRemoveNegotiableQuoteItemNonNegotiable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The quotes with the following UIDs are not negotiable: '
            . 'cart_item_customer_mask');

        $customer = $this->customerRepository->get('customercompany22@example.com');
        $quote = $this->quoteRepository->getForCustomer($customer->getId());
        $this->runSimpleMutation('cart_item_customer_mask', $quote);
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
    public function testRemoveNegotiableQuoteItemBadStatus(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The quotes with the following UIDs have a status that does not allow them to be edited or submitted: '
            . 'nq_customer_closed_mask'
        );

        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);
        $this->runSimpleMutation('nq_customer_closed_mask', $quote);
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testRemoveItemForInvalidNegotiableQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find quotes with the following UIDs: 9999');

        $query = $this->getQuery('9999', base64_encode('9999'));
        $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_customer_with_manage_permissions.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote_by_customer.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws Exception
     */
    public function testRemoveInvalidItemForNegotiableQuote(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The following item IDs were not found on the specified quote: ' . base64_encode((string)0)
        );

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)0));
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
    public function testRemoveNegotiableQuoteItemForSecondStore(): void
    {
        $this->storeManager->setCurrentStore('secondstore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondstore';

        $customer = $this->customerRepository->get('customercompany22@example.com');
        $negotiableQuotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quoteId = array_key_last($negotiableQuotes);
        $quote = $this->quoteRepository->get($quoteId);
        $itemId = $quote->getItems()[0]->getItemId();

        $query = $this->getQuery('nq_customer_mask', base64_encode((string)$itemId));
        $response = $this->graphQlMutation($query, [], '', $headers);

        $this->assertNotEmpty($response['removeNegotiableQuoteItems']);
        $this->assertArrayHasKey('quote', $response['removeNegotiableQuoteItems']);
        $this->assertEquals('nq_customer_mask', $response['removeNegotiableQuoteItems']['quote']['uid']);
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
    public function testRemoveNegotiableQuoteItemForInvalidWebsite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not find a quote with the specified UID.');

        $this->storeManager->setCurrentStore('secondwebsitestore');
        $headers = $this->getHeaderMap();
        $headers['Store'] = 'secondwebsitestore';

        $query = $this->getQuery('nq_customer_mask', base64_encode('9999'));
        $this->graphQlMutation($query, [], '', $headers);
    }

    /**
     * Runs a query with the given quote id
     *
     * @param string $quoteId
     * @param CartInterface $quote
     * @return array|bool|float|int|string
     * @throws Exception
     */
    private function runSimpleMutation(string $quoteId, CartInterface $quote)
    {
        $quoteItemId = $quote->getItems()[0]->getItemId();
        $query = $this->getQuery($quoteId, base64_encode((string)$quoteItemId));
        return $this->graphQlMutation($query, [], '', $this->getHeaderMap());
    }

    /**
     * Generates GraphQl mutation to remove quote item from negotiable quote
     *
     * @param string $quoteId
     * @param string $itemId
     * @return string
     */
    private function getQuery(string $quoteId, string $itemId): string
    {
        return <<<MUTATION
mutation {
  removeNegotiableQuoteItems(
    input: {
      quote_uid: "{$quoteId}"
      quote_item_uids: ["{$itemId}"]
    }
  ) {
    quote {
      uid
      name
      status
      created_at
      updated_at
      items {
        id
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
