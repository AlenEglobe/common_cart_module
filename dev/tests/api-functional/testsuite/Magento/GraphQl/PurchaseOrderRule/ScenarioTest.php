<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\GraphQl\PurchaseOrder\Encoder;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Verify placing order for a purchase order
 */
class ScenarioTest extends GraphQlAbstract
{
    private const QUERY_IS_ENABLED = <<<QRY
{
    customer {
        purchase_orders_enabled
    }
}
QRY;
    private const QUERY_CART = <<<QRY
{
    customerCart {
        id
    }
}
QRY;
    private const QUERY_PLACE_PURCHASE_ORDER = <<<QRY
mutation {
  placePurchaseOrder(input: {cart_id: "%s"}) {
    purchase_order {
      uid
    }
  }
}
QRY;
    private const QUERY_VALIDATE = <<<QRY
mutation {
  validatePurchaseOrders(input: {purchase_order_uids: ["%s"]}) {
    purchase_orders {
      status
    }
    errors {
      message
      type
    }
  }
}
QRY;
    private const QUERY_APPROVE = <<<QRY
mutation {
    approvePurchaseOrders(input: {purchase_order_uids: ["%s"]}) {
        purchase_orders {
            status
        }
        errors {
            message
            type
        }
    }
}
QRY;
    private const QUERY_PLACE_ORDER = <<<QRY
mutation {
    placeOrderForPurchaseOrder(input: {purchase_order_uid: "%s"}) {
        order {
            number
        }
    }
}
QRY;
    private const QUERY_PURCHASE_ORDER = <<<QRY
{
    customer {
        purchase_order(uid: "%s") {
            order {
                number
            }
            status
        }
    }
}
QRY;

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    public function setUp(): void
    {
        $this->getCustomerHeaders = Bootstrap::getObjectManager()->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(Customer::class, as: 'company_buyer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$company_admin.id$'
            ],
            'company'
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$']),
        DataFixture(
            AssignCustomer::class,
            [
                'company_id' => '$company.entity_id$',
                'customer_id' => '$company_buyer.id$'
            ]
        ),
        DataFixture(
            Role::class,
            [
                'company_id' => '$company.entity_id$'
            ],
            'approver'
        ),
        DataFixture(
            Rule::class,
            [
                'company_id' => '$company.entity_id$',
                'approver_role_ids' => ['$approver.role_id$'],
                'created_by' => '$company_admin.id$'
            ],
            'rule'
        ),
        DataFixture(
            CustomerCart::class,
            [
                'customer_id' => '$company_buyer.id$'
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
    ]
    public function testScenario()
    {
        $this->ensurePurchaseOrdersEnabled();

        $purchaseOrderId = $this->placePurchaseOrder($this->getCartId());

        $this->validatePurchaseOrder($purchaseOrderId);
        $this->approvePurchaseOrder($purchaseOrderId);
        $this->verifyPlacedOrder($this->placeOrder($purchaseOrderId), $purchaseOrderId);
    }

    /**
     * @return void
     * @throws AuthenticationException|LocalizedException
     */
    private function ensurePurchaseOrdersEnabled(): void
    {
        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders_enabled' => true
                ]
            ],
            $this->graphQlQuery(
                self::QUERY_IS_ENABLED,
                [],
                '',
                $this->getCustomerHeaders->execute('company_buyer')
            )
        );
    }

    /**
     * @return string
     * @throws AuthenticationException|LocalizedException
     */
    private function getCartId(): string
    {
        $cartResponse = $this->graphQlQuery(
            self::QUERY_CART,
            [],
            '',
            $this->getCustomerHeaders->execute('company_buyer')
        );

        $this->assertNotEmpty($cartResponse['customerCart']['id']);

        return $cartResponse['customerCart']['id'];
    }

    /**
     * @param string $cartId
     * @return string
     * @throws AuthenticationException|LocalizedException
     */
    private function placePurchaseOrder(string $cartId): string
    {
        $purchaseOrderResponse = $this->graphQlMutation(
            sprintf(self::QUERY_PLACE_PURCHASE_ORDER, $cartId),
            [],
            '',
            $this->getCustomerHeaders->execute('company_buyer')
        );

        $this->assertNotEmpty($purchaseOrderResponse['placePurchaseOrder']['purchase_order']['uid']);

        return $purchaseOrderResponse['placePurchaseOrder']['purchase_order']['uid'];
    }

    /**
     * @param string $id
     * @return void
     * @throws AuthenticationException|LocalizedException
     */
    private function validatePurchaseOrder(string $id): void
    {
        $this->assertEquals(
            [
                'validatePurchaseOrders' => [
                    'purchase_orders' => [
                        [
                            'status' => 'APPROVAL_REQUIRED'
                        ]
                    ],
                    'errors' => []
                ]
            ],
            $this->graphQlMutation(
                sprintf(self::QUERY_VALIDATE, $id),
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );
    }

    /**
     * @param string $id
     * @return void
     * @throws AuthenticationException|LocalizedException
     */
    private function approvePurchaseOrder(string $id): void
    {
        $this->assertEquals(
            [
                'approvePurchaseOrders' => [
                    'purchase_orders' => [
                        [
                            'status' => 'APPROVED'
                        ]
                    ],
                    'errors' => []
                ]
            ],
            $this->graphQlMutation(
                sprintf(self::QUERY_APPROVE, $id),
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );
    }

    /**
     * @param string $id
     * @return string
     * @throws AuthenticationException|LocalizedException
     */
    private function placeOrder(string $id): string
    {
        $placeOrderResult = $this->graphQlMutation(
            sprintf(self::QUERY_PLACE_ORDER, $id),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );

        $this->assertTrue(isset($placeOrderResult['placeOrderForPurchaseOrder']['order']['number']));

        return $placeOrderResult['placeOrderForPurchaseOrder']['order']['number'];
    }

    /**
     * @param string $orderNumber
     * @param string $purchaseOrderId
     * @throws AuthenticationException|LocalizedException
     */
    private function verifyPlacedOrder(string $orderNumber, string $purchaseOrderId): void
    {
        $this->assertEquals(
            [
                'customer' => [
                    'purchase_order' => [
                        'order' => [
                            'number' => $orderNumber
                        ],
                        'status' => 'ORDER_PLACED'
                    ]
                ]
            ],
            $this->graphQlQuery(
                sprintf(self::QUERY_PURCHASE_ORDER, $purchaseOrderId),
                [],
                '',
                $this->getCustomerHeaders->execute('company_buyer')
            )
        );
    }
}
