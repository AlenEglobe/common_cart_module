<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company user resolver
 */
class CompanyUserTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Uid
     */
    private $idEncoder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->idEncoder = $objectManager->get(Uid::class);
    }

    /**
     * Test company user
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyUser(): void
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $expected = [
            'user' => [
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
            ],
        ];

        $response = $this->executeQuery((string)$customer->getId(), 'john.doe@example.com', 'password');
        self::assertSame($response['company'], $expected);
    }

    /**
     * Test for user from another company
     *
     * @magentoApiDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testOtherCompanyUser(): void
    {
        $customerEmail ='admin@magento.com';
        $requestedCustomer = $this->customerRepository->get('Company_Admin_Under_Carly@example.com');
        $response = $this->executeQuery((string)$requestedCustomer->getId(), $customerEmail, 'password');
        self::assertNull($response['company']['user']);
    }

    /**
     * @param string $customerId
     * @param string $email
     * @param string $password
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery(string $customerId, string $email, string $password)
    {
        $customerId = $this->idEncoder->encode($customerId);

        $query = <<<QUERY
{
  company {
    user (id: "{$customerId}") {
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
  }
}
QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute($email, $password)
        );
    }
}
