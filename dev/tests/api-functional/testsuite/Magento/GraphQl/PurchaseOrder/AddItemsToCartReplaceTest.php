<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\NegotiableQuote\Test\Fixture\QuoteIdMask;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderApprove;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderPlaceOrder;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test adding purchase order items to the shopping cart with replace option
 */
class AddItemsToCartReplaceTest extends GraphQlAbstract
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
        DataFixture(
            CustomerCart::class,
            [
                'customer_id' => '$customer.id$'
            ],
            'quote'
        ),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask'),
        DataFixture(Product::class, ['sku' => 'simple1'], 'product1'),
        DataFixture(Product::class, ['sku' => 'simple2'], 'product2'),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote.id$',
                'product_id' => '$product1.id$',
                'qty' => 2
            ]
        ),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote.id$',
                'product_id' => '$product2.id$'
            ]
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
        ),
        DataFixture(
            PurchaseOrderApprove::class,
            [
                'purchase_order_id' => '$purchase_order.entity_id$',
                'customer_id' => '$customer.id$',
            ]
        ),
        DataFixture(
            PurchaseOrderPlaceOrder::class,
            [
                'purchase_order_id' => '$purchase_order.entity_id$',
                'customer_id' => '$customer.id$',
            ],
            'order'
        ),
        DataFixture(
            CustomerCart::class,
            [
                'customer_id' => '$customer.id$'
            ],
            'quote'
        ),
        DataFixture(Product::class, ['sku' => 'simple3'], as: 'product3'),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote.id$',
                'product_id' => '$product3.id$'
            ]
        ),
    ]
    public function testAddItems()
    {
        $quoteResponse = $this->graphQlQuery(self::QUERY_CUSTOMER_CART, [], '', $this->getHeaders());
        $this->assertTrue(isset($quoteResponse['customerCart']['id']));
        $maskedQuoteId = $quoteResponse['customerCart']['id'];

        $this->assertTrue(isset($quoteResponse['customerCart']['items']));
        $this->assertEquals(1, count($quoteResponse['customerCart']['items']));

        $expectedResult = [
            'addPurchaseOrderItemsToCart' => [
                'cart' => [
                    'id' => $maskedQuoteId,
                    'items' => [
                        [
                            'quantity' => 2,
                            'product' => [
                                'sku' => 'simple1'
                            ]
                        ],
                        [
                            'quantity' => 1,
                            'product' => [
                                'sku' => 'simple2'
                            ]
                        ],
                    ]
                ],
                'user_errors' => []
            ]
        ];

        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');
        $this->assertEquals(2, count($purchaseOrder->getSnapshotQuote()->getAllVisibleItems()));

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
