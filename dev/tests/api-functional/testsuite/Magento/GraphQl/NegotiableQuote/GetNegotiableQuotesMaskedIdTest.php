<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\ResourceModel\Collection\QuoteIdMaskCollectionFactory;
use Magento\NegotiableQuoteGraphQl\Model\NegotiableQuote\ResourceModel\Collection\QuoteIdMaskCollection;
use Magento\Quote\Model\QuoteIdMask;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for Masked IDs in Negotiable Quote queries
 */
class GetNegotiableQuotesMaskedIdTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskCollectionFactory
     */
    private $maskCollectionFactory;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->maskCollectionFactory = $objectManager->get(QuoteIdMaskCollectionFactory::class);
    }

    /**
     * Testing that quotes without masked ids get them added when accessed by a query
     *
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_structure_no_view_subs.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quotes_for_structure_no_mask.php
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active 1
     * @magentoConfigFixture base_website btob/website_configuration/company_active 1
     * @throws \Exception
     */
    public function testGetNegotiableQuotesWithoutMaskedIds(): void
    {
        $query = <<< QUERY
{
  negotiableQuotes
  {
    total_count
    items {
      uid
      buyer {
        firstname
      }
    }
  }
}
QUERY;
        $response = $this->graphQlQuery($query, [], '', $this->getCustomerAuthHeaders("email@companyquote.com"));

        $this->assertEquals(3, $response['negotiableQuotes']['total_count']);
        $negotiableQuotes = [];
        foreach ($response['negotiableQuotes']['items'] as $negotiableQuote) {
            $negotiableQuotes[$negotiableQuote['buyer']['firstname']] = $negotiableQuote;
        }

        // Verify that the quote with an existing uid didn't change
        $this->assertArrayHasKey('John', $negotiableQuotes);
        $this->assertEquals('nq_admin_mask', $negotiableQuotes['John']['uid']);

        // Verify that new uids exist and are unique
        $this->assertArrayHasKey('Manager', $negotiableQuotes);
        $this->assertNotEmpty($negotiableQuotes['Manager']['uid']);
        $managerUid = $negotiableQuotes['Manager']['uid'];
        $this->assertNotEquals('nq_admin_mask', $managerUid);
        $this->assertArrayHasKey('Customer', $negotiableQuotes);
        $this->assertNotEmpty($negotiableQuotes['Customer']['uid']);
        $customerUid = $negotiableQuotes['Customer']['uid'];
        $this->assertNotEquals('nq_admin_mask', $customerUid);
        $this->assertNotEquals($managerUid, $customerUid);

        // Verify that the new mask objects match the expected quotes
        /** @var QuoteIdMaskCollection $collection */
        $collection = $this->maskCollectionFactory->create();
        $collection->addFieldToFilter('masked_id', ['in' => [
            $negotiableQuotes['Manager']['uid'],
            $negotiableQuotes['Customer']['uid']
        ]]);
        $masks = [];
        /** @var QuoteIdMask $mask */
        foreach ($collection->getItems() as $mask) {
            $masks[$mask->getMaskedId()] = (int)$mask->getQuoteId();
        }

        $this->assertArrayHasKey($managerUid, $masks);
        $this->assertArrayHasKey($customerUid, $masks);
        $managerQuote = $this->cartRepository->get((int)$masks[$managerUid]);
        $this->assertNotEmpty($managerQuote);
        $this->assertEquals('Manager', $managerQuote->getCustomer()->getFirstname());
        $customerQuote = $this->cartRepository->get((int)$masks[$customerUid]);
        $this->assertNotEmpty($customerQuote);
        $this->assertEquals('Customer', $customerQuote->getCustomer()->getFirstname());
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
}
