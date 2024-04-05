<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderApprove;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderPlaceOrder;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test placed purchase order query
 */
class PlacedPurchaseOrderTest extends GraphQlAbstract
{

    private const QUERY_ALL_DIRECT_FIELDS = <<<QRY
{
    customer {
        purchase_order(uid: "%s") {
            status
            available_actions
            order {
                total {
                    grand_total {
                        value
                    }
                }
            }
        }
    }
}
QRY;

    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

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
        DataFixture(Product::class, as: 'product'),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote.id$',
                'product_id' => '$product.id$'
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
        )
    ]
    /**
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testOrder()
    {
        /** @var OrderInterface $order */
        $order = DataFixtureStorageManager::getStorage()->get('order');
        $expectedResult = [
            'customer' => [
                'purchase_order' => [
                    'status' => strtoupper(PurchaseOrderInterface::STATUS_ORDER_PLACED),
                    'available_actions' => [],
                    'order' => [
                        'total' => [
                            'grand_total' => [
                                'value' => $order->getGrandTotal()
                            ]
                        ]
                    ]
                ]
            ]
        ];
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');
        $this->encoder = new Encoder();
        $this->getCustomerHeaders = new GetCustomerHeaders();
        $this->assertEquals(
            $expectedResult,
            $this->graphQlQuery(
                sprintf(self::QUERY_ALL_DIRECT_FIELDS, $this->encoder->encode((string) $purchaseOrder->getId())),
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }
}
