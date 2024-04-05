<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Setup\Test\Unit\Fixtures;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\Data\StructureInterface;
use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure;
use Magento\Company\Model\PermissionManagementInterface;
use Magento\Company\Model\ResourceModel\Customer;
use Magento\Company\Model\Role;
use Magento\Company\Model\UserRoleManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use Magento\Directory\Model\ResourceModel\Region;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Setup\Fixtures\CompaniesFixture;
use Magento\Setup\Fixtures\FixtureModel;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CompaniesFixtureTest extends TestCase
{
    /**
     * @var CompaniesFixture
     */
    private $companiesFixture;

    /**
     * @var FixtureModel|MockObject
     */
    private $fixtureModelMock;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    private $objectManager;

    /**
     * @var CustomerInterface|MockObject
     */
    private $customer;

    /**
     * @var CompanyCustomerInterface|MockObject
     */
    private $companyCustomerModel;

    /**
     * @var CustomerRepositoryInterface|MockObject
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var Role|MockObject
     */
    private $role;

    /**
     * @var TeamInterface|MockObject
     */
    private $team;

    /**
     * @var CompanyInterfaceFactory|MockObject
     */
    private $companyFactoryMock;

    /**
     * @var CustomerInterfaceFactory|MockObject
     */
    private $customerFactoryMock;

    /**
     * @var CollectionFactory|MockObject
     */
    private $regionsCollectionFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->fixtureModelMock = $this->getMockBuilder(FixtureModel::class)
            ->onlyMethods(['getValue', 'getObjectManager'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = $this->getMockBuilder(ObjectManagerInterface::class)
            ->onlyMethods(['get', 'create'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->companyCustomerModel = $this->getMockBuilder(CompanyCustomerInterface::class)
            ->onlyMethods(['setCustomerId', 'setCompanyId', 'setJobTitle', 'setStatus', 'setTelephone'])
            ->addMethods(['setIsSuperUser'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->customer = $this->getMockBuilder(CustomerInterface::class)
            ->onlyMethods(['getId', 'getWebsiteId'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->customerRepository = $this->getMockBuilder(CustomerRepositoryInterface::class)
            ->onlyMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->onlyMethods(['addFilter', 'create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->role = $this->getMockBuilder(Role::class)
            ->onlyMethods(['setRoleName', 'setCompanyId', 'setPermissions'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->team = $this->getMockBuilder(TeamInterface::class)
            ->onlyMethods(['setName', 'setDescription'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->customerFactoryMock = $this->getMockBuilder(CustomerInterfaceFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->companyFactoryMock = $this->getMockBuilder(CompanyInterfaceFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->regionsCollectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $objectManagerHelper = new ObjectManager($this);
        $this->companiesFixture = $objectManagerHelper->getObject(
            CompaniesFixture::class,
            [
                'fixtureModel' => $this->fixtureModelMock,
                'websiteCustomers' => null,
                'company' => null,
                'uniqueAttributesQuantity' => null,
                'companyFactory' => $this->companyFactoryMock,
                'customerFactory' => $this->customerFactoryMock,
                'regionsCollectionFactory' => $this->regionsCollectionFactory
            ]
        );
    }

    /**
     * Test execute() method.
     *
     * @param bool $addCustomers
     * @param int|null $websiteId
     * @param array $sequence
     * @param array $calls
     *
     * @return void
     * @dataProvider executeDataProvider
     */
    public function testExecute($addCustomers, $websiteId, array $sequence, array $calls): void
    {
        $companiesCount = $teamsPerCompanyCount = 1;
        $userRolesPerCompanyCount = 2;
        $customersCount = 3;

        $this->fixtureModelMock->method('getValue')
            ->withConsecutive(
                ['companies', 0],
                ['companies', 0],
                ['user_roles_per_company', 0],
                ['customers', 0],
                ['user_roles_per_company', 0],
                ['teams_per_company', 0]
            )
            ->willReturnOnConsecutiveCalls(
                $companiesCount,
                $companiesCount,
                $userRolesPerCompanyCount,
                $customersCount,
                $userRolesPerCompanyCount,
                $teamsPerCompanyCount
            );

        $this->prepareObjectManager($addCustomers, $websiteId, $calls);
        $this->fixtureModelMock->expects($this->exactly($calls['fixtureModel_getObjectManager']))
            ->method('getObjectManager')->willReturn($this->objectManager);

        $this->companiesFixture->execute();
    }

    /**
     * Data provider for execute() method.
     *
     * @return array
     */
    public function executeDataProvider(): array
    {
        return [
            [true, null,
                [
                    'user_roles_per_company' => 9,
                    'teams_per_company' => 10,
                ],
                [
                    'storeManager_getWebsites'=> 3,
                    'website_getId' => 6,
                    'fixtureModel_getObjectManager' => 9,
                    'objectManager_get' => 5,
                    'objectManager_create' => 4,
                    'company_getId' => 1,
                    'customer_getId' => 5,
                    'customerFactory_create' => 1,
                    'companyCustomerResource_saveAdvancedCustomAttributes' => 1,
                    'companyCustomerModel_setCustomerId' => 1,
                    'companyCustomerModel_setCompanyId' => 1,
                    'companyCustomerModel_setJobTitle' => 1,
                    'companyCustomerModel_setIsSuperUser' => 1,
                    'companyCustomerModel_setStatus' => 1,
                    'companyCustomerModel_setTelephone' => 1,
                    'storeManager_getWebsite' => 1,
                    'companyStructureManager_getStructureByCustomerId' => 1,
                    'companyStructure_getData' => 0,
                    'companyStructureManager_addNode' => 1,
                    'companyStructureManager_getStructureByTeamId' => 0,
                    'companyPermissionManagement_retrieveDefaultPermissions' => 0,
                    'role_setRoleName' => 0,
                    'role_setCompanyId' => 0,
                    'role_setPermissions' => 0,
                    'roleRepository_save' => 0,
                    'userRoleManagement_assignRoles' => 0,
                    'team_setName' => 0,
                    'team_setDescription' => 0,
                    'teamRepository_create' => 0
                ]
            ],
            [false, 34,
                [
                    'user_roles_per_company' => 9,
                    'teams_per_company' => 10,
                ],
                [
                    'storeManager_getWebsites'=> 2,
                    'website_getId' => 4,
                    'fixtureModel_getObjectManager' => 17,
                    'objectManager_get' => 11,
                    'objectManager_create' => 6,
                    'company_getId' => 4,
                    'customer_getId' => 11,
                    'customerFactory_create' => 2,
                    'companyCustomerResource_saveAdvancedCustomAttributes' => 2,
                    'companyCustomerModel_setCustomerId' => 2,
                    'companyCustomerModel_setCompanyId' => 2,
                    'companyCustomerModel_setJobTitle' => 2,
                    'companyCustomerModel_setIsSuperUser' => 2,
                    'companyCustomerModel_setStatus' => 2,
                    'companyCustomerModel_setTelephone' => 2,
                    'storeManager_getWebsite' => 1,
                    'companyStructureManager_getStructureByCustomerId' => 1,
                    'companyStructure_getData' => 1,
                    'companyStructureManager_addNode' => 2,
                    'companyStructureManager_getStructureByTeamId' => 1,
                    'companyPermissionManagement_retrieveDefaultPermissions' => 1,
                    'role_setRoleName' => 1,
                    'role_setCompanyId' => 1,
                    'role_setPermissions' => 1,
                    'roleRepository_save' => 1,
                    'userRoleManagement_assignRoles' => 1,
                    'team_setName' => 1,
                    'team_setDescription' => 1,
                    'teamRepository_create' => 1
                ]
            ]
        ];
    }

    /**
     * Test execute() with Exception.
     *
     * @return void
     */
    public function testExecuteWithException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('There are not enough customers to populate all companies');

        $companiesCount = 1;
        $userRolesPerCompanyCount = 2;
        $customersCount = 1;
        $mapForMethodGetValue = [
            ['companies', 0, $companiesCount],
            ['user_roles_per_company', 0, $userRolesPerCompanyCount],
            ['customers', 0, $customersCount]
        ];
        $this->fixtureModelMock->expects($this->exactly(4))->method('getValue')->willReturnMap($mapForMethodGetValue);

        $companyRepository = $this->getMockBuilder(CompanyRepositoryInterface::class)
            ->disableOriginalConstructor()->getMockForAbstractClass();

        $mapForMethodGet = [
            [CompanyRepositoryInterface::class, $companyRepository]
        ];
        $this->objectManager->expects($this->once())->method('get')->willReturnMap($mapForMethodGet);
        $regionsCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $region = $this->getMockBuilder(Region::class)->disableOriginalConstructor()
            ->addMethods(['getCode', 'getRegionId'])
            ->getMock();
        $this->regionsCollectionFactory->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($regionsCollection);
        $regionsCollection->expects($this->atLeastOnce())->method('getFirstItem')->willReturn($region);

        $this->fixtureModelMock->expects($this->once())->method('getObjectManager')
            ->willReturn($this->objectManager);

        $this->companiesFixture->execute();
    }

    /**
     * Prepare ObjectManager mock.
     *
     * @param bool $addCustomers
     * @param int|null $websiteId
     * @param array $calls
     *
     * @return void
     */
    private function prepareObjectManager($addCustomers, $websiteId, array $calls): void
    {
        $this->prepareCustomer($websiteId, $calls);

        $this->prepareRole($calls);

        $this->prepareTeam($calls);

        $mapForMethodGet = $this->getMapForMethodGet($addCustomers, $calls);
        $this->objectManager->expects($this->exactly($calls['objectManager_get']))->method('get')
            ->willReturnMap($mapForMethodGet);

        $mapForMethodCreate = $this->getMapForMethodCreate($websiteId, $calls);
        $this->objectManager->expects($this->exactly($calls['objectManager_create']))->method('create')
            ->willReturnMap($mapForMethodCreate);
    }

    /**
     * Prepare Customer mock.
     *
     * @param int|null $websiteId
     * @param array $calls
     *
     * @return void
     */
    private function prepareCustomer($websiteId, array $calls): void
    {
        $customerId = 354;
        $this->customer->expects($this->exactly($calls['customer_getId']))->method('getId')->willReturn($customerId);
        $this->customer->expects($this->exactly(2))->method('getWebsiteId')->willReturn($websiteId);
    }

    /**
     * Prepare Role mock.
     *
     * @param array $calls
     *
     * @return void
     */
    private function prepareRole(array $calls): void
    {
        $this->role->expects($this->exactly($calls['role_setRoleName']))->method('setRoleName')->willReturnSelf();
        $this->role->expects($this->exactly($calls['role_setCompanyId']))->method('setCompanyId')->willReturnSelf();
        $this->role->expects($this->exactly($calls['role_setPermissions']))->method('setPermissions')->willReturnSelf();
    }

    /**
     * Prepare Team mock.
     *
     * @param array $calls
     *
     * @return void
     */
    private function prepareTeam(array $calls): void
    {
        $this->team->expects($this->exactly($calls['team_setName']))->method('setName')->willReturnSelf();
        $this->team->expects($this->exactly($calls['team_setDescription']))->method('setDescription')->willReturnSelf();
    }

    /**
     * Retrieve map for calling get() method in ObjectManager mock.
     *
     * @param bool $addCustomers
     * @param array $calls
     *
     * @return array
     */
    private function getMapForMethodGet($addCustomers, array $calls): array
    {
        $company = $this->getMockBuilder(CompanyInterface::class)
            ->onlyMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $companyId = 23;
        $company->expects($this->exactly($calls['company_getId']))->method('getId')->willReturn($companyId);

        $this->companyFactoryMock->expects($this->exactly(1))
            ->method('create')
            ->willReturn($company);

        $companyRepository = $this->getMockBuilder(CompanyRepositoryInterface::class)
            ->onlyMethods(['save'])
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $companyRepository->expects($this->atLeastOnce())->method('save')->willReturn($company);

        $this->prepareCustomerRepository($addCustomers);

        $this->prepareSearchCriteriaBuilder();

        $companyCustomerResource = $this->getMockBuilder(Customer::class)
            ->onlyMethods(['saveAdvancedCustomAttributes'])
            ->disableOriginalConstructor()->getMock();
        $companyCustomerResource
            ->expects($this->exactly($calls['companyCustomerResource_saveAdvancedCustomAttributes']))
            ->method('saveAdvancedCustomAttributes')->willReturnSelf();

        $companyStructure = $this->getMockBuilder(StructureInterface::class)
            ->addMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $targetId = 737;
        $companyStructure->expects($this->exactly($calls['companyStructure_getData']))->method('getData')
            ->with(StructureInterface::STRUCTURE_ID)->willReturn($targetId);

        $companyStructureManager = $this->getMockBuilder(Structure::class)
            ->onlyMethods(['getStructureByCustomerId', 'addNode', 'getStructureByTeamId'])
            ->disableOriginalConstructor()
            ->getMock();

        $companyStructureManager->expects($this->exactly($calls['companyStructureManager_getStructureByCustomerId']))
            ->method('getStructureByCustomerId')->willReturnOnConsecutiveCalls(null, $companyStructure);

        $companyStructureManager->expects($this->exactly($calls['companyStructureManager_addNode']))->method('addNode')
            ->willReturnSelf();
        $companyStructureManager->expects($this->exactly($calls['companyStructureManager_getStructureByTeamId']))
            ->method('getStructureByTeamId')->willReturn($companyStructure);

        $permission = $this->getMockBuilder(RoleInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $companyPermissionManagement = $this->getMockBuilder(PermissionManagementInterface::class)
            ->onlyMethods(['retrieveDefaultPermissions'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $defaultPermissions = [$permission];
        $companyPermissionManagement
            ->expects($this->exactly($calls['companyPermissionManagement_retrieveDefaultPermissions']))
            ->method('retrieveDefaultPermissions')->willReturn($defaultPermissions);

        $roleRepository = $this->getMockBuilder(RoleRepositoryInterface::class)
            ->onlyMethods(['save'])
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $roleRepository->expects($this->exactly($calls['roleRepository_save']))->method('save')
            ->willReturn($this->role);

        $userRoleManagement = $this->getMockBuilder(UserRoleManagement::class)
            ->onlyMethods(['assignRoles'])
            ->disableOriginalConstructor()
            ->getMock();
        $userRoleManagement->expects($this->exactly($calls['userRoleManagement_assignRoles']))->method('assignRoles')
            ->willReturn(null);

        $teamRepository = $this->getMockBuilder(TeamRepositoryInterface::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $teamId = 75;
        $teamRepository->expects($this->exactly($calls['teamRepository_create']))
            ->method('create')
            ->willReturn($teamId);

        return [
            [CompanyRepositoryInterface::class, $companyRepository],
            [CustomerRepositoryInterface::class, $this->customerRepository],
            [SearchCriteriaBuilder::class, $this->searchCriteriaBuilder],
            [Customer::class, $companyCustomerResource],
            [Structure::class, $companyStructureManager],
            [PermissionManagementInterface::class, $companyPermissionManagement],
            [RoleRepositoryInterface::class, $roleRepository],
            [UserRoleManagement::class, $userRoleManagement],
            [TeamRepositoryInterface::class, $teamRepository]
        ];
    }

    /**
     * Prepare CustomerRepository mock.
     *
     * @param bool $addCustomers
     *
     * @return void
     */
    private function prepareCustomerRepository($addCustomers): void
    {
        $customerSearchResult = $this->getMockBuilder(CustomerSearchResultsInterface::class)
            ->onlyMethods(['getItems'])
            ->disableOriginalConstructor()->getMockForAbstractClass();

        if ($addCustomers) {
            $customers = [$this->customer];
        } else {
            $customers = [];
        }
        $customerSearchResult->expects($this->exactly(1))->method('getItems')->willReturn($customers);

        $this->customerRepository->expects($this->exactly(1))->method('getList')->willReturn($customerSearchResult);
    }

    /**
     * Prepare CustomerRepository mock.
     *
     * @return void
     */
    private function prepareSearchCriteriaBuilder(): void
    {
        $this->searchCriteriaBuilder->expects($this->exactly(1))->method('addFilter')->willReturnSelf();

        $searchCriteria = $this->getMockBuilder(SearchCriteria::class)
            ->disableOriginalConstructor()->getMock();
        $this->searchCriteriaBuilder->expects($this->exactly(1))->method('create')->willReturn($searchCriteria);
    }

    /**
     * Retrieve map for calling create() method in ObjectManager mock.
     *
     * @param int|null $websiteId
     * @param array $calls
     *
     * @return array
     */
    private function getMapForMethodCreate($websiteId, array $calls): array
    {
        $website = $this->getMockBuilder(WebsiteInterface::class)
            ->onlyMethods(['getId'])
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $website->expects($this->exactly($calls['website_getId']))->method('getId')->willReturn($websiteId);

        $storeManager = $this->getMockBuilder(StoreManager::class)
            ->onlyMethods(['getWebsites', 'getWebsite'])
            ->disableOriginalConstructor()->getMock();

        $websites = [$website];
        $storeManager->expects($this->exactly($calls['storeManager_getWebsites']))->method('getWebsites')
            ->willReturn($websites);

        $storeManager->expects($this->exactly($calls['storeManager_getWebsite']))->method('getWebsite')
            ->willReturn($website);

        $this->customerFactoryMock->expects($this->exactly($calls['customerFactory_create']))
            ->method('create')
            ->willReturn($this->customer);
        $regionsCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $region = $this->getMockBuilder(Region::class)->disableOriginalConstructor()
            ->addMethods(['getCode', 'getRegionId'])
            ->getMock();
        $this->regionsCollectionFactory->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($regionsCollection);
        $regionsCollection->expects($this->atLeastOnce())->method('getFirstItem')->willReturn($region);
        $region->expects($this->atLeastOnce())->method('getCode')->willReturn('Al');
        $region->expects($this->atLeastOnce())->method('getRegionId')->willReturn(1);

        $this->prepareCompanyCustomerModel($calls);

        return [
            [StoreManager::class, [], $storeManager],
            [CompanyCustomerInterface::class, [], $this->companyCustomerModel],
            [Role::class, [], $this->role],
            [TeamInterface::class, [], $this->team]
        ];
    }

    /**
     * Prepare CompanyCustomerModel mock.
     *
     * @param array $calls
     *
     * @return void
     */
    private function prepareCompanyCustomerModel(array $calls): void
    {
        $this->companyCustomerModel->expects($this->exactly($calls['companyCustomerModel_setCustomerId']))
            ->method('setCustomerId')->willReturnSelf();
        $this->companyCustomerModel->expects($this->exactly($calls['companyCustomerModel_setCompanyId']))
            ->method('setCompanyId')->willReturnSelf();
        $this->companyCustomerModel->expects($this->exactly($calls['companyCustomerModel_setJobTitle']))
            ->method('setJobTitle')->willReturnSelf();
        $this->companyCustomerModel->expects($this->exactly($calls['companyCustomerModel_setStatus']))
            ->method('setStatus')->willReturnSelf();
        $this->companyCustomerModel->expects($this->exactly($calls['companyCustomerModel_setTelephone']))
            ->method('setTelephone')->willReturnSelf();
    }

    /**
     * Test getActionTitle() method.
     *
     * @return void
     */
    public function testGetActionTitle(): void
    {
        $expected = 'Generating companies';
        $this->assertEquals($expected, $this->companiesFixture->getActionTitle());
    }

    /**
     * Test introduceParamLabels() method.
     *
     * @return void
     */
    public function testIntroduceParamLabels(): void
    {
        $expected = ['companies' => 'Companies'];
        $this->assertEquals($expected, $this->companiesFixture->introduceParamLabels());
    }
}
