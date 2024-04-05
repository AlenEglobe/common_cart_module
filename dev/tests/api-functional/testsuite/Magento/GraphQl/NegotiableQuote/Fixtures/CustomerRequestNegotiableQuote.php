<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote\Fixtures;

use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\TestCase\GraphQl\Client;

class CustomerRequestNegotiableQuote
{
    /**
     * @var Client
     */
    private $gqlClient;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $tokenService;

    /**
     * @var string
     */
    private $authHeader;

    /**
     * @var string
     */
    private $cartId;

    /**
     * @var array
     */
    private $customerLogin;

    /**
     * @param Client $gqlClient
     * @param CustomerTokenServiceInterface $tokenService
     */
    public function __construct(
        Client $gqlClient,
        CustomerTokenServiceInterface $tokenService
    ) {
        $this->gqlClient = $gqlClient;
        $this->tokenService = $tokenService;
    }

    /**
     * Make GraphQl POST request
     *
     * @param string $query
     * @param array $additionalHeaders
     * @return array
     */
    private function makeRequest(string $query, array $additionalHeaders = []): array
    {
        $headers = array_merge([$this->getAuthHeader()], $additionalHeaders);
        return $this->gqlClient->post($query, [], '', $headers);
    }

    /**
     * Get header for authenticated requests
     *
     * @return string
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
    private function getAuthHeader(): string
    {
        if (empty($this->authHeader)) {
            $customerToken = $this->tokenService
                ->createCustomerAccessToken($this->customerLogin['email'], $this->customerLogin['password']);
            $this->authHeader = "Authorization: Bearer {$customerToken}";
        }
        return $this->authHeader;
    }

    /**
     * @param array $negotiableQuoteData
     * @param $customerEmail
     * @return array
     */
    public function requestNegotiableQuotes(array $customerLogin, array $negotiableQuoteData): array
    {
        foreach ($negotiableQuoteData as $requestedQuote) {
            $this->customerLogin = $customerLogin;
            $maskedCartId = $this->createEmptyCart();
            $this->addProductToCart($maskedCartId, $requestedQuote);
            $negotiableQuotes[] = $this->requestNegotiableQuoteQuery($maskedCartId, $requestedQuote);
        }
        return $negotiableQuotes;
    }

    /**
     * @return string
     */
    private function createEmptyCart(): string
    {
        $createEmptyCart = <<<QUERY
mutation {
  createEmptyCart
}
QUERY;
        $result = $this->makeRequest($createEmptyCart);
        $maskedCartId = $result['createEmptyCart'];
        return $maskedCartId;
    }

    /**
     * @param string $cartId
     * @param float $quantity
     * @param string $sku
     * @return void
     */
    private function addProductToCart(string $maskedCartId, array $productData): void
    {
        $productSku = $productData['productSku'];
        $quantity = $productData['productQuantity'] ?? 1;
        $addProductToCart = <<<QUERY
mutation {
  addSimpleProductsToCart(
    input: {
      cart_id: "{$maskedCartId}"
      cart_items: [
        {
          data: {
            quantity: {$quantity}
            sku: "{$productSku}"
          }
        }
      ]
    }
  ) {
    cart {
      items {
        quantity
        product { sku  }
      }
    }
  }
}
QUERY;
        $this->makeRequest($addProductToCart);
    }

    /**
     * @param array $requestedQuote
     * @return array
     */
    private function requestNegotiableQuoteQuery(string $maskedCartId, array $requestedQuote): array
    {
        $quoteName = $requestedQuote['name'];
        $quoteComment = $requestedQuote['comment'];
        $requestNegotiableQuoteQuery = <<<QUERY
mutation {
  requestNegotiableQuote(
    input: {
      cart_id: "{$maskedCartId}"
      quote_name: "{$quoteName}"
      comment: {
        comment: "{$quoteComment}"
      }
    }
  ) {
    quote {
      uid
      name
      status
      created_at
      updated_at
    }
  }
}
QUERY;
        $response = $this->makeRequest($requestNegotiableQuoteQuery);
        $requestedNegotiableQuote = $response['requestNegotiableQuote']['quote'];
        return $requestedNegotiableQuote;
    }
}
