<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query\Resolver;

use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company acl resources    resolver
 */
class CompanyAclResourcesTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
    }

    /**
     * Test company ACL resources
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyAclResources(): void
    {
        $query = <<<QUERY
{
  company {
    acl_resources {
      children {
        id
        sort_order
        text
        children {
          id
          sort_order
          text
        }
      }
      id
      sort_order
      text
    }
  }
}
QUERY;

        $response = $this->executeQuery($query);
        $this->validateAclResources($response['company']['acl_resources']);
    }

    /**
     * Validate ACL resources
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @param array $actualAclResources
     */
    private function validateAclResources(array $actualAclResources): void
    {
        $expectedAclResources = [
            [
                "children" => [
                    [
                        "id" => "Magento_Sales::all",
                        "sort_order" => 10,
                        "text" => "Sales",
                        "children" => [
                            [
                                "id" => "Magento_Sales::place_order",
                                "sort_order" => 20,
                                "text" => "Allow Checkout"
                            ],
                            [
                                "id" => "Magento_Sales::view_orders",
                                "sort_order" => 40,
                                "text" => "View orders"
                            ],
                        ]
                    ],
                    [
                        "id" => "Magento_NegotiableQuote::all",
                        "sort_order" => 20,
                        "text" => "Quotes",
                        "children" => [
                            [
                                "id" => "Magento_NegotiableQuote::view_quotes",
                                "sort_order" => 10,
                                "text" => "View"
                            ],
                        ]
                    ],
                    [
                        "id" => "Magento_PurchaseOrder::all",
                        "sort_order" => 20,
                        "text" => "Order Approvals",
                        "children" => [
                            [
                                "id" => "Magento_PurchaseOrder::view_purchase_orders",
                                "sort_order" => 10,
                                "text" => "View my Purchase Orders"
                            ],
                            [
                                "id" => "Magento_PurchaseOrder::autoapprove_purchase_order",
                                "sort_order" => 40,
                                "text" => "Auto-approve POs created within this role"
                            ],
                            [
                                "id" => "Magento_PurchaseOrderRule::super_approve_purchase_order",
                                "sort_order" => 50,
                                "text" => "Approve Purchase Orders without other approvals"
                            ],
                            [
                                "id" => "Magento_PurchaseOrderRule::view_approval_rules",
                                "sort_order" => 60,
                                "text" => "View Approval Rules"
                            ],
                        ]
                    ],
                    [
                        "id" => "Magento_Company::view",
                        "sort_order" => 100,
                        "text" => "Company Profile",
                        "children" => [
                            [
                                "id" => "Magento_Company::view_account",
                                "sort_order" => 100,
                                "text" => "Account Information (View)"
                            ],
                            [
                                "id" => "Magento_Company::view_address",
                                "sort_order" => 200,
                                "text" => "Legal Address (View)"
                            ],
                            [
                                "id" => "Magento_Company::contacts",
                                "sort_order" => 300,
                                "text" => "Contacts (View)"
                            ],
                            [
                                "id" => "Magento_Company::payment_information",
                                "sort_order" => 400,
                                "text" => "Payment Information (View)"
                            ],
                            [
                                "id" => "Magento_Company::shipping_information",
                                "sort_order" => 450,
                                "text" => "Shipping Information (View)"
                            ],
                        ]
                    ],
                    [
                        "id" => "Magento_Company::user_management",
                        "sort_order" => 200,
                        "text" => "Company User Management",
                        "children" => [
                            [
                                "id" => "Magento_Company::roles_view",
                                "sort_order" => 100,
                                "text" => "View roles and permissions"
                            ],
                            [
                                "id" => "Magento_Company::users_view",
                                "sort_order" => 300,
                                "text" => "View users and teams"
                            ],
                        ]
                    ],
                    [
                        "id" => "Magento_Company::credit",
                        "sort_order" => 500,
                        "text" => "Company Credit",
                        "children" => [
                            [
                                "id" => "Magento_Company::credit_history",
                                "sort_order" => 500,
                                "text" => "View"
                            ],
                        ]
                    ],
                ],
                "id" => "Magento_Company::index",
                "sort_order" => 100,
                "text" => "All"
            ]
        ];

        self::assertEqualsCanonicalizing($expectedAclResources, $actualAclResources);
    }

    /**
     * Execute query
     *
     * @param $query
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery($query)
    {
        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }
}
