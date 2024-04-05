<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Model\ResourceModel\Company as CompanyResource;
use Magento\Company\Model\Company;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test class for create and update of company account.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CompanyAccountTest extends GraphQlAbstract
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /** @var CompanyResource */
    private $companyResource;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->registry = $this->objectManager->get(Registry::class);
        $this->companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->companyResource = $this->objectManager->get(CompanyResource::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
    }

    /**
     * Test if company feature is activated.
     *
     * @magentoConfigFixture default_store btob/website_configuration/company_active 0
     */
    public function testConfigCompanyActive()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Company is not enabled or registration not allowed.');
        $mutationQuery = <<<MUTATION
mutation {
  createCompany(
    input: {
      company_name: "Company"
      company_email: "email@magento.com"
      legal_name: "Legalname"
      vat_tax_id: "12345"
      reseller_id: "123"
      company_admin:   {
        email: "admin@magento.com"
        firstname: "Company"
        lastname: "Admin"
        gender: 1
        job_title: "Manager"
      }
      legal_address: {
        city: "City"
        country_id: US
        postcode: "12345"
        region: {
            region_id: 35
        }
        street: ["Street  123"]
        telephone: "0123456789"
      }
    }
  ) {
    company {
      id
      email
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutationQuery);
    }

    /**
     * Test if company registration is enabled.
     *
     * @magentoConfigFixture default_store company/general/allow_company_registration 0
     */
    public function testConfigAllowCompanyRegistration()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Company is not enabled or registration not allowed.');
        $mutationQuery = <<<MUTATION
mutation {
  createCompany(
    input: {
      company_name: "Company"
      company_email: "email@magento.com"
      legal_name: "Legalname"
      vat_tax_id: "12345"
      reseller_id: "123"
      company_admin:   {
        email: "admin@magento.com"
        firstname: "Company"
        lastname: "Admin"
        gender: 1
        job_title: "Manager"
      }
      legal_address: {
        city: "City"
        country_id: US
        postcode: "12345"
        region: {
            region_id: 35
        }
        street: ["Street  123"]
        telephone: "0123456789"
      }
    }
  ) {
    company {
      id
      email
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutationQuery);
    }

    /**
     * Test creation of company.
     *
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store company/general/allow_company_registration 1
     */
    public function testCreateCompanyAccount()
    {
        $companyEmail = "company@example.com";
        $legalName = "Legal name";
        $companyName = "Company name";
        $adminEmail = "company_user@example.com";
        $adminName = "Admin";
        $postcode = "12345";
        $city = "Example city";
        $jobTitle = "Manager";
        $mutationQuery = <<<MUTATION
mutation {
  createCompany(
    input: {
      company_name: "{$companyName}"
      company_email: "{$companyEmail}"
      legal_name: "{$legalName}"
      vat_tax_id: "12345"
      reseller_id: "123"
      company_admin:   {
        email: "{$adminEmail}"
        firstname: "{$adminName}"
        lastname: "Company"
        gender: 1
        job_title: "{$jobTitle}"
      }
      legal_address: {
        city: "{$city}"
        country_id: US
        postcode: "{$postcode}"
        region: {
            region_id: 35
        }
        street: ["Street  123"]
        telephone: "0123456789"
      }
    }
  ) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      company_admin {
        email
        firstname
        lastname
        gender
        job_title
      }
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;
        $result = $this->graphQlMutation($mutationQuery);
        $this->assertNotEmpty($result['createCompany']['company']);
        $this->assertNotEmpty($result['createCompany']['company']['id']);
        $this->assertEquals($result['createCompany']['company']['email'], $companyEmail);
        $this->assertEquals($result['createCompany']['company']['legal_name'], $legalName);
        $this->assertEquals($result['createCompany']['company']['name'], $companyName);
        $this->assertEquals($result['createCompany']['company']['company_admin']['email'], $adminEmail);
        $this->assertEquals($result['createCompany']['company']['company_admin']['firstname'], $adminName);
        $this->assertEquals($result['createCompany']['company']['company_admin']['job_title'], $jobTitle);
        $this->assertEquals($result['createCompany']['company']['legal_address']['postcode'], $postcode);
        $this->assertEquals($result['createCompany']['company']['legal_address']['city'], $city);
        $this->assertEquals($result['createCompany']['company']['legal_address']['region']['region_code'], 'MS');

        $this->deleteCompany($companyEmail, $adminEmail);
    }

    /**
     * Test if company account exists.
     *
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store company/general/allow_company_registration 1
     * @magentoDbIsolation disabled
     */
    public function testCreateCompanyAccountExistingEmail()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage(
            'A customer with the same email address already exists in an associated website.'
        );
        $mutationQuery = <<<MUTATION
mutation {
  createCompany(
    input: {
      company_name: "Company"
      company_email: "email@magento.com"
      legal_name: "Legalname"
      vat_tax_id: "12345"
      reseller_id: "123"
      company_admin:   {
        email: "admin@magento.com"
        firstname: "Company"
        lastname: "Admin"
        gender: 1
        job_title: "Manager"
      }
      legal_address: {
        city: "City"
        country_id: US
        postcode: "12345"
        region: {
            region_id: 35
        }
        street: ["Street  123"]
        telephone: "0123456789"
      }
    }
  ) {
    company {
      id
      email
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutationQuery);
    }

    /**
     * Test customer access for updating company.
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store company/general/allow_company_registration 1
     */
    public function testCustomerAccessForUpdateCompany()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage(
            'Customer is not a company user.'
        );
        $mutationQuery = <<<MUTATION
mutation {
  updateCompany(
    input: {
      company_email: "company@example.com",
    }) {
    company {
      id
      email
      name
    }
  }
}
MUTATION;
        $this->graphQlMutation($mutationQuery);
    }

    /**
     * Test for updating of company.
     *
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoDbIsolation disabled
     */
    public function testUpdateCompanyAccount()
    {
        $companyEmail = "company@example.com";
        $companyName = "Company name updated";
        $legalName = "Legal name updated";
        $postcode = "12346";
        $city = "Example city updated";
        $street = "New Street  123";
        $telephone = "0121221211211";
        $adminEmail = "admin@magento.com";
        $this->approveCompany();
        $mutationQuery = <<<MUTATION
mutation {
  updateCompany(
    input: {
      company_name: "{$companyName}",
      company_email: "{$companyEmail}",
      legal_name: "{$legalName}",
      vat_tax_id: "1212111",
      reseller_id: "13311",
      legal_address: {
        city: "{$city}",
        country_id: US,
        postcode: "{$postcode}",
        region: {
          region_code: "MN",
          region_id: 34
        },
        street: ["{$street}"],
        telephone: "0121221211211"
      }
    }) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;
        $result = $this->graphQlMutation(
            $mutationQuery,
            [],
            '',
            $this->getCustomerHeader($adminEmail)
        );
        $this->assertNotEmpty($result['updateCompany']['company']);
        $this->assertNotEmpty($result['updateCompany']['company']['id']);
        $this->assertEquals($result['updateCompany']['company']['email'], $companyEmail);
        $this->assertEquals($result['updateCompany']['company']['legal_name'], $legalName);
        $this->assertEquals($result['updateCompany']['company']['name'], $companyName);
        $this->assertEquals($result['updateCompany']['company']['legal_address']['postcode'], $postcode);
        $this->assertEquals($result['updateCompany']['company']['legal_address']['city'], $city);
        $this->assertEquals($result['updateCompany']['company']['legal_address']['telephone'], $telephone);
        $this->assertEquals($result['updateCompany']['company']['legal_address']['region']['region_code'], 'MN');
    }

    /**
     * Test access of guest customer for updating company.
     *
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateCompanyAsGuest()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Customer is not a company user.');
        $mutationQuery = <<<MUTATION
mutation {
  updateCompany(
    input: {
      company_name: "Company",
      company_email: "email@magento.com",
      legal_name: "Legal name",
      vat_tax_id: "1212111",
      reseller_id: "13311",
      legal_address: {
        city: "City",
        country_id: US,
        postcode: "12345",
        region: {
          region_code: "MN",
          region_id: 34
        },
        street: ["Street 1"],
        telephone: "0121221211211"
      }
    }) {
    company {
      id
      email
    }
  }
}
MUTATION;
        $this->graphQlMutation(
            $mutationQuery,
            [],
            '',
            []
        );
    }

    /**
     * Test access of non company customer for updating company.
     *
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateCompanyAsNonCompanyUser()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Customer is not a company user.');
        $customer = $this->createNonCompanyCustomer($password = 'SomePassword123');

        $mutationQuery = <<<MUTATION
mutation {
  updateCompany(
    input: {
      company_name: "Company",
      company_email: "email@magento.com",
      legal_name: "Legal name",
      vat_tax_id: "1212111",
      reseller_id: "13311",
      legal_address: {
        city: "City",
        country_id: US,
        postcode: "12345",
        region: {
          region_code: "MN",
          region_id: 34
        },
        street: ["Street 1"],
        telephone: "0121221211211"
      }
    }) {
    company {
      id
      email
    }
  }
}
MUTATION;
        $this->graphQlMutation(
            $mutationQuery,
            [],
            '',
            ['Authorization' => 'Bearer ' . $this->authCustomer($customer['email'], $password)]
        );
    }

    /**
     * Get http header with customer access token.
     *
     * @param string $email
     * @return string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AuthenticationException
     * @throws InputException
     */
    private function getCustomerHeader($email)
    {
        return $this->getCustomerAuthenticationHeader->execute($email, 'password');
    }

    /**
     * Update company status before login.
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function approveCompany()
    {
        $company = $this->objectManager->get(Company::class);
        $this->companyResource->load($company, 'company@example.com', 'company_email');
        $company->setStatus(CompanyInterface::STATUS_APPROVED);
        $this->companyResource->save($company);
    }

    /**
     * Clear company data.
     *
     * @param string $companyEmail
     * @param string $customerEmail
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    private function deleteCompany($companyEmail, $customerEmail)
    {
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);
        $company = $this->objectManager->get(Company::class);
        $this->companyResource->load($company, $companyEmail, 'company_email');
        $this->companyRepository->deleteById($company->getId());

        $customer = $this->customerRepository->get($customerEmail);
        $this->customerRepository->delete($customer);
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', false);
    }

    /**
     * Create new random customer.
     *
     * @param string $password Customer's password.
     * @return array New customer data.
     */
    private function createNonCompanyCustomer(string $password = 'Test123'): array
    {
        $newFirstname = 'John';
        $newLastname = 'Smith';
        $newEmail = 'new_random_customer' .random_int(1000, 9999) .'@magento.com';

        $query = <<<QUERY
mutation {
    createCustomerV2(
        input: {
            firstname: "{$newFirstname}"
            lastname: "{$newLastname}"
            email: "{$newEmail}"
            password: "{$password}"
            is_subscribed: false
        }
    ) {
        customer {
            id
            email
            created_at
        }
    }
}
QUERY;
        $response = $this->graphQlMutation($query);

        return $response['createCustomerV2']['customer'];
    }

    /**
     * Authenticate customer and retrieve token.
     *
     * @param string $email
     * @param string $password
     * @return string
     * @throws \Exception
     */
    private function authCustomer(string $email, string $password): string
    {
        $query = <<<MUTATION
mutation {
	generateCustomerToken(
        email: "{$email}"
        password: "{$password}"
    ) {
        token
    }
}
MUTATION;

        return $this->graphQlMutation($query)['generateCustomerToken']['token'];
    }
}
