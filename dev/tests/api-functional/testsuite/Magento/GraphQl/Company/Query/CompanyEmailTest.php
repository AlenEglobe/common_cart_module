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
 * Test company email resolver
 */
class CompanyEmailTest extends GraphQlAbstract
{
    /**
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyEmailValid(): void
    {
        $query = <<<QUERY
{
    isCompanyEmailAvailable(email: "test@test.com") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery($query);
        self::assertTrue($response['isCompanyEmailAvailable']['is_email_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyEmailInvalid(): void
    {
        $query = <<<QUERY
{
    isCompanyEmailAvailable(email: "email@magento.com") {
      is_email_available
    }
}
QUERY;

        $response = $this->graphQlQuery($query);
        self::assertFalse($response['isCompanyEmailAvailable']['is_email_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company.php
     * @magentoConfigFixture btob/website_configuration/company_active 0
     */
    public function testCompanyInActive(): void
    {
        $expectedMessage = 'Company feature is not available.';
        $query = <<<QUERY
{
    isCompanyEmailAvailable(email: "email@magento.com") {
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
