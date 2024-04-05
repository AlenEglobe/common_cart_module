<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\SharedCatalog\Grouped;

use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\GroupedProduct\Test\Fixture\Product as GroupedProductFixture;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\ObjectManager;
use Magento\SharedCatalog\Api\SharedCatalogManagementInterface;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;

/**
 * Add products to cart from a specific shared catalog for grouped product
 */
class AddProductToCartTest extends GraphQlAbstract
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /**
     * @var SharedCatalogManagementInterface
     */
    private $sharedCatalogManagement;

    /**
     * @var ProductManagementInterface
     */
    private $productManagement;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->fixtures = DataFixtureStorageManager::getStorage();
        $this->sharedCatalogManagement = $this->objectManager->get(
            \Magento\SharedCatalog\Api\SharedCatalogManagementInterface::class
        );
        $this->productManagement = $this->objectManager->create(
            \Magento\SharedCatalog\Api\ProductManagementInterface::class
        );
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(
            GetCustomerAuthenticationHeader::class
        );
    }

    /**
     * Given Catalog permissions are enabled
     * Shared Catalog is enabled, company is active"
     * And "Allow Browsing Category" is set to "Yes, for Everyone"
     * And "Display Product Prices" is set to "Yes, for Everyone"
     * And "Allow Adding to Cart" is set to "Yes, for Everyone"
     * All products are only in the general public catalog (& not in a custom shared catalog)
     * And a grouped product is assigned to a public shared catalog
     * When a customer requests to add the grouped product
     * Then the cart is populated with the requested products
     *
     * @magentoConfigFixture default_store catalog/magento_catalogpermissions/enabled 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_category_view 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_product_price 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_checkout_items 1
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/sharedcatalog_active 1
     * @magentoApiDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     */
    #[
        DataFixture(
            GroupedProductFixture::class,
            [
                'sku' => 'grouped-product-allowed',
                'product_links' => [
                    ['sku' => 'simple_product_1', 'qty' => 12],
                    ['sku' => 'simple_product_2', 'qty' => 20],
                ]
            ],
            'grouped-product-allowed'
        )
    ]
    public function testGroupedProductIsAddedToCart()
    {
        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();
        /** @var ProductInterface $groupedProduct*/
        $groupedProduct = $this->fixtures->get('grouped-product-allowed');
        /** @var \Magento\SharedCatalog\Api\ProductManagementInterface $productManagement */
        $this->productManagement->assignProducts($sharedCatalog->getId(), [$groupedProduct]);
        $this->reindexCatalogPermissions();

        $productSku = 'grouped-product-allowed';
        $desiredQuantity = 5;
        $cartId = $this->createEmptyCart();
        $response = $this->graphQlMutation(
            $this->prepareMutation($cartId, $productSku, $desiredQuantity),
            [],
            ''
        );
        $this->removeQuote($cartId);
        $this->assertNotEmpty($response['addProductsToCart']['cart']['items']);
        $this->assertCount(2, $response['addProductsToCart']['cart']['items']);
        $this->assertArrayHasKey('user_errors', $response['addProductsToCart']);
        $userErrors = $response['addProductsToCart']['user_errors'];
        $this->assertCount(0, $userErrors);
    }

    /**
     * Given Catalog permissions are enabled
     * Shared Catalog is enabled, company is active"
     * And "Allow Browsing Category" is set to "Yes, for Everyone"
     * And "Display Product Prices" is set to "Yes, for Everyone"
     * And "Allow Adding to Cart" is set to "Yes, for Everyone"
     * All products are only in the general public catalog (& not in a custom shared catalog)
     * And a grouped product with denied category is assigned to a public shared catalog
     * When a customer requests to add the grouped product
     * Then the cart is populated with only allowed products from grouped product
     *
     * @magentoConfigFixture default_store catalog/magento_catalogpermissions/enabled 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_category_view 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_product_price 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_checkout_items 1
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/sharedcatalog_active 1
     * @magentoApiDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     */
    #[
        DataFixture(CategoryFixture::class, ['name' => 'Denied category'], 'denied_category'),
        DataFixture(
            ProductFixture::class,
            [
                'name' => 'Simple Product in Denied Category',
                'sku' => 'simple-product-in-denied-category',
                'category_ids' => ['$denied_category.id$'],
                'price' => 10,
            ],
            'simple_product_in_denied_category'
        ),
        DataFixture(
            GroupedProductFixture::class,
            [
                'sku' => 'grouped-product-with-denied-product-options',
                'category_ids' => ['$denied_category.id$'],
                'product_links' => [
                    ['sku' => 'simple_product_1', 'qty' => 12],
                    ['sku' => 'simple_product_2', 'qty' => 20],
                    ['sku' => '$simple_product_in_denied_category.sku$', 'qty' => 12],
                ]
            ],
            'grouped-product-with-denied-product-options'
        ),
    ]
    public function testOnlyAllowedProductsIsAddedToCartWithDeniedProductsOptionsInGroupedProduct()
    {
        $this->markTestSkipped('Test is skipped by issue AC-6872');

        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();
        /** @var ProductInterface $groupedProduct */
        $groupedProduct = $this->fixtures->get(
            'grouped-product-with-denied-product-options'
        );
        /** @var \Magento\SharedCatalog\Api\ProductManagementInterface $productManagement */
        $this->productManagement->assignProducts($sharedCatalog->getId(), [$groupedProduct]);
        $this->reindexCatalogPermissions();

        $productSku = 'grouped-product-with-denied-product-options';
        $desiredQuantity = 5;
        $cartId = $this->createEmptyCart();
        $response = $this->graphQlMutation(
            $this->prepareMutation($cartId, $productSku, $desiredQuantity),
            [],
            ''
        );
        $this->removeQuote($cartId);
        $this->assertNotEmpty($response['addProductsToCart']['cart']['items']);
        $this->assertCount(2, $response['addProductsToCart']['cart']['items']);
        $this->assertArrayHasKey('user_errors', $response['addProductsToCart']);
        $userErrors = $response['addProductsToCart']['user_errors'];
        $this->assertCount(1, $userErrors);
        $this->assertEquals('PERMISSION_DENIED', $userErrors[0]['code']);

        //TODO: determine the exact error message needs to
        // either individual product or the grouped product
        $this->assertStringContainsString($groupedProduct->getSku(), $userErrors[0]['message']);
    }

    /**
     * Given Catalog permissions are enabled
     * Shared Catalog is enabled, company is active"
     * And "Allow Browsing Category" is set to "Yes, for Everyone"
     * And "Display Product Prices" is set to "Yes, for Everyone"
     * And "Allow Adding to Cart" is set to "NO, for Everyone"
     * All products are only in the general public catalog (& not in a custom shared catalog)
     * And a grouped product is assigned to a public shared catalog
     * When a customer requests to add the grouped product
     * Then the cart is empty as Allow Adding to Cart permission is disabled
     *
     * @magentoConfigFixture default_store catalog/magento_catalogpermissions/enabled 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_category_view 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_catalog_product_price 1
     * @magentoConfigFixture catalog/magento_catalogpermissions/grant_checkout_items 0
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/sharedcatalog_active 1
     * @magentoApiDataFixture Magento/SharedCatalog/_files/public_shared_catalog_products.php
     */
    #[
        DataFixture(
            GroupedProductFixture::class,
            [
                'sku' => 'grouped-product-denied',
                'product_links' => [
                    ['sku' => 'simple_product_1', 'qty' => 12],
                    ['sku' => 'simple_product_2', 'qty' => 20],
                ]
            ],
            'grouped-product-denied'
        )
    ]
    public function testGroupedProductIsDeniedToCart()
    {
        $sharedCatalog = $this->sharedCatalogManagement->getPublicCatalog();
        /** @var ProductInterface $groupedProduct */
        $groupedProduct = $this->fixtures->get('grouped-product-denied');
        /** @var \Magento\SharedCatalog\Api\ProductManagementInterface $productManagement */
        $this->productManagement->assignProducts($sharedCatalog->getId(), [$groupedProduct]);
        $this->reindexCatalogPermissions();

        $productSku = 'grouped-product-denied';
        $desiredQuantity = 5;
        $cartId = $this->createEmptyCart();
        $response = $this->graphQlMutation(
            $this->prepareMutation($cartId, $productSku, $desiredQuantity),
            [],
            ''
        );
        $this->assertEmpty($response['addProductsToCart']['cart']['items']);
        $this->assertArrayHasKey('user_errors', $response['addProductsToCart']);
        $this->assertCount(
            1,
            $response['addProductsToCart']['user_errors']
        );
        $this->assertEquals(
            'PERMISSION_DENIED',
            $response['addProductsToCart']['user_errors'][0]['code']
        );
        $this->assertStringContainsString(
            $groupedProduct->getSku(),
            $response['addProductsToCart']['user_errors'][0]['message']
        );
        $this->removeQuote($cartId);
    }

    /**
     * Prepare add products to cart mutation
     *
     * @param string $cartId
     * @param string $productSku
     * @param int $desiredQuantity
     * @return string
     */
    private function prepareMutation(string $cartId, string $productSku, int $desiredQuantity): string
    {
        $mutation = <<<MUTATION
mutation {
  addProductsToCart(
    cartId: "{$cartId}",
    cartItems: [
      {
          sku: "{$productSku}"
          quantity: {$desiredQuantity}
      }

      ]
  ) {
  user_errors {
        code,
        message
    }
    cart {
      items {
       quantity
       product {
          sku
        }
      }
    }
  }
}
MUTATION;
        return $mutation;
    }

    /**
     * Create empty cart
     *
     * @return string
     * @throws \Exception
     */
    private function createEmptyCart(): string
    {
        $query = <<<QUERY
mutation {
  createEmptyCart
}
QUERY;
        $response = $this->graphQlMutation(
            $query,
            [],
            ''
        );
        $cartId = $response['createEmptyCart'];
        return $cartId;
    }

    /**
     * Remove the quote
     *
     * @param string $maskedId
     */
    private function removeQuote(string $maskedId): void
    {
        $maskedIdToQuote = $this->objectManager->get(MaskedQuoteIdToQuoteIdInterface::class);
        $quoteId = $maskedIdToQuote->execute($maskedId);

        $cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $cartRepository->get($quoteId);
        $cartRepository->delete($quote);
    }

    /**
     * Reindex catalog permissions
     */
    private function reindexCatalogPermissions()
    {
        $appDir = dirname(Bootstrap::getInstance()->getAppTempDir());
        $out = '';
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec("php -f {$appDir}/bin/magento indexer:reindex catalogpermissions_category", $out);
    }
}
