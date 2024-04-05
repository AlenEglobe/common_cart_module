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
 * Test company users resolver
 */
class CompanyUsersTest extends GraphQlAbstract
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
    public function testCompanyUsers(): void
    {
        $expected = [
            'users' => [
                'items' => [
                    [
                        'addresses' => [
                            [
                                'city' => 'City Name',
                                'company' => null,
                                'country_code' => 'US',
                                'default_billing' => false,
                                'default_shipping' => false,
                                'extension_attributes' => null,
                                'fax' => null,
                                'firstname' => 'John',
                                'lastname' => 'Doe',
                                'middlename' => null,
                                'postcode' => '7777',
                                'prefix' => null,
                                'region' => [
                                    'region' => 'Arizona',
                                    'region_code' => 'AZ',
                                    'region_id' => 4,
                                ],
                                'region_id' => 4,
                                'street' => [
                                    0 => 'Line 1 Street',
                                    1 => 'Line 2',
                                ],
                                'suffix' => null,
                                'telephone' => '123123123',
                                'vat_id' => null,
                            ],
                        ],
                        'date_of_birth' => null,
                        'default_billing' => null,
                        'default_shipping' => null,
                        'email' => 'john.doe@example.com',
                        'firstname' => 'John',
                        'gender' => null,
                        'is_subscribed' => false,
                        'lastname' => 'Doe',
                        'middlename' => null,
                        'prefix' => null,
                        'suffix' => null,
                        'taxvat' => null,
                    ], [
                        'addresses' => [],
                        'date_of_birth' => null,
                        'default_billing' => null,
                        'default_shipping' => null,
                        'email' => 'veronica.costello@example.com',
                        'firstname' => 'Veronica',
                        'gender' => null,
                        'is_subscribed' => false,
                        'lastname' => 'Costello',
                        'middlename' => null,
                        'prefix' => null,
                        'suffix' => null,
                        'taxvat' => null,
                    ], [
                        'addresses' => [],
                        'date_of_birth' => null,
                        'default_billing' => null,
                        'default_shipping' => null,
                        'email' => 'alex.smith@example.com',
                        'firstname' => 'Alex',
                        'gender' => null,
                        'is_subscribed' => false,
                        'lastname' => 'Smith',
                        'middlename' => null,
                        'prefix' => null,
                        'suffix' => null,
                        'taxvat' => null,
                    ],
                ],
                'total_count' => 3,
                'page_info' => [
                    'page_size' => 10,
                    'current_page' => 1
                ]
            ],
        ];

        $response = $this->executeQuery();
        self::assertSame($response['company'], $expected);
    }

    /**
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery()
    {
        $query = <<<QUERY
{
  company {
    users (filter: {status: ACTIVE}, pageSize:10, currentPage:1) {
      items {
        addresses {
          city
          company
          country_code
          default_billing
          default_shipping
          extension_attributes {
            attribute_code
            value
          }
          fax
          firstname
          lastname
          middlename
          postcode
          prefix
          region {
            region
            region_code
            region_id
          }
          region_id
          street
          suffix
          telephone
          vat_id
        }
        date_of_birth
        default_billing
        default_shipping
        email
        firstname
        gender
        is_subscribed
        lastname
        middlename
        prefix
        suffix
        taxvat
      }
      total_count
      page_info {
        page_size
        current_page
      }
    }
  }
}

QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute('john.doe@example.com', 'password')
        );
    }
}
