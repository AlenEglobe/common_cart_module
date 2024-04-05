<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrderRule;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test covering Customer.purchase_order_approval_rule_metadata field
 */
class MetadataTest extends GraphQlAbstract
{
    private const QUERY_APPLIES_TO = <<<QUERY
{
    customer {
        purchase_order_approval_rule_metadata {
              available_applies_to {
                    name
                    users_count
              }
        }
  }
}
QUERY;

    private const QUERY_CURRENCY = <<<QUERY
{
    customer {
        purchase_order_approval_rule_metadata {
              available_condition_currencies {
                    code
                    symbol
              }
        }
  }
}
QUERY;

    private const QUERY_APPROVAL = <<<QUERY
{
    customer {
        purchase_order_approval_rule_metadata {
              available_requires_approval_from {
                    name
                    users_count
              }
        }
  }
}
QUERY;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
    }

    /**
     * Retrieve purchase_order_approval_rule_metadata
     *
     * @magentoApiDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule_one_manager_approved.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture btob/website_configuration/purchaseorder_enabled 1
     * @dataProvider queryProvider
     * @throws \Exception
     */
    public function testMetadata(string $query, array $expectedResponse)
    {
        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getAuthorizationHeader()
        );
        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * Get authorization header
     *
     * @return array
     * @throws AuthenticationException
     */
    private function getAuthorizationHeader(): array
    {
        $token = $this->customerTokenService->createCustomerAccessToken(
            'john.doe@example.com',
            'password'
        );
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @return array
     */
    public function queryProvider(): array
    {
        return [
            [
                self::QUERY_APPLIES_TO,
                [
                    'customer' => [
                        'purchase_order_approval_rule_metadata' => [
                            'available_applies_to' => [
                                [
                                    'name' => 'Default User',
                                    'users_count' => 1
                                ],
                                [
                                    'name' => 'Role 1',
                                    'users_count' => 1
                                ],
                                [
                                    'name' => 'Role 2',
                                    'users_count' => 1
                                ],
                            ]
                        ]
                    ]
                ]
            ],
            [
                self::QUERY_CURRENCY,
                [
                    'customer' => [
                        'purchase_order_approval_rule_metadata' => [
                            'available_condition_currencies' => [
                                [
                                    'code' => 'USD',
                                    'symbol' => '$'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                self::QUERY_APPROVAL,
                [
                    'customer' => [
                        'purchase_order_approval_rule_metadata' => [
                            'available_requires_approval_from' => [
                                [
                                    'name' => 'Company Administrator',
                                    'users_count' => 0
                                ],
                                [
                                    'name' => 'Purchaser\'s Manager',
                                    'users_count' => 0
                                ],
                                [
                                    'name' => 'Default User',
                                    'users_count' => 0
                                ],
                                [
                                    'name' => 'Role 1',
                                    'users_count' => 1
                                ],
                                [
                                    'name' => 'Role 2',
                                    'users_count' => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
