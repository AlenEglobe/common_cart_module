<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query\Resolver;

use Magento\Company\Api\RoleManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\Framework\GraphQl\Query\Uid;

/**
 * Test company role resolver
 */
class CompanyRoleTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @var RoleManagementInterface
     */
    private $roleManagement;

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
        $this->roleManagement = $objectManager->get(RoleManagementInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->idEncoder = $objectManager->get(Uid::class);
    }

    /**
     * Test company role
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRole(): void
    {
        $email = 'john.doe@example.com';
        $customer = $this->customerRepository->get($email);
        $defaultRole = $this->roleManagement->getCompanyDefaultRole(
            $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $response = $this->executeQuery((string)$defaultRole->getId(), $email, 'password');
        $this->validateAclResource($response['company']['role']['permissions']);
    }

    /**
     * Test access to other company role
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testOtherCompanyRole(): void
    {
        $customer = $this->customerRepository->get('customrole@company.com');
        $defaultRole = $this->roleManagement->getCompanyDefaultRole(
            $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $response = $this->executeQuery((string)$defaultRole->getId(), 'john.doe@example.com', 'password');
        self::assertNull($response['company']['role']);
    }

    /**
     * @param $aclResources
     */
    private function validateAclResource($aclResources): void
    {
        foreach ($aclResources as $aclResource) {
            self::assertArrayHasKey('id', $aclResource);
            self::assertArrayHasKey('sort_order', $aclResource);
            self::assertArrayHasKey('text', $aclResource);

            if (!empty($aclResource['children'])) {
                $this->validateAclResource($aclResource['children']);
            }
        }
    }

    /**
     * @param string $roleId
     * @param string $email
     * @param string $password
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery(string $roleId, string $email, string $password)
    {
        $roleId = $this->idEncoder->encode($roleId);
        $query = <<<QUERY
{
  company {
    role (id: "{$roleId}") {
      name
      users_count
      permissions {
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
