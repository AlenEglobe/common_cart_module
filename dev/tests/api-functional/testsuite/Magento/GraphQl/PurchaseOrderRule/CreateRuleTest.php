<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;

#[
    Config('btob/website_configuration/company_active', 1),
    Config('btob/website_configuration/purchaseorder_enabled', 1),
    DataFixture(Customer::class, as: 'company_admin'),
    DataFixture(Customer::class, as: 'company_buyer'),
    DataFixture(User::class, as: 'user'),
    DataFixture(
        Company::class,
        [
            'super_user_id' => '$company_admin.entity_id$'
        ],
        'company'
    ),

    DataFixture(
        Role::class,
        [
            'company_id' => '$company.entity_id$',
            'role_name' => 'Company Administrator'
        ],
        'role_administrator'
    ),
    DataFixture(
        Role::class,
        [
            'company_id' => '$company.entity_id$',
            'role_name' => 'Purchaser\'s Manager'
        ],
        'role_purchase_manager'
    )
]
class CreateRuleTest extends GraphQlAbstract
{
    private const CUSTOMER_PASSWORD = 'password';

    private const QUERY = <<<QUERY
    mutation {
        createPurchaseOrderApprovalRule(input: {
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
        }) {
            name
            status
           }
      }
    QUERY;

    private const QUERY_NO_ANSWER = <<<QUERY
    mutation {
        createPurchaseOrderApprovalRule(input: {
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
        }) {
              name
           }
      }
    QUERY;

    private const QUERY_NON_EXISTING_APPLIES_TO = <<<QUERY
    mutation {
        createPurchaseOrderApprovalRule(input: {
            name: "%s"
            description: "%s"
            applies_to: ["%s"]
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
        }) {
              name
           }
      }
    QUERY;

    private const QUERY_MISSING_RULE_CONDITION_PARAMS = <<<QUERY
    mutation {
        createPurchaseOrderApprovalRule(input: {
            name: "%s"
            description: "%s"
            applies_to: []
            status: %s
            condition: {
              attribute: %s
              operator: %s
            }
            approvers: ["%s"]
        }) {
              name
           }
      }
    QUERY;

    private const RULE_ARGS = [
        [
            'name' => 'Rule 001',
            'description' => 'Rule 001 description',
            'status' => 'ENABLED',
            'condition_attribute' => 'SHIPPING_INCL_TAX',
            'condition_operators' => 'MORE_THAN',
            'amount_value' => '50.00'
        ],
        [
            'name' => 'Rule 001 Disabled',
            'description' => 'Rule 001 description',
            'status' => 'DISABLED',
            'condition_attribute' => 'SHIPPING_INCL_TAX',
            'condition_operators' => 'MORE_THAN',
            'amount_value' => '50.00'
        ],
        [
            'name' => 'Rule 002',
            'description' => 'Rule 002 description',
            'status' => 'ENABLED',
            'condition_attribute' => 'GRAND_TOTAL',
            'condition_operators' => 'MORE_THAN_OR_EQUAL_TO',
            'amount_value' => '100.00'
        ],
        [
            'name' => 'Rule 003',
            'description' => 'Rule 003 description',
            'status' => 'ENABLED',
            'condition_attribute' => 'GRAND_TOTAL',
            'condition_operators' => 'MORE_THAN',
        ]
    ];

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    /**
     * @var Uid
     */
    private $uid;

