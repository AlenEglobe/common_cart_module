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
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company role name resolver
 */
class CompanyRoleNameTest extends GraphQlAbstract
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
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRoleNameValid(): void
    {
        $query = <<<QUERY
{
    isCompanyRoleNameAvailable(name: "test role name") {
      is_role_name_available
    }
}
QUERY;

        $response = $this->executeQuery($query);
        self::assertTrue($response['isCompanyRoleNameAvailable']['is_role_name_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRoleNameInvalid(): void
    {
        $query = <<<QUERY
{
    isCompanyRoleNameAvailable(name: "custom company role") {
      is_role_name_available
    }
}
QUERY;

        $response = $this->executeQuery($query);
        self::assertFalse($response['isCompanyRoleNameAvailable']['is_role_name_available']);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRoleNameLength(): void
    {
        $query = <<<QUERY
{
    isCompanyRoleNameAvailable(name: "custom company role custom company role custom company role") {
      is_role_name_available
    }
}
QUERY;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company role name must not be more than 40 characters.');
        $this->executeQuery($query);
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
            $this->customerAuthenticationHeader->execute('customrole@company.com', 'password')
        );
    }
}
