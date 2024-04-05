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
 * Test company hierarchy resolver
 */
class CompanyStructureTest extends GraphQlAbstract
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
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyStructure(): void
    {
        $expected = [
            "structure" => [
                "items" => [
                    [
                        "entity" => [
                            "__typename" => "Customer",
                            "firstname" => "John",
                            "lastname" => "Doe",
                            "email" => "john.doe@example.com"
                        ]
                    ],
                    [
                        "entity" => [
                            "__typename" => "CompanyTeam",
                            "name" => "Test team",
                            "description" => "Test team description"
                        ]
                    ],
                    [
                        "entity" => [
                            "__typename" => "Customer",
                            "firstname" => "Veronica",
                            "lastname" => "Costello",
                            "email" => "veronica.costello@example.com"
                        ]
                    ],
                    [
                        "entity" => [
                            "__typename" => "Customer",
                            "firstname" => "Alex",
                            "lastname" => "Smith",
                            "email" => "alex.smith@example.com"
                        ]
                    ]
                ]
            ]
        ];

        $query = <<<QUERY
{
  company {
    structure {
      items {
        entity {
          __typename
          ... on Customer {
            firstname
            lastname
            email
          }
          ... on CompanyTeam {
            name
            description
          }
        }
      }
    }
  }
}

QUERY;

        $response = $this->executeQuery($query);
        self::assertSame($response['company'], $expected);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyStructureDepth(): void
    {
        $expected = [
            "structure" => [
                "items" => [
                    [
                        "entity" => [
                            "__typename" => "Customer",
                            "firstname" => "John",
                            "lastname" => "Doe",
                            "email" => "john.doe@example.com"
                        ]
                    ]
                ]
            ]
        ];

        $query = <<<QUERY
{
  company {
    structure (depth: 0) {
      items {
        entity {
          __typename
          ... on Customer {
            firstname
            lastname
            email
          }
          ... on CompanyTeam {
            name
            description
          }
        }
      }
    }
  }
}
QUERY;

        $response = $this->executeQuery($query);
        self::assertSame($response['company'], $expected);
    }

    /**
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
