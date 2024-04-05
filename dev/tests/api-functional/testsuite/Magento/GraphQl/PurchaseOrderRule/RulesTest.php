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
 * Test covering Customer.purchase_order_approval_rules field
 */
class RulesTest extends GraphQlAbstract
{
    private const CUSTOMER_PASSWORD = 'password';

    private const QUERY = <<<QUERY
{
    customer {
        purchase_order_approval_rules(currentPage: %s, pageSize: %s) {
            total_count
            page_info {
                page_size
                current_page
                total_pages
            }
            items {
                uid
                name
            }
        }
    }
}
QUERY;

    /**
     * Retrieve purchase_order_approval_rule_metadata
     *
     * @magentoApiDataFixture Magento/Company/_files/company_rollback.php
     * @dataProvider pagesProvider
     * @throws AuthenticationException|LocalizedException
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
            'rule1'
        ),
        DataFixture(
            Rule::class,
            [
                'company_id' => '$company.entity_id$',
                'approver_role_ids' => ['$approver.role_id$'],
                'created_by' => '$customer.id$'
            ],
            'rule2'
        ),
    ]
    public function testRules(int $page, int $pageSize)
    {
        $response = $this->graphQlQuery(
            sprintf(self::QUERY, $page, $pageSize),
            [],
            '',
            $this->getAuthorizationHeader()
        );
        $expectedResult = [
            'customer' => [
                'purchase_order_approval_rules' => [
                    'total_count' => 2,
                    'page_info' => [
                        'page_size' => $pageSize,
                        'current_page' => $page,
                        'total_pages' => (int)ceil(2 / $pageSize)
                    ],
                    'items' => array_slice(
                        array_values(
                            array_map(
                                function (RuleInterface $rule) {
                                    return [
                                        'uid' => Bootstrap::getObjectManager()->get(Uid::class)
                                            ->encode((string)$rule->getId()),
                                        'name' => $rule->getName()
                                    ];
                                },
                                [
                                    DataFixtureStorageManager::getStorage()->get('rule2'),
                                    DataFixtureStorageManager::getStorage()->get('rule1')
                                ]
                            )
                        ),
                        ($page - 1) * $pageSize,
                        $pageSize,
                    )
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $response);
    }

    /**
     * @return int[][]
     */
    public function pagesProvider(): array
    {
        return [
            [1, 20],
            [1, 1],
            [2, 1]
        ];
    }

    /**
     * Get authorization header
     *
     * @return array
     * @throws AuthenticationException|LocalizedException
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
