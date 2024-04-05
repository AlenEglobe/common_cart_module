<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderApprove;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Test\Fixture\PurchaseOrderValidate;
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test placed purchase approval flow
 */
class ApprovalFlowTest extends GraphQlAbstract
{

    private const QUERY_ALL_DIRECT_FIELDS = <<<QRY
{
    customer {
        purchase_order(uid: "%s") {
            approval_flow {
                rule_name
                events {
                    name
                    role
                    status
                    message
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
        DataFixture(PurchaseOrderFromQuote::class, ['cart_id' => '$quote.id$'], 'purchase_order'),
        DataFixture(PurchaseOrderValidate::class, ['purchase_order_id' => '$purchase_order.entity_id$',]),
        DataFixture(
            PurchaseOrderApprove::class,
            [
                'purchase_order_id' => '$purchase_order.entity_id$',
                'customer_id' => '$company_admin.id$',
            ]
        )
    ]
    public function testFlow()
    {
        /** @var RoleInterface $role */
        $role = DataFixtureStorageManager::getStorage()->get('approver');
        /** @var RuleInterface $rule */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');
        $expectedResult = [
            'customer' => [
                'purchase_order' => [
                    'approval_flow' => [
                        [
                            'rule_name' => $rule->getName(),
                            'events' => [
                                [
                                    'name' => 'Unknown Customer',
                                    'role' => $role->getRoleName(),
                                    'status' => 'PENDING',
                                    'message' => 'Pending Approval from ' . $role->getRoleName()
                                ],
                                [
                                    'name' => 'Unknown Customer',
                                    'role' => 'Company Administrator',
                                    'status' => 'PENDING',
                                    'message' => 'Pending Approval from Company Administrator'
                                ],
                                [
                                    'name' => 'Unknown Customer',
                                    'role' => 'Purchaser\'s Manager',
                                    'status' => 'PENDING',
                                    'message' => 'Pending Approval from Purchaser\'s Manager'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');
        $objectManager = Bootstrap::getObjectManager();
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);

        $this->assertEquals(
            $expectedResult,
            $this->graphQlQuery(
                sprintf(self::QUERY_ALL_DIRECT_FIELDS, $this->encoder->encode($purchaseOrder->getId())),
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );
    }
}
