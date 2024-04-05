<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query;

use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test company admin email resolver
 */
class CompanyAdminEmailTest extends GraphQlAbstract
{
    #[
        Config('btob/website_configuration/company_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        )
    ]
    public function testCompanyAdminEmailValid(): void
    {
        $query = <<<QUERY
{
    isCompanyAdminEmailAvailable(email: "test@test.com") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery($query);
        self::assertTrue($response['isCompanyAdminEmailAvailable']['is_email_available']);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        )
    ]
    public function testCompanyAdminEmailInvalid(): void
    {
        $query = <<<QUERY
{
    isCompanyAdminEmailAvailable(email: "%s") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery(
            sprintf($query, DataFixtureStorageManager::getStorage()->get('customer')->getEmail())
        );
        self::assertFalse($response['isCompanyAdminEmailAvailable']['is_email_available']);
    }

    #[
        Config('btob/website_configuration/company_active', 1)
    ]
    public function testCompanyAdminEmailFormatInvalid(): void
    {
        $expectedMessage = 'Invalid value of "admin@magento" provided for the email field.';
        $query = <<<QUERY
{
    isCompanyAdminEmailAvailable(email: "admin@magento") {
      is_email_available
    }
}
QUERY;

        try {
            $this->graphQlQuery($query);
            self::fail('Response should contains errors.');
        } catch (ResponseContainsErrorsException $e) {
            $responseData = $e->getResponseData();
            self::assertEquals($expectedMessage, $responseData['errors'][0]['message']);
        }
    }

    #[
        Config('btob/website_configuration/company_active', 0)
    ]
    public function testCompanyInActive(): void
    {
        $expectedMessage = 'Company feature is not available.';
        $query = <<<QUERY
{
    isCompanyAdminEmailAvailable(email: "admin@magento.com") {
      is_email_available
    }
}
QUERY;

        try {
            $this->graphQlQuery($query);
            self::fail('Response should contains errors.');
        } catch (ResponseContainsErrorsException $e) {
            $responseData = $e->getResponseData();
            self::assertEquals($expectedMessage, $responseData['errors'][0]['message']);
        }
    }
}
