<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\SharedCatalog;

use Magento\Catalog\Test\Fixture\Category;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogPermissions\Model\Permission as PermissionModel;
use Magento\CatalogPermissions\Test\Fixture\Permission;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\ConfigurableProductGraphQl\Model\Options\SelectionUidFormatter;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\SharedCatalog\Test\Fixture\AssignProducts as AssignProductsSharedCatalog;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\ObjectManager;
use Magento\SharedCatalog\Test\Fixture\AssignProductsCategory as AssignProductsCategory;
use Magento\SharedCatalog\Api\CompanyManagementInterface;
use Magento\SharedCatalog\Api\CategoryManagementInterface;
use Magento\CatalogPermissions\Model\Indexer\Category\Processor as CategoryPermissionsIndexer;

/**
 * Add products to cart from a specific shared catalog
 */
class AddProductToCartTest extends GraphQlAbstract
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /**
     * @var SelectionUidFormatter
     */
    private $selectionUidFormatter;

    /**
     * @var CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @var CategoryManagementInterface
     */
    private $categoryManagement;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->selectionUidFormatter = $this->objectManager->get(SelectionUidFormatter::class);
        $this->companyManagement = $this->objectManager->get(CompanyManagementInterface::class);
        $this->categoryManagement = $this->objectManager->get(CategoryManagementInterface::class);
    }

    /**
     * Response should have cart items available
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(Product::class, as: 'product'),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$product$'],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$product.id$',
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
        DataFixture(
            Permission::class,
            [
                'category_id' => '$category.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_ALLOW
            ]
        )
    ]
    public function testProductIsAddedToCart()
    {
        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        /** @var \Magento\Catalog\Model\Product $product */
        $product = DataFixtureStorageManager::getStorage()->get('product');

        /** @var \Magento\Company\Model\Company $company */
        $company = DataFixtureStorageManager::getStorage()->get('company');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        $this->companyManagement->assignCompanies($sharedCatalog->getId(), [$company]);
        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        $desiredQuantity = 5;
        $headerAuthorization = $this->getCustomerAuthenticationHeader
            ->execute($companyAdmin->getEmail());
        $cartId = $this->createEmptyCart($headerAuthorization);

        $response = $this->graphQlMutation(
            $this->prepareMutation($cartId, $product->getSku(), $desiredQuantity),
            [],
            '',
            $headerAuthorization
        );

        $expected = [
            "addProductsToCart" => [
                "cart" => [
                    "items" => [
                        [
                            "quantity" => 5,
                            "product" => ["sku" => $product->getSku()]
                        ]
                    ]
                ],
                "user_errors"=>[]
            ]
        ];

        $this->removeQuote($cartId);
        $this->assertEquals($expected, $response);
    }

    /**
     * Response should have configurable product in the cart.
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(
            Permission::class,
            [
                'category_id' => '$category.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_ALLOW
            ]
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(ProductFixture::class, ['stock_item' => ['is_qty_decimal' => true]], 'product1'),
        DataFixture(ProductFixture::class, as: 'product2'),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$product1$', '$product2$']],
            'configurable_product'
        ),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$configurable_product$'],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsSharedCatalog::class,
            [
                'product_ids' => [
                    '$configurable_product.id$',
                ],
                'catalog_id' => '$shared_catalog.id$',
            ]
        ),
    ]
    public function testConfigurableProductIsAddedToCart()
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        /** @var \Magento\Catalog\Model\Product $configurableProduct */
        $configurableProduct = DataFixtureStorageManager::getStorage()->get('configurable_product');

        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var \Magento\Company\Model\Company $company */
        $company = DataFixtureStorageManager::getStorage()->get('company');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');

        $this->companyManagement->assignCompanies($sharedCatalog->getId(), [$company]);
        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        $attributeId = (int)$configurableProduct->getExtensionAttributes()
            ->getConfigurableProductOptions()[0]
            ->getAttributeId();
        $valueIndex = (int)$configurableProduct->getExtensionAttributes()
            ->getConfigurableProductOptions()[0]
            ->getOptions()[0]['value_index'];

        $desiredQuantity = 3;

        $headerAuthorization = $this->getCustomerAuthenticationHeader
            ->execute($companyAdmin->getEmail());

        $cartId = $this->createEmptyCart($headerAuthorization);
        $options = $this->generateSuperAttributesUIDQuery($attributeId, $valueIndex);
        $mutation = $this->getAddConfigurableProductToCartMutation(
            $cartId,
            $configurableProduct->getSku(),
            $desiredQuantity,
            $options
        );
        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $headerAuthorization
        );

        $expected = [
            "addProductsToCart" => [
                "cart" => [
                    "items" => [
                        [
                            "quantity" => 3,
                            "product" => [
                                "sku" => $configurableProduct->getSku(),
                                "id" => $configurableProduct->getId()
                            ],
                            "configurable_options" => [
                                [
                                    "id" => $attributeId,
                                    "value_id" =>$valueIndex
                                ]
                            ]
                        ]
                    ]
                ],
                "user_errors" => []
            ]
        ];

        $this->removeQuote($cartId);
        $this->assertEquals($expected, $response);
    }

    /**
     * Response should have no configurable product in cart
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(
            Permission::class,
            [
                'category_id' => '$category.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_ALLOW
            ]
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(ProductFixture::class, ['stock_item' => ['is_qty_decimal' => true]], 'product1'),
        DataFixture(ProductFixture::class, as: 'product2'),
        DataFixture(AttributeFixture::class, as: 'attribute'),
        DataFixture(
            ConfigurableProductFixture::class,
            ['_options' => ['$attribute$'], '_links' => ['$product1$', '$product2$']],
            'configurable_product'
        ),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$configurable_product$'],
                'category' => '$category$'
            ]
        ),
    ]
    public function testConfigurableProductIsDeniedToCart()
    {
        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        $desiredQuantity = 5;

        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = DataFixtureStorageManager::getStorage()->get('customer');

        /** @var \Magento\Catalog\Model\Product $configurableProduct */
        $configurableProduct = DataFixtureStorageManager::getStorage()->get('configurable_product');

        $headerAuthorization = $this->getCustomerAuthenticationHeader
            ->execute($customer->getEmail());
        $cartId = $this->createEmptyCart($headerAuthorization);

        $attributeId = (int)$configurableProduct->getExtensionAttributes()
            ->getConfigurableProductOptions()[0]
            ->getAttributeId();
        $valueIndex = (int)$configurableProduct->getExtensionAttributes()
            ->getConfigurableProductOptions()[0]
            ->getOptions()[0]['value_index'];
        $options = $this->generateSuperAttributesUIDQuery($attributeId, $valueIndex);
        $mutation = $this->getAddConfigurableProductToCartMutation(
            $cartId,
            $configurableProduct->getSku(),
            $desiredQuantity,
            $options
        );
        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $headerAuthorization
        );

        $this->removeQuote($cartId);

        $expected = [
            "addProductsToCart" => [
                "cart" => [
                    "items" => []
                ],
                "user_errors" => [
                    [
                        "message" => sprintf('You cannot add "%s" to the cart.', $configurableProduct->getSku())
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);
    }

    /**
     * Generates UID for super configurable product super attributes
     *
     * @param int $attributeId
     * @param int $valueIndex
     * @return string
     */
    private function generateSuperAttributesUIDQuery(int $attributeId, int $valueIndex): string
    {
        return 'selected_options: ["' . $this->selectionUidFormatter->encode($attributeId, $valueIndex) . '"]';
    }

    /**
     * @param string $maskedQuoteId
     * @param string $configurableSku
     * @param int $quantity
     * @param string $selectedOptionsQuery
     * @return string
     */
    private function getAddConfigurableProductToCartMutation(
        string $maskedQuoteId,
        string $configurableSku,
        int    $quantity,
        string $selectedOptionsQuery
    ): string {
        return <<<QUERY
mutation {
    addProductsToCart(
        cartId:"{$maskedQuoteId}"
        cartItems: [
            {
                sku: "{$configurableSku}"
                quantity: $quantity
                {$selectedOptionsQuery}
            }
        ]
    ) {
        cart {
            items {
                quantity
                product {
                    sku
                    id
                }
                ... on ConfigurableCartItem {
                    configurable_options {
                        id
                        value_id
                    }
                }
            }
        },
        user_errors {
            message
        }
    }
}
QUERY;
    }

    /**
     * Response should have no cart items
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            Company::class,
            [
                'super_user_id' => '$company_admin.id$',
                'customer_group_id' => '$customer_group.id$'
            ],
            'company'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(
            Permission::class,
            [
                'category_id' => '$category.id$',
                'website_id' => 1,
                'customer_group_id' => '$customer_group.id$',
                'grant_catalog_category_view' => PermissionModel::PERMISSION_ALLOW,
                'grant_catalog_product_price' => PermissionModel::PERMISSION_ALLOW,
                'grant_checkout_items' => PermissionModel::PERMISSION_DENY
            ]
        ),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
        DataFixture(Category::class, as: 'category'),
        DataFixture(Product::class, as: 'product'),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$product$'],
                'category' => '$category$'
            ]
        ),
        DataFixture(
            AssignProductsCategory::class,
            [
                'products' => ['$product$'],
                'category' => '$category$'
            ]
        ),
    ]
    public function testProductIsDeniedToCart()
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = DataFixtureStorageManager::getStorage()->get('product');

        /** @var \Magento\Customer\Model\Customer $customer */
        $companyAdmin = DataFixtureStorageManager::getStorage()->get('company_admin');

        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');

        /** @var \Magento\Company\Model\Company $company */
        $company = DataFixtureStorageManager::getStorage()->get('company');

        /** @var \Magento\Catalog\Model\Category $category */
        $category = DataFixtureStorageManager::getStorage()->get('category');
        $this->companyManagement->assignCompanies($sharedCatalog->getId(), [$company]);
        $this->categoryManagement->assignCategories($sharedCatalog->getId(), [$category]);

        $this->objectManager->create(CategoryPermissionsIndexer::class)->reindexAll();

        $desiredQuantity = 5;
        $headerAuthorization = $this->getCustomerAuthenticationHeader
            ->execute($companyAdmin->getEmail());
        $cartId = $this->createEmptyCart($headerAuthorization);

        $response = $this->graphQlMutation(
            $this->prepareMutation($cartId, $product->getSku(), $desiredQuantity),
            [],
            '',
            $headerAuthorization
        );

        $this->removeQuote($cartId);

        $expected = [
            "addProductsToCart" => [
                "cart" => [
                    "items" => []
                ],
                "user_errors" => [
                    [
                        "code" => "PERMISSION_DENIED",
                        "message" => sprintf('You cannot add "%s" to the cart.', $product->getSku())
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $response);
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
        return <<<MUTATION
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
    cart {
      items {
       quantity
       product {
          sku
        }
      }
    },
    user_errors {
        code,
        message
    }
  }
}
MUTATION;
    }

    /**
     * Create empty cart
     *
     * @param array $headerAuthorization
     * @return string
     * @throws \Exception
     */
    private function createEmptyCart(array $headerAuthorization): string
    {
        $query = <<<QUERY
mutation {
  createEmptyCart
}
QUERY;
        $response = $this->graphQlMutation(
            $query,
            [],
            '',
            $headerAuthorization
        );
        return $response['createEmptyCart'];
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
}
