<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\Rule;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Model\CompanyUser;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Framework\UrlInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\AclInterface as UserRoleManagement;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Api\Data\RoleInterface;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class DataProviderTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var CompanyUser|\PHPUnit\Framework\MockObject\MockObject
     */
    private $companyUser;

    /**
     * @var DataProvider
     */
    private $dataProvider;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var UserRoleManagement
     */
    private $userRoleManagement;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = ObjectManager::getInstance();
        $this->urlBuilder = $this->objectManager->create(UrlInterface::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->userRoleManagement = $this->objectManager->get(UserRoleManagement::class);
        $this->roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);
        $this->companyUser = $this->getMockBuilder(CompanyUser::class)
            ->disableOriginalConstructor()->getMock();
        $this->dataProvider = $this->objectManager->create(
            DataProvider::class,
            [
                'name' => 'purchase_order_rule_listing_data_source',
                'primaryFieldName' => 'primary',
                'requestFieldName' => 'request',
                'companyUser' => $this->companyUser
            ]
        );
    }

    /**
     * Test the sort order results for each sortable column
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/rules_for_sorting.php
     * @dataProvider sortOrderDataProvider
     */
    public function testGetData(string $sortByField, string $direction, array $expectedResults)
    {
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $results = $companyRepository->getList(
            $searchCriteriaBuilder->addFilter('company_name', 'Magento')->create()
        )->getItems();
        /* @var CompanyInterface $company */
        $company = reset($results);
        $this->companyUser
            ->expects($this->any())
            ->method('getCurrentCompanyId')
            ->willReturn($company->getId());

        // @var SortOrder
        $sortOrder = $this->objectManager->get(SortOrder::class);
        $sortOrder->setField($sortByField);
        $sortOrder->setDirection($direction);
        $this->dataProvider->getSearchCriteria()->setSortOrders([$sortOrder]);
        $data = $this->dataProvider->getData();
        $actualResult = [];
        foreach ($data['items'] as $item) {
            $actualResult[] = $item[$sortByField];
        }
        $this->assertEquals($expectedResults, $actualResult);
    }

    /**
     * Test manage approval rules acl for different role configuration
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @dataProvider rulesPermissions
     */
    public function testGetDataRolePermissions($permissionValue, $expectedResult)
    {
        $customer = $this->customerRepository->get('veronica.costello@example.com');
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);

        $companies = $companyRepository->getList(
            $searchCriteriaBuilder->addFilter('company_name', 'Magento')->create()
        )->getItems();

        $roles = $roleRepository->getList(
            $searchCriteriaBuilder->addFilter('role_name', 'Default User')->create()
        )->getItems();

        /** @var CompanyInterface $company */
        $company = reset($companies);
        /** @var RoleInterface $role */
        $role = reset($roles);
        $permissions = $role->getPermissions();
        foreach ($permissions as $permission) {
            if ($permission->getResourceId() == 'Magento_PurchaseOrderRule::manage_approval_rules') {
                $permission->setPermission($permissionValue);
                $permission->save();
                break;
            }
        }

        $this->userRoleManagement->assignUserDefaultRole($customer->getId(), $company->getId());
        $this->session->loginById($customer->getId());
        $data = $this->dataProvider->getData();
        $this->assertEquals($expectedResult, $data['isCreateRuleAllowed']);
        $this->assertEquals(
            $this->urlBuilder->getUrl('purchaseorderrule/create'),
            $data['createRuleUrl']
        );
    }

    /**
     * Data provider containing expected manage approval rule permissions
     *
     * @return array[]
     */
    public function rulesPermissions()
    {
        return [
            ['deny', false],
            ['allow', true]
        ];
    }

    /**
     * Data provider containing expected sorting results
     *
     * @return array[]
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function sortOrderDataProvider()
    {
        return [
            [
                'name',
                SortOrder::SORT_ASC,
                [
                    '1   Integration Test Rule Name 7',
                    '10   Integration Test Rule Name 9',
                    '10Alex Smith Integration Test Rule Name 6',
                    '1Alex Smith Integration Test Rule Name 4',
                    '2   Integration Test Rule Name 8',
                    '2Alex Smith Integration Test Rule Name 5',
                    'BeforeTest Smith Integration Test Rule Name 0',
                    'Test Smith Integration Test Rule Name 1',
                    'test Smith Integration Test Rule Name 3',
                    'Tests Smith Integration Test Rule Name 2',
                ]
            ],
            [
                'name',
                SortOrder::SORT_DESC,
                [
                    'Tests Smith Integration Test Rule Name 2',
                    'test Smith Integration Test Rule Name 3',
                    'Test Smith Integration Test Rule Name 1',
                    'BeforeTest Smith Integration Test Rule Name 0',
                    '2Alex Smith Integration Test Rule Name 5',
                    '2   Integration Test Rule Name 8',
                    '1Alex Smith Integration Test Rule Name 4',
                    '10Alex Smith Integration Test Rule Name 6',
                    '10   Integration Test Rule Name 9',
                    '1   Integration Test Rule Name 7',
                ]
            ],
            [
                'is_active',
                SortOrder::SORT_ASC,
                [
                    '0', '0', '1', '1', '1', '1', '1', '1', '1', '1'
                ]
            ],
            [
                'is_active',
                SortOrder::SORT_DESC,
                [
                    '1', '1', '1', '1', '1', '1', '1', '1', '0', '0'
                ]
            ],
            [
                'created_by_name',
                SortOrder::SORT_ASC,
                [
                    '1  ',
                    '10  ',
                    '10Alex Smith',
                    '1Alex Smith',
                    '2  ',
                    '2Alex Smith',
                    'BeforeTest Smith',
                    'Test Smith',
                    'test Smith',
                    'Tests Smith',
                ]
            ],
            [
            'created_by_name',
                SortOrder::SORT_DESC,
                [
                    'Tests Smith',
                    'Test Smith',
                    'test Smith',
                    'BeforeTest Smith',
                    '2Alex Smith',
                    '2  ',
                    '1Alex Smith',
                    '10Alex Smith',
                    '10  ',
                    '1  ',
                ]
            ]
        ];
    }
}
