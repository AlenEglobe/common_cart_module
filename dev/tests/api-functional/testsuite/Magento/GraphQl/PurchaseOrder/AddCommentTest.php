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
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
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
 * Test add comment to purchase order
 */
#[
    DataFixture(Customer::class, as: 'company_admin'),
    DataFixture(Customer::class, as: 'company_buyer'),
    DataFixture(Customer::class, as: 'outsider'),
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
    DataFixture(PurchaseOrderFromQuote::class, ['cart_id' => '$quote.id$'], 'purchase_order')
]
class AddCommentTest extends GraphQlAbstract
{
    private const QUERY = <<<QRY
mutation {
    addPurchaseOrderComment(input: {purchase_order_uid: "%s", comment: "Lorem ipsum dolor sit amet"}) {
        comment {
            text
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
     * Set up the objects used across all scenarios
     *
     * @return void
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    public function testAddComment()
    {
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        $expectedResult = [
            "addPurchaseOrderComment" => [
                "comment" => [
                    "text" => "Lorem ipsum dolor sit amet"
                ],
            ]
        ];

        $id = $this->encoder->encode($purchaseOrder->getId());
        $response = $this->graphQlMutation(
            sprintf(self::QUERY, $id),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );

        $this->assertEquals(
            $expectedResult,
            $response
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    public function testAddCommentNotCompanyUser()
    {
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        $id = $this->encoder->encode($purchaseOrder->getId());

        $this->expectExceptionMessage('Customer is not a company user.');
        $this->graphQlMutation(
            sprintf(self::QUERY, $id),
            [],
            '',
            $this->getCustomerHeaders->execute('outsider')
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    public function testAddCommentNotExistingPurchaseOrder()
    {
        $this->expectExceptionMessage('Purchase order with requested ID=abcd not found.');

        $this->graphQlMutation(
            sprintf(self::QUERY, 'abcd'),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }
}
