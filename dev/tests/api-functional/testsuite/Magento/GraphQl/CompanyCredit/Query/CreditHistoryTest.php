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
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company credit history query
 *
 * @magentoAppIsolation enabled
 */
class CreditHistoryTest extends GraphQlAbstract
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
     * Test company credit history
     *
     * @magentoApiDataFixture Magento/Company/_files/companies_with_credit.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCreditHistory(): void
    {
        $response = $this->executeQuery('sam.smith@example.com', 'yeibkxbOe3r');
        self::assertSame($this->getExpectedCreditHistory(), $response['company']);
    }

    /**
     * Test company credit history with filters
     *
     * @magentoApiDataFixture Magento/Company/_files/companies_with_credit.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCreditHistoryWithFilters(): void
    {
        $response = $this->executeQueryWithFilters(
            'sam.smith@example.com',
            'REIMBURSEMENT',
            'newname',
            '12345',
            'yeibkxbOe3r'
        );
        self::assertSame($this->getExpectedCreditHistory(), $response['company']);
    }

    /**
     * Test company credit history with wrong filters
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCreditHistoryWrongFilters(): void
    {
        $expected = [
            "credit_history" => [
                "items" => [],
                "page_info" => [
                    "current_page" => 1,
                    "page_size" => 20,
                    "total_pages" => 0
                ],
                "total_count" => 0
            ]
        ];
        $response = $this->executeQueryWithFilters(
            'john.doe@example.com',
            'REIMBURSEMENT',
            'non-exists',
            '12345',
            'password'
        );
        self::assertSame($expected, $response['company']);
    }

    /**
     * Test company credit history when payment on account is disabled
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 0
     */
    public function testCompanyCreditPaymentDisabled(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"Payment on Account" is disabled.');
        $this->executeQuery('john.doe@example.com', 'password');
    }

    /**
     * Test company credit history when resource is denied
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoConfigFixture payment/companycredit/active 1
     */
    public function testCompanyCreditResourceDenied(): void
    {
        $deniedPermissions = [
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

        $this->executeQuery('veronica.costello@example.com', 'password');
    }

    /**
     * Execute query
     *
     * @param string $customerEmail
     * @param string $password
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery(string $customerEmail, string $password)
    {
        $query = <<<QUERY
{
  company {
    credit_history {
      items {
        amount {
          currency
          value
        }
        balance {
          credit_limit {
            currency
            value
          }
          available_credit {
            currency
            value
          }
          outstanding_balance {
            currency
            value
          }
        }
        custom_reference_number
        type
        updated_by {
          name
          type
        }
      }
      page_info {
        current_page
        page_size
        total_pages
      }
      total_count
    }
  }
}
QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute($customerEmail, $password)
        );
    }

    /**
     * Execute query with filters
     *
     * @param string $customerEmail
     * @param string $operationType
     * @param string $updatedBy
     * @param string $customReferenceNumber
     * @param string $password
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQueryWithFilters(
        string $customerEmail,
        string $operationType,
        string $updatedBy,
        string $customReferenceNumber,
        string $password
    ) {
        $query = <<<QUERY
{
  company {
    credit_history (
      filter: {
        updated_by: "{$updatedBy}",
        operation_type: {$operationType},
        custom_reference_number: "{$customReferenceNumber}"
      }
    ) {
      items {
        amount {
          currency
          value
        }
        balance {
          credit_limit {
            currency
            value
          }
          available_credit {
            currency
            value
          }
          outstanding_balance {
            currency
            value
          }
        }
        custom_reference_number
        type
        updated_by {
          name
          type
        }
      }
      page_info {
        current_page
        page_size
        total_pages
      }
      total_count
    }
  }
}
QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute($customerEmail, $password)
        );
    }

    /**
     * Get expected credit history
     *
     * @return array
     */
    private function getExpectedCreditHistory(): array
    {
        return [
            "credit_history" => [
                "items" => [
                    [
                        "amount" => [
                            "currency" => "USD",
                            "value" => 500
                        ],
                        "balance" => [
                            "credit_limit" => [
                                "currency" => "USD",
                                "value" => 1000
                            ],
                            "available_credit" => [
                                "currency" => "USD",
                                "value" => 2500
                            ],
                            "outstanding_balance" => [
                                "currency" => "USD",
                                "value" => 1500
                            ]
                        ],
                        "custom_reference_number" => "12345",
                        "type" => "REIMBURSEMENT",
                        "updated_by" => [
                            "name" => "newname secondname",
                            "type" => "ADMIN"
                        ]
                    ]
                ],
                "page_info" => [
                    "current_page" => 1,
                    "page_size" => 20,
                    "total_pages" => 1
                ],
                "total_count" => 1
            ]
        ];
    }
}
