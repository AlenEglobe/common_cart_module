<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

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
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
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
 * Test delete purchase order approval rules
 */
#[
    DataFixture(Customer::class, as: 'company_admin'),
    DataFixture(Customer::class, as: 'company_buyer'),
    DataFixture(Customer::class, as: 'not_company_user'),
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
class DeleteRuleTest extends GraphQlAbstract
{
    private const QUERY = <<<QRY
mutation {
  deletePurchaseOrderApprovalRule(input: {approval_rule_uids: [%s]}) {
    errors {
      message
      type
    }
  }
}
QRY;

    private const WRONG_QUERY = <<<QRY
mutation {
  deletePurchaseOrderApprovalRule(input: {approval_rule_uids: []}) {
    errors {
      message
      type
    }
  }
}
QRY;

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
        $this->scopeConfig = $objectManager->get(ApiMutableScopeConfig::class);
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

    /**
     * Set up the configuration for specific scneraios
     *
     * @return void
     */
    private function enableConfig()
    {
        $this->scopeConfig->setValue('btob/website_configuration/company_active', '1');
        $this->scopeConfig->setValue('btob/website_configuration/purchaseorder_enabled', '1');
    }

    /**
     * Disable configuration for specific scenarios
     *
     * @return void
     */
    private function disableConfig()
    {
        $this->scopeConfig->setValue('btob/website_configuration/company_active', '0');
        $this->scopeConfig->setValue('btob/website_configuration/purchaseorder_enabled', '0');
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToDeleteRuleWhenCompanyFeatureNotAvailable()
    {
        $this->disableConfig();

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Company feature is not available.");
        $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray([$rule->getId()]))),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testAttemptToDeleteRuleByUnauthenticatedUser()
    {
        $this->enableConfig();

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current customer isn't authorized.");
        $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray([$rule->getId()]))),
            [],
            ''
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToDeleteRuleWhenCustomerIsNotCompanyUser()
    {
        $this->enableConfig();

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Customer is not a company user.");
        $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray([$rule->getId()]))),
            [],
            '',
            $this->getCustomerHeaders->execute('not_company_user')
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToDeleteRuleWhenCustomerIsNotAuthorized()
    {
        $this->enableConfig();

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("You do not have authorization to perform this action.");
        $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray([$rule->getId()]))),
            [],
            '',
            $this->getCustomerHeaders->execute('company_buyer')
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToDeleteRuleWhenMissingApprovalRuleUids()
    {
        $this->enableConfig();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Required parameter "approval_rule_uids" is missing.');
        $this->graphQlMutation(
            self::WRONG_QUERY,
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToDeleteNonExistingApprovalRule()
    {
        $this->enableConfig();
        $nonExistingApprovalRuleUid = "90000001";
        $expectedMessage = sprintf('Rule with id "%s" does not exist.', $nonExistingApprovalRuleUid);
        $expectedResult = [
            "deletePurchaseOrderApprovalRule" => [
                "errors" => [
                    [
                        "message" => $expectedMessage,
                        "type" => "NOT_FOUND"
                    ]
                ]
            ]
        ];

        $ids = $this->encoder->convertToString($this->encoder->encodeArray([$nonExistingApprovalRuleUid]));
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
    public function testDeleteRule()
    {
        $this->enableConfig();

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $expectedResult = [
            "deletePurchaseOrderApprovalRule" => [
                "errors" => []
            ]
        ];

        $response = $this->graphQlMutation(
            sprintf(self::QUERY, $this->encoder->convertToString($this->encoder->encodeArray([$rule->getId()]))),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );

        $this->assertEquals(
            $expectedResult,
            $response
        );
    }
}
