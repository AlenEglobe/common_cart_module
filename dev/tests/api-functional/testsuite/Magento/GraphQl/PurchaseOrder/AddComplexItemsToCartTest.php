<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\NegotiableQuote\Test\Fixture\QuoteIdMask;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\ConfigurableProduct\Test\Fixture\AddProductToCart as AddConfigurableProductToCartFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test adding complex purchase order items to the shopping cart
 */
class AddComplexItemsToCartTest extends GraphQlAbstract
{

    private const QUERY_CUSTOMER_CART = <<<QRY
{
  customerCart {
    id
    items {
        quantity
        product {
            sku
        }
    }
  }
}
QRY;

    private const QUERY_ADD_ITEMS_TO_CART = <<<QRY
mutation {
    addPurchaseOrderItemsToCart(input: {purchase_order_uid: "%s", cart_id: "%s", replace_existing_cart_items: true}) {
        cart {
            id
            items {
                quantity
                product {
                    sku
                }
            }
        }
        user_errors {
            message
            code
        }
    }
}
QRY;

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
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
            CustomerCart::class,
            [
                'customer_id' => '$customer.id$'
            ],
            'quote'
        ),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask'),
        DataFixture(
            AddConfigurableProductToCartFixture::class,
            [
                'cart_id' => '$quote.id$',
                'product_id' => '$configurable_product.id$',
                'child_product_id' => '$product1.id$',
                'qty' => 2.6
            ],
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(
            PurchaseOrderFromQuote::class,
            [
                'cart_id' => '$quote.id$'
            ],
            'purchase_order'
        )
    ]
    public function testAddItems()
    {
        $quoteResponse = $this->graphQlQuery(self::QUERY_CUSTOMER_CART, [], '', $this->getHeaders());
        $this->assertTrue(isset($quoteResponse['customerCart']['id']));
        $maskedQuoteId = $quoteResponse['customerCart']['id'];

        /** @var ProductInterface $configurableProduct */
        $configurableProduct = DataFixtureStorageManager::getStorage()->get('configurable_product');

        $expectedResult = [
            'addPurchaseOrderItemsToCart' => [
                'cart' => [
                    'id' => $maskedQuoteId,
                    'items' => [
                        [
                            'quantity' => 2.6,
                            'product' => [
                                'sku' => $configurableProduct->getSku()
                            ]
                        ],
                    ]
                ],
                'user_errors' => []
            ]
        ];

        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        /** @var Uid $uid */
        $uid = Bootstrap::getObjectManager()->get(Uid::class);

        $this->assertEquals(
            $expectedResult,
            $this->graphQlMutation(
                sprintf(self::QUERY_ADD_ITEMS_TO_CART, $uid->encode($purchaseOrder->getId()), $maskedQuoteId),
                [],
                '',
                $this->getHeaders()
            )
        );
    }

    /**
     * @return string[]
     * @throws AuthenticationException|LocalizedException
     */
    private function getHeaders(): array
    {
        /** @var CustomerInterface $customer */
        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        return Bootstrap::getObjectManager()->get(GetCustomerAuthenticationHeader::class)
            ->execute($customer->getEmail());
    }
}
