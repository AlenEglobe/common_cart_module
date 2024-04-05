<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\CompanyCredit\Query;

use Magento\Company\Api\RoleManagementInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Permission\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company credit query
 */
class CreditTest extends GraphQlAbstract
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
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var PermissionManagementInterface
     */
    private $permissionManagement;

    /**
     * @var CollectionFactory
     */
    private $permissionCollection;

    /**
     * @var RoleManagementInterface
     */
    private $roleManagement;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->permissionManagement = $objectManager->get(PermissionManagementInterface::class);
        $this->permissionCollection = $objectManager->get(CollectionFactory::class);
        $this->roleManagement = $objectManager->get(RoleManagementInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCredit(): void
    {
        $expected = [
            "credit" => [
                "outstanding_balance" => [
                    "currency" => "USD",
                    "value" => -30
                ],
                "available_credit" => [
                    "currency" => "USD",
                    "value" => 70
                ],
                "credit_limit" => [
                    "currency" => "USD",
                    "value" => 100
                ]
            ]
        ];

        $response = $this->executeQuery('john.doe@example.com');
        self::assertSame($response['company'], $expected);
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 0
     */
    public function testCompanyCreditPaymentDisabled(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"Payment on Account" is disabled.');
        $this->executeQuery('john.doe@example.com');
    }

    /**
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCreditResourceDenied(): void
    {
        $deniedPermissions = [
            'Magento_Company::credit',
            'Magento_Company::credit_history',
        ];

        $customer = $this->customerRepository->get('veronica.costello@example.com');
        $defaultRole = $this->roleManagement->getCompanyDefaultRole(
            $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $rolePermissions = $this->permissionCollection
            ->create()
            ->addFieldToFilter('role_id', ['eq' => $defaultRole->getId()])
            ->getColumnValues('resource_id');

        // Disable access
        foreach ($deniedPermissions as $permission) {
            if (in_array($permission, $rolePermissions, true)) {
                $key = array_search($permission, $rolePermissions, true);
                unset($rolePermissions[$key]);
            }
        }

        $defaultRole->setPermissions($this->permissionManagement->populatePermissions($rolePermissions));
        $this->roleRepository->save($defaultRole);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have authorization to perform this action.');

        $this->executeQuery('veronica.costello@example.com');
    }

    /**
     * @param string $customerEmail
     * @return array|bool|float|int|string
     */
    private function executeQuery(string $customerEmail)
    {
        $query = <<<QUERY
{
  company {
    credit {
      outstanding_balance {
        currency
        value
      }
      available_credit {
        currency
        value
      }
      credit_limit {
        currency
        value
      }
    }
  }
}
QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute($customerEmail, 'password')
        );
    }
}
