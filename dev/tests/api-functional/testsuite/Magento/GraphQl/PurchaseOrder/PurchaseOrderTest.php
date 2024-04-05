<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Exception;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Api\Data\CommentInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderComment;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test purchase order query
 */
class PurchaseOrderTest extends GraphQlAbstract
{

    private const QUERY = <<<QRY
{
    customer {
        purchase_order(uid: "%s") {
            uid
            number
            order {
                id
            }
            quote {
                total_quantity
            }
            created_at
            updated_at
            created_by {
                email
                firstname
                lastname
            }
            status
            comments {
                text
                author {
                    email
                }
            }
            history_log {
                activity
                message
            }
            available_actions
        }
    }
}
QRY;

    private const SIMPLE_QUERY = <<<QRY
{
    customer {
        purchase_order(uid: "%s") {
            uid
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

    /**
     * @return void
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

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
            PurchaseOrderComment::class,
            [
                'purchase_order_id' => '$purchase_order.entity_id$',
                'creator_id' => '$customer.id$',
            ],
            'comment'
        )
    ]
    public function testAllDirectFields()
    {
        $purchaseOrder = Bootstrap::getObjectManager()->get(PurchaseOrderRepositoryInterface::class)
            ->getById(DataFixtureStorageManager::getStorage()->get('purchase_order')->getEntityId());

        $this->assertEquals(
            $this->getExpectedResult(
                $purchaseOrder,
                DataFixtureStorageManager::getStorage()->get('quote'),
                DataFixtureStorageManager::getStorage()->get('customer'),
                DataFixtureStorageManager::getStorage()->get('comment'),
            ),
            $this->graphQlQuery(
                sprintf(self::QUERY, $this->encoder->encode($purchaseOrder->getId())),
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }

    private function getExpectedResult(
        PurchaseOrderInterface $purchaseOrder,
        CartInterface $quote,
        \Magento\Customer\Model\Customer $customer,
        CommentInterface $comment
    ): array {
        $expectedResult = [
            'customer' => [
                'purchase_order' => [
                    'uid' => $this->encoder->encode((string)$purchaseOrder->getId()),
                    'number' => $purchaseOrder->getIncrementId(),
                    'order' => null,
                    'quote' => [
                        'total_quantity' => $quote->getItemsCount()
                    ],
                    'created_at' => $purchaseOrder->getCreatedAt(),
                    'updated_at' => $purchaseOrder->getUpdatedAt(),
                    'created_by' => [
                        'email' => $customer->getEmail(),
                        'firstname' => $customer->getFirstname(),
                        'lastname' => $customer->getLastname(),
                    ],
                    'status' => strtoupper(PurchaseOrderInterface::STATUS_PENDING),
                    'available_actions' => [
                        'CANCEL',
                        'VALIDATE'
                    ],
                    'comments' => [
                        [
                            'text' => $comment->getComment(),
                            'author' => [
                                'email' => $customer->getEmail()
                            ]
                        ]
                    ],
                    'history_log' => [
                        [
                            'activity' => 'submit',
                            'message' => 'Purchase Order #' . $purchaseOrder->getIncrementId()
                                . ' was Submitted By ' . $customer->getName()
                        ]
                    ],
                ]
            ]
        ];

        return $expectedResult;
    }

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
        )
    ]
    public function testPurchaseWrongOrderId()
    {
        $expectedMessage = sprintf(
            'GraphQL response contains errors: No such entity with entity_id = %s',
            $this->encoder->encode("90000001")
        );
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->graphQlQuery(
            sprintf(self::SIMPLE_QUERY, $this->encoder->encode("90000001")),
            [],
            '',
            $this->getCustomerHeaders->execute()
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testPurchaseOrderNoAuthentication()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("GraphQL response contains errors: The current customer isn't authorized.");

        $this->graphQlQuery(sprintf(self::SIMPLE_QUERY, $this->encoder->encode("90000001")), [], '');
    }
}