    /**
     * Set up the objects used across all scenarios
     *
     * @return void
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
        $this->uid = $objectManager->get(Uid::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testCreateRuleWithAdminApprovalRequired()
    {
        $role = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY,
                CreateRuleTest::RULE_ARGS[0]['name'],
                CreateRuleTest::RULE_ARGS[0]['description'],
                CreateRuleTest::RULE_ARGS[0]['status'],
                CreateRuleTest::RULE_ARGS[0]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[0]['condition_operators'],
                CreateRuleTest::RULE_ARGS[0]['amount_value'],
                $this->uid->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
        $expectedResult = [
            'createPurchaseOrderApprovalRule' => [
                'name' => 'Rule 001',
                'status' => 'ENABLED',
            ]
        ];

        $this->assertEquals($expectedResult, $response);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testCreateRuleWithAdminAndManagerApproval()
    {
        $roleAdm = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $roleMng = DataFixtureStorageManager::getStorage()->get('role_purchase_manager');
        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY,
                CreateRuleTest::RULE_ARGS[0]['name'],
                CreateRuleTest::RULE_ARGS[0]['description'],
                CreateRuleTest::RULE_ARGS[0]['status'],
                CreateRuleTest::RULE_ARGS[0]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[0]['condition_operators'],
                CreateRuleTest::RULE_ARGS[0]['amount_value'],
                implode(
                    '","',
                    array_map(
                        function ($id) {
                            return $this->uid->encode($id);
                        },
                        [$roleAdm->getRoleId(), $roleMng->getRoleId()]
                    )
                )
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
        $expectedResult = [
            'createPurchaseOrderApprovalRule' => [
                'name' => 'Rule 001',
                'status' => 'ENABLED',
            ]
        ];

        $this->assertEquals($expectedResult, $response);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testCreateDisabledRuleWithAdminApprovalRequired()
    {
        $role = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY,
                CreateRuleTest::RULE_ARGS[1]['name'],
                CreateRuleTest::RULE_ARGS[1]['description'],
                CreateRuleTest::RULE_ARGS[1]['status'],
                CreateRuleTest::RULE_ARGS[1]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[1]['condition_operators'],
                CreateRuleTest::RULE_ARGS[1]['amount_value'],
                $this->uid->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
        $expectedResult = [
            "createPurchaseOrderApprovalRule" => [
                'name' => 'Rule 001 Disabled',
                    'status' => 'DISABLED'
            ]
        ];

        $this->assertEquals($expectedResult, $response);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testCreateGrandTotalRule()
    {
        $role = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $response = $this->graphQlMutation(
            sprintf(
                self::QUERY,
                CreateRuleTest::RULE_ARGS[2]['name'],
                CreateRuleTest::RULE_ARGS[2]['description'],
                CreateRuleTest::RULE_ARGS[2]['status'],
                CreateRuleTest::RULE_ARGS[2]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[2]['condition_operators'],
                CreateRuleTest::RULE_ARGS[2]['amount_value'],
                $this->uid->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
        $expectedResult = [
            'createPurchaseOrderApprovalRule' => [
                'name' => 'Rule 002',
                'status' => 'ENABLED'
            ]
        ];

        $this->assertEquals($expectedResult, $response);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testAttemptWithNonExistingApproverRole()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('The company role with ID "999999999" does not exist.');

        $this->graphQlMutation(
            sprintf(
                self::QUERY_NO_ANSWER,
                CreateRuleTest::RULE_ARGS[2]['name'],
                CreateRuleTest::RULE_ARGS[2]['description'],
                CreateRuleTest::RULE_ARGS[2]['status'],
                CreateRuleTest::RULE_ARGS[2]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[2]['condition_operators'],
                CreateRuleTest::RULE_ARGS[2]['amount_value'],
                $this->uid->encode('999999999')
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testAttemptWithNonExistingAppliesToRole()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('The company role with ID "999999999999" does not exist.');

        $role = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $this->graphQlMutation(
            sprintf(
                self::QUERY_NON_EXISTING_APPLIES_TO,
                CreateRuleTest::RULE_ARGS[2]['name'],
                CreateRuleTest::RULE_ARGS[2]['description'],
                $this->uid->encode("999999999999"),
                CreateRuleTest::RULE_ARGS[2]['status'],
                CreateRuleTest::RULE_ARGS[2]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[2]['condition_operators'],
                CreateRuleTest::RULE_ARGS[2]['amount_value'],
                $this->uid->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testAttemptWithMissingRuleConditionCriteria()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage("Required data is missing from a rule condition.");

        $role = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $this->graphQlMutation(
            sprintf(
                self::QUERY_MISSING_RULE_CONDITION_PARAMS,
                CreateRuleTest::RULE_ARGS[3]['name'],
                CreateRuleTest::RULE_ARGS[3]['description'],
                CreateRuleTest::RULE_ARGS[3]['status'],
                CreateRuleTest::RULE_ARGS[3]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[3]['condition_operators'],
                $this->uid->encode($role->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1)
    ]
    public function testAttemptToCreateRuleWithExistingName()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage("This rule name already exists. Enter a unique rule name.");

        $roleAdm = DataFixtureStorageManager::getStorage()->get('role_administrator');
        $this->graphQlMutation(
            sprintf(
                self::QUERY_NO_ANSWER,
                CreateRuleTest::RULE_ARGS[2]['name'],
                CreateRuleTest::RULE_ARGS[2]['description'],
                CreateRuleTest::RULE_ARGS[2]['status'],
                CreateRuleTest::RULE_ARGS[2]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[2]['condition_operators'],
                CreateRuleTest::RULE_ARGS[2]['amount_value'],
                $this->uid->encode($roleAdm->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );

        $this->graphQlMutation(
            sprintf(
                self::QUERY_NO_ANSWER,
                CreateRuleTest::RULE_ARGS[2]['name'],
                CreateRuleTest::RULE_ARGS[2]['description'],
                CreateRuleTest::RULE_ARGS[2]['status'],
                CreateRuleTest::RULE_ARGS[2]['condition_attribute'],
                CreateRuleTest::RULE_ARGS[2]['condition_operators'],
                CreateRuleTest::RULE_ARGS[2]['amount_value'],
                $this->uid->encode($roleAdm->getRoleId())
            ),
            [],
            '',
            $this->getCustomerHeaders->execute('company_admin')
        );
    }
}
