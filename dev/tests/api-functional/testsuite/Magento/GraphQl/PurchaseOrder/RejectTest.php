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
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderManagement;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\App\ApiMutableScopeConfig;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test reject purchase orders
 */
#[
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
    DataFixture(PurchaseOrderFromQuote::class, ['cart_id' => '$quote.id$'], 'purchase_order')
]
class RejectTest extends GraphQlAbstract
{
    private const QUERY = <<<QRY
mutation {
  rejectPurchaseOrders(input: {purchase_order_uids: [%s]}) {
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

    /**
     * @var PurchaseOrderManagement
     */
    private $purchaseOrderManagement;

    /**
     * @var ApiMutableScopeConfig
     */
    private $scopeConfig;

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
        $this->purchaseOrderManagement = $objectManager->get(PurchaseOrderManagement::class);
        $this->scopeConfig = $objectManager->get(ApiMutableScopeConfig::class);
        $this->setupConfig();
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

    /**
     * Set up the configuration required for all the scenarios
     *
     * @return void
     */
    private function setupConfig()
    {
        $this->scopeConfig->setValue('btob/website_configuration/company_active', '1');
        $this->scopeConfig->setValue('btob/website_configuration/purchaseorder_enabled', '1');
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AuthenticationException
     * @throws GraphQlInputException
     */
    public function testRejectPendingPurchaseOrders()
    {
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        $expectedResult = [
            "rejectPurchaseOrders" => [
                "purchase_orders" => [
                    [
                        "status" => "REJECTED"
                    ]
                ],
                "errors" => []
            ]
        ];

        $ids = $this->encoder->convertToString($this->encoder->encodeArray([$purchaseOrder->getId()]));
        $response = $this->graphQlMutation(
            sprintf(self::QUERY, $ids),
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
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AuthenticationException
     * @throws GraphQlInputException
     */
    public function testAttemptToRejectApprovedPurchaseOrders()
    {
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');
        $this->purchaseOrderManagement->approvePurchaseOrder($purchaseOrder);

        $expectedMessage = sprintf(
            "Action 'reject' is not allowed for purchase order %s.",
            $purchaseOrder->getIncrementId()
        );
        $expectedResult = [
            "rejectPurchaseOrders" => [
                "purchase_orders" => [],
                "errors" => [
                    [
                        "message" => $expectedMessage,
                        "type" => "OPERATION_NOT_APPLICABLE"
                    ]
                ]
            ]
        ];

        $ids = $this->encoder->convertToString($this->encoder->encodeArray([$purchaseOrder->getId()]));
        $response = $this->graphQlMutation(
            sprintf(self::QUERY, $ids),
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
    public function testAttemptToRejectNonExistingPurchaseOrders()
    {
        $nonExistingPurchaseOrdersIds = ["90000001", "90000002"];

        $expectedMessage = "Action 'reject' - purchase order with requested ID=%s not found";
        foreach ($nonExistingPurchaseOrdersIds as $nonExistingPurchaseOrdersId) {
            $expectedErrors[] = [
                "message" => sprintf($expectedMessage, $this->encoder->encode($nonExistingPurchaseOrdersId)),
                "type" => "NOT_FOUND"
            ];
        }

        $expectedResult = [
            "rejectPurchaseOrders" => [
                "purchase_orders" => [],
                "errors" => $expectedErrors
            ]
        ];

        $ids = $this->encoder->convertToString($this->encoder->encodeArray($nonExistingPurchaseOrdersIds));
        $response = $this->graphQlMutation(
            sprintf(self::QUERY, $ids),
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
     * @throws Exception
     */
    public function testAttemptToRejectUnauthenticatedUser()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current customer isn't authorized.");
        $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray(["90000001"]))),
            [],
            ''
        );
    }
}
