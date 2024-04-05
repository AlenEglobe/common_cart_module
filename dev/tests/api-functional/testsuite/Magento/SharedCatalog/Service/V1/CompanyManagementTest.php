<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Service\V1;

use Exception;
use Magento\Authorization\Test\Fixture\Role as RoleFixture;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\SharedCatalog\Api\Data\SharedCatalogInterface;
use Magento\SharedCatalog\Test\Fixture\AssignCompany;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\Store\Test\Fixture\Store;
use Magento\Store\Test\Fixture\Website;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\User\Test\Fixture\User;

/**
 * Tests for shared catalog companies actions (assign, unassign, getting).
 */
class CompanyManagementTest extends AbstractSharedCatalogTest
{
    private const SERVICE_READ_NAME = 'sharedCatalogCompanyManagementV1';
    private const SERVICE_VERSION = 'V1';

    /**
     * Test flows is: assign company, double-check with get companies, unassign company, and double-check again.
     *
     * @return void
     * @throws LocalizedException
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        DataFixture(Website::class, as: 'website'),
        DataFixture(Store::class, as: 'store'),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(RoleFixture::class, as: 'restrictedRole'),
        DataFixture(User::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$restrictedUser.id$',
                'super_user_id' => '$company_admin.id$'
            ],
            'company'
        ),
        DataFixture(SharedCatalog::class, as: 'shared_catalog')
    ]
    public function testSharedCatalogFlow()
    {
        /** @var SharedCatalogInterface $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        /** @var CompanyInterface $sharedCatalog */
        $company = DataFixtureStorageManager::getStorage()->get('company');

        $assignCompaniesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/assignCompanies', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'assignCompanies',
            ],
        ];
        $companiesParam = [
            [
                'id' => $company->getId(),
                'street' => $company->getStreet(),
                'sales_representative_id' => $company->getSalesRepresentativeId(),
                'reject_reason' => $company->getRejectReason(),
                'rejected_at' => $company->getRejectedAt(),
                'customer_group_id' => $company->getCustomerGroupId(),
                'super_user_id' => $company->getSuperUserId()
            ]
        ];

        $params = ['sharedCatalogId' => $sharedCatalog->getId(), 'companies' => $companiesParam];
        $resp = $this->_webApiCall($assignCompaniesServiceInfo, $params);

        $this->assertTrue($resp, 'Assign companies did not work');

        $getCompaniesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/companies', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'getCompanies',
            ],
        ];

        $respCompanyIds = $this->_webApiCall($getCompaniesServiceInfo, ['sharedCatalogId' => $sharedCatalog->getId()]);
        $this->assertEquals(
            implode("\"", ["[", $company->getId(), "]"]),
            $respCompanyIds,
            'Companies are not assigned.'
        );

        $unassignCompaniesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/unassignCompanies', $sharedCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'unassignCompanies',
            ],
        ];

        $params = ['sharedCatalogId' => $sharedCatalog->getId(), 'companies' => $companiesParam];
        $resp = $this->_webApiCall($unassignCompaniesServiceInfo, $params);

        $this->assertTrue($resp, 'Unassign companies did not work');

        $respCompanyIds = $this->_webApiCall($getCompaniesServiceInfo, ['sharedCatalogId' => $sharedCatalog->getId()]);
        $this->assertEquals(
            "[]",
            $respCompanyIds,
            'Companies are not unassigned.'
        );
    }

    /**
     * Test unassign company from Public Shared Catalog
     *
     * @return void
     * @throws LocalizedException
     */
    #[
        Config('catalog/magento_catalogpermissions/enabled', 1),
        DataFixture(Website::class, as: 'website'),
        DataFixture(Store::class, as: 'store'),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(RoleFixture::class, as: 'restrictedRole'),
        DataFixture(User::class, ['role_id' => '$restrictedRole.id$'], 'restrictedUser'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$restrictedUser.id$',
                'super_user_id' => '$company_admin.id$'
            ],
            'company'
        ),
        DataFixture(SharedCatalog::class, as: 'shared_catalog'),
        DataFixture(AssignCompany::class, ['catalog_id' => '$shared_catalog.id$', 'company' => '$company$'])
    ]
    public function testUnassignCompanyFromPublicSharedCatalog()
    {
        $expectedMessage = 'You cannot unassign a company from the public shared catalog.';

        $publicCatalog = $this->getPublicCatalog();
        /** @var CompanyInterface $sharedCatalog */
        $company = DataFixtureStorageManager::getStorage()->get('company');

        $unassignCompaniesServiceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/sharedCatalog/%d/unassignCompanies', $publicCatalog->getId()),
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'unassignCompanies',
            ],
        ];
        $companiesParam = [
            [
                'id' => $company->getId(),
                'street' => $company->getStreet(),
                'sales_representative_id' => $company->getSalesRepresentativeId(),
                'reject_reason' => $company->getRejectReason(),
                'rejected_at' => $company->getRejectedAt(),
                'customer_group_id' => $company->getCustomerGroupId(),
                'super_user_id' => $company->getSuperUserId()
            ]
        ];

        $params = ['sharedCatalogId' => $publicCatalog->getId(), 'companies' => $companiesParam];

        try {
            $this->_webApiCall($unassignCompaniesServiceInfo, $params);
            $this->fail("Expected exception");
        } catch (\SoapFault $e) {
            $this->assertStringContainsString(
                $expectedMessage,
                $e->getMessage(),
                "SoapFault does not contain expected message."
            );
        } catch (Exception $e) {
            $errorObj = $this->processRestExceptionResult($e);
            $this->assertEquals($expectedMessage, $errorObj['message']);
        }
    }
}
