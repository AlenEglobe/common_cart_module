<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query;

use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company query
 */
class CompanyTest extends GraphQlAbstract
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
    public function testCompanyActive(): void
    {
        $expected = [
            'name' => 'Magento',
            'email' => 'company@example.com',
            'legal_name' => null,
            'vat_tax_id' => null,
            'reseller_id' => null,
            'legal_address' => [
                'city' => 'City',
                'country_code' => 'US',
                'postcode' => 'Postcode',
                'region' => [
                    'region' => 'Alabama',
                    'region_code' => 'AL',
                    'region_id' => 1,
                ],
                'street' => [
                    '123 Street',
                ],
                'telephone' => '5555555555',
            ],
            'company_admin' => [
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
                            'region_id' => 4
                        ],
                        'region_id' => 4,
                        'street' => ['Line 1 Street', 'Line 2'],
                        'suffix' => null,
                        'telephone' => '123123123',
                        'vat_id' => null
                    ]
                ],
            ],
            'sales_representative' => [
                'email' => 'admin@example.com',
                'firstname' => 'firstname',
                'lastname' => 'lastname',
            ],
            'payment_methods' => [
                'companycredit',
                'checkmo'
            ]
        ];

        $response = $this->executeQuery();
        self::assertSame($response['company'], $expected);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 0
     */
    public function testCompanyInActive(): void
    {
        $expectedMessage = 'Company feature is not available.';

        try {
            $this->executeQuery();
            self::fail('Response should contains errors.');
        } catch (ResponseContainsErrorsException $e) {
            $responseData = $e->getResponseData();
            self::assertEquals($expectedMessage, $responseData['errors'][0]['message']);
        }
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
    name
    email
    legal_name
    vat_tax_id
    reseller_id
    legal_address {
      city
      country_code
      postcode
      region {
        region
        region_code
        region_id
      }
      street
      telephone
    }
    company_admin {
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
    }
    sales_representative {
      email
      firstname
      lastname
    }
    payment_methods
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
