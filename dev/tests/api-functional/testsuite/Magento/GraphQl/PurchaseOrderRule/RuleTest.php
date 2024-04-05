<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\PurchaseOrderRule\Api\Data\RuleInterface;
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test covering Customer.purchase_order_approval_rule field
 */
class RuleTest extends GraphQlAbstract
{
    private const CUSTOMER_PASSWORD = 'password';

    private const QUERY = <<<QUERY
{
    customer {
        purchase_order_approval_rule(uid: "%s") {
            uid
            name
            status
            created_by
            applies_to_roles {
                name
            }
            approver_roles {
                name
            }
            condition {
              attribute
              operator
              ... on PurchaseOrderApprovalRuleConditionAmount {
                amount {
                  value
                  currency
                }
              }
              ... on PurchaseOrderApprovalRuleConditionQuantity {
                quantity
              }
            }
        }
    }
}
QUERY;

    /**
     * Retrieve purchase_order_approval_rule_metadata
     *
     * @throws AuthenticationException
     * @throws LocalizedException
     */
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
                'created_by' => '$customer.id$'
            ],
            'rule'
        ),
    ]
    public function testRule()
    {
        $ruleId = DataFixtureStorageManager::getStorage()->get('rule')->getId();

        $response = $this->graphQlQuery(
            sprintf(self::QUERY, Bootstrap::getObjectManager()->get(Uid::class)->encode((string)$ruleId)),
            [],
            '',
            $this->getAuthorizationHeader()
        );
        $this->assertEquals($this->getExpectedResult(), $response);
    }

    /**
     * @return \array[][]
     * @throws LocalizedException
     */
    private function getExpectedResult(): array
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        /** @var RuleInterface $rule */
        $rule = $fixtures->get('rule');
        /** @var CustomerInterface $creator */
        $creator = $fixtures->get('customer');
        /** @var RoleInterface $role */
        $role = $fixtures->get('approver');
        return [
            'customer' => [
                'purchase_order_approval_rule' => [
                    'uid' => Bootstrap::getObjectManager()->get(Uid::class)->encode((string)$rule->getId()),
                    'name' => $rule->getName(),
                    'status' => $rule->isActive() ? 'ENABLED' : 'DISABLED',
                    'created_by' => $creator->getFirstname() . ' ' . $creator->getLastname(),
                    'applies_to_roles' => [],
                    'approver_roles' => [
                        [
                            'name' => 'Purchaser\'s Manager'
                        ],
                        [
                            'name' => 'Company Administrator'
                        ],
                        [
                            'name' => $role->getRoleName()
                        ],
                    ],
                    'condition' => [
                        'attribute' => 'GRAND_TOTAL',
                        'operator' => 'MORE_THAN',
                        'amount' => [
                            'value' => 5,
                            'currency' => 'USD',
                        ],
                    ],
                ]
            ]
        ];
    }

    /**
     * Get authorization header
     *
     * @return string[]
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    private function getAuthorizationHeader(): array
    {
        $token = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class)
            ->createCustomerAccessToken(
                DataFixtureStorageManager::getStorage()->get('customer')->getEmail(),
                self::CUSTOMER_PASSWORD
            );
        return ['Authorization' => 'Bearer ' . $token];
    }
}
