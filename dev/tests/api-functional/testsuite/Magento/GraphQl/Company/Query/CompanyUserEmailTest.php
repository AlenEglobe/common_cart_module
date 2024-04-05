<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query;

use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company user email resolver
 */
class CompanyUserEmailTest extends GraphQlAbstract
{
    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyUserEmailValid(): void
    {
        $query = <<<QUERY
{
    isCompanyUserEmailAvailable(email: "test@test.com") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery($query);
        self::assertTrue($response['isCompanyUserEmailAvailable']['is_email_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyUserEmailInvalid(): void
    {
        $query = <<<QUERY
{
    isCompanyUserEmailAvailable(email: "company-admin@example.com") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery($query);
        self::assertFalse($response['isCompanyUserEmailAvailable']['is_email_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture btob/website_configuration/company_active 0
     */
    public function testCompanyInActive(): void
    {
        $expectedMessage = 'Company feature is not available.';
        $query = <<<QUERY
{
    isCompanyUserEmailAvailable(email: "company-admin@example.com") {
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
