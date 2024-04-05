<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Exception;
use Magento\Company\Api\Data\RoleInterface;
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
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test update purchase order approval rules
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
    )
]
class UpdateRuleTest extends GraphQlAbstract
{
    private const QUERY = <<<QRY
mutation {
  updatePurchaseOrderApprovalRule(input: {
        uid: "%s",
        name: "%s"
        description: "%s"
        applies_to: []
        status: %s
        condition: {
          attribute: %s
          operator: %s
          amount: {
            value: %s
            currency: EUR
          }
        }
        approvers: ["%s"]
    })
    {
        name
        status
    }
}
QRY;

    private const QUERY_JUST_CHANGE_NAME = <<<QRY
mutation {
  updatePurchaseOrderApprovalRule(input: {
        uid: "%s",
        name: "%s"
    })
    {
        name
        status
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

    #[
        Config('btob/website_configuration/company_active', 0),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToUpdateRuleWhenCompanyFeatureNotAvailable()
    {
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Company feature is not available.");
        $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($rule->getId()),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws LocalizedException
     */
    public function testAttemptToUpdateRuleByUnauthenticatedUser()
    {
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The current customer isn't authorized.");
        $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($rule->getId()),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            ''
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToUpdateRuleWhenCustomerIsNotCompanyUser()
    {
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Customer is not a company user.");
        $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($rule->getId()),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('not_company_user')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToUpdateRuleWhenCustomerIsNotAuthorized()
    {
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');

        $this->expectExceptionMessage("You do not have authorization to perform this action.");

        $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($rule->getId()),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_buyer')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testAttemptToUpdateNonExistingApprovalRule()
    {
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        $nonExistingApprovalRuleUid = "90000001";

        $expectedMessage = sprintf('Rule with id "%s" does not exist.', $nonExistingApprovalRuleUid);
        $this->expectExceptionMessage($expectedMessage);

        $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($nonExistingApprovalRuleUid),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
    ]
    /**
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function testUpdateRule()
    {
        /** @var RuleInterface $purchaseOrder */
        $rule = DataFixtureStorageManager::getStorage()->get('rule');
        /** @var RoleInterface $role */
        $role = DataFixtureStorageManager::getStorage()->get('approver');

        $expectedResult = [
            'updatePurchaseOrderApprovalRule' => [
                'name' => 'Modified name',
                'status' => 'DISABLED'
            ]
        ];

        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY,
                $this->encoder->encode($rule->getId()),
                'Modified name',
                'Modified description',
                'DISABLED',
                'SHIPPING_INCL_TAX',
                'MORE_THAN',
                '50.00',
                $this->encoder->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );

        $this->assertEquals(
            $expectedResult,
            $response
        );

        $expectedResult = [
            'updatePurchaseOrderApprovalRule' => [
                'name' => 'Name modified once again',
                'status' => 'DISABLED'
            ]
        ];

        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY_JUST_CHANGE_NAME,
                $this->encoder->encode($rule->getId()),
                'Name modified once again',
            ),
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
