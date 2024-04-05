<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Setup\Fixtures;

use Magento\Company\Api\Data\CompanyCustomerInterface;

/**
 * Generates Companies fixtures.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CompaniesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    protected $priority = 130;

    /**
     * @var array
     */
    private $websiteCustomers;

    /**
     * @var \Magento\Company\Api\Data\CompanyInterface
     */
    private $company;

    /**
     * @var \Magento\Company\Api\CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var \Magento\Company\Api\Data\CompanyInterfaceFactory
     */
    private $companyFactory;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    private $customerFactory;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Region\CollectionFactory
     */
    private $regionsCollectionFactory;

    /**
     * Constructor
     *
     * @param FixtureModel $fixtureModel
     * @param \Magento\Company\Api\Data\CompanyInterfaceFactory|null $companyFactory
     * @param \Magento\Customer\Api\Data\CustomerInterfaceFactory|null $customerFactory
     * @param \Magento\Directory\Model\ResourceModel\Region\CollectionFactory|null $regionsCollectionFactory
     */
    public function __construct(
        FixtureModel $fixtureModel,
        \Magento\Company\Api\Data\CompanyInterfaceFactory $companyFactory = null,
        \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory = null,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionsCollectionFactory = null
    ) {
        parent::__construct($fixtureModel);
        $this->companyFactory = $companyFactory ?: $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Api\Data\CompanyInterfaceFactory::class);
        $this->customerFactory = $customerFactory ?: $this->fixtureModel->getObjectManager()
            ->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class);
        $this->regionsCollectionFactory = $regionsCollectionFactory ?: $this->fixtureModel->getObjectManager()
            ->get(\Magento\Directory\Model\ResourceModel\Region\CollectionFactory::class);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $companiesCount = $this->fixtureModel->getValue('companies', 0);

        if (!$companiesCount) {
            return;
        }

        $this->companyRepository = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Api\CompanyRepositoryInterface::class);
        $regionsCollection = $this->regionsCollectionFactory->create();
        $regionsCollection->unshiftOrder('region_id', 'ASC');
        $region = $regionsCollection->getFirstItem();

        if (!$this->websiteCustomers) {
            $this->createWebsiteCustomers();
        }

        for ($i = 1; $i <= $companiesCount; $i++) {
            $companyAdmin = $this->getNextCustomer();
            $company = $this->companyFactory->create(
                [
                    'data' => [
                        'status' => \Magento\Company\Api\Data\CompanyInterface::STATUS_APPROVED,
                        'company_name' => 'Company ' . $i,
                        'legal_name' => 'Company legal name ' . $i,
                        'company_email' => 'company' . $i . '@example.com',
                        'street' => 'Street ' . $i,
                        'city' => 'City ' . $i,
                        'country_id' => 'US',
                        'region' => $region->getCode(),
                        'region_id' => $region->getRegionId(),
                        'postcode' => '22222',
                        'telephone' => '2222222',
                        'super_user_id' => $companyAdmin->getId(),
                        'customer_group_id' => 1
                    ]
                ]
            );
            $this->company = $this->companyRepository->save($company);
            $companyAdmin = $this->assignCustomersToCompany($companyAdmin);
            $this->company->setCompanyEmail($companyAdmin->getEmail());
            $this->companyRepository->save($company);
        }
    }

    /**
     * @inheritdoc
     */
    public function getActionTitle()
    {
        return 'Generating companies';
    }

    /**
     * @inheritdoc
     */
    public function introduceParamLabels()
    {
        return ['companies' => 'Companies'];
    }

    /**
     * Get next customer.
     *
     * @param int $websiteId [optional]
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function getNextCustomer($websiteId = null)
    {
        if (null === $websiteId) {
            $websiteId = $this->getFirstWebsiteId();
        }

        $result = array_shift($this->websiteCustomers[$websiteId]);
        if (null === $result) {
            $result = $this->customerFactory->create();
        }

        return $result;
    }

    /**
     * Get ID of first website which has users not assigned to any company.
     *
     * @return int
     */
    private function getFirstWebsiteId()
    {
        /** @var \Magento\Store\Model\StoreManager $storeManager */
        $storeManager = $this->fixtureModel->getObjectManager()->create(\Magento\Store\Model\StoreManager::class);
        $websites = $storeManager->getWebsites();
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        foreach ($websites as $website) {
            if (count($this->websiteCustomers[$website->getId()]) > 0) {
                return $website->getId();
            }
        }

        return $storeManager->getWebsite()->getId();
    }

    /**
     * Create website customers list.
     *
     * @return void
     * @throws \Exception
     */
    private function createWebsiteCustomers()
    {
        if (!$this->validateCustomersQuantity()) {
            throw new \Exception("There are not enough customers to populate all companies");
        }
        /** @var \Magento\Store\Model\StoreManager $storeManager */
        $storeManager = $this->fixtureModel->getObjectManager()->create(\Magento\Store\Model\StoreManager::class);
        $websites = $storeManager->getWebsites();
        $this->websiteCustomers = [];
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        foreach ($websites as $website) {
            $this->websiteCustomers[$website->getId()] = $this->getWebsiteCustomers($website->getId());
        }
    }

    /**
     * Validate that companies have plenty of customers.
     *
     * @return bool
     */
    private function validateCustomersQuantity()
    {
        $companiesCount = $this->fixtureModel->getValue('companies', 0);
        $usersPerCompanyCount = $this->fixtureModel->getValue('user_roles_per_company', 0);
        $customersCount = $this->fixtureModel->getValue('customers', 0);

        if ($companiesCount * $usersPerCompanyCount > $customersCount) {
            return false;
        }

        return true;
    }

    /**
     * Get website customers.
     *
     * @param int $websiteId
     * @return \Magento\Customer\Api\Data\CustomerInterface[]
     */
    private function getWebsiteCustomers($websiteId)
    {
        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $searchCriteriaBuilder = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(\Magento\Customer\Api\Data\CustomerInterface::WEBSITE_ID, $websiteId)
            ->create();
        return $customerRepository->getList($searchCriteria)->getItems();
    }

    /**
     * Assign customers to company.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $companyAdmin
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function assignCustomersToCompany($companyAdmin)
    {
        $userRolesPerCompany = $this->fixtureModel->getValue('user_roles_per_company', 0);
        $teamsPerCompany = $this->fixtureModel->getValue('teams_per_company', 0);

        if (!$userRolesPerCompany && !$teamsPerCompany) {
            return;
        }

        //Create company admin.
        $this->assignCustomerToCompany($companyAdmin, 1);
        $this->saveSuperUser($companyAdmin->getId());

        //Assign customers to teams and create user roles for them (excluding company admin).
        $requiredCustomersCount = $userRolesPerCompany > $teamsPerCompany ? $userRolesPerCompany : $teamsPerCompany;

        for ($i = 1; $i < $requiredCustomersCount; $i++) {
            $customer = $this->getNextCustomer($companyAdmin->getWebsiteId());
            if ($customer->getWebsiteId() === null) {
                continue;
            }

            $this->assignCustomerToCompany($customer);

            if ($userRolesPerCompany > 0) {
                $this->createUserRole($customer);
                $userRolesPerCompany--;
            }

            if ($teamsPerCompany > 0) {
                $this->createTeam($customer);
                $teamsPerCompany--;
            }
        }
        return $companyAdmin;
    }

    /**
     * Save super user.
     *
     * @param int $customerId
     * @return void
     */
    private function saveSuperUser($customerId)
    {
        /** @var \Magento\Company\Model\Company\Structure $companyStructure */
        $companyStructure = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Model\Company\Structure::class);
        if (!$companyStructure->getStructureByCustomerId($customerId)) {
            $companyStructure->addNode(
                $customerId,
                \Magento\Company\Api\Data\StructureInterface::TYPE_CUSTOMER,
                0
            );
        }
    }

    /**
     * Create user roles for company user.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return void
     */
    private function createUserRole(\Magento\Customer\Api\Data\CustomerInterface $customer)
    {
        /** @var \Magento\Company\Api\Data\RoleInterface[] $roles */
        $defaultPermissions = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Model\PermissionManagementInterface::class)
            ->retrieveDefaultPermissions();
        /** @var \Magento\Company\Api\RoleRepositoryInterface $roleRepository */
        $roleRepository = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Api\RoleRepositoryInterface::class);
        /** @var \Magento\Company\Model\UserRoleManagement $userRoleManagement */
        $userRoleManagement = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Model\UserRoleManagement::class);
        /** @var \Magento\Company\Model\Role $role */
        $role = $this->fixtureModel->getObjectManager()
            ->create(\Magento\Company\Model\Role::class);
        $role->setRoleName('Company Role ' . $customer->getId());
        $role->setCompanyId($this->company->getId());
        $role->setPermissions($defaultPermissions);
        $roleRepository->save($role);
        $userRoleManagement->assignRoles($customer->getId(), [$role]);
    }

    /**
     * Create team for customer.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return void
     */
    private function createTeam(\Magento\Customer\Api\Data\CustomerInterface $customer)
    {
        /** @var \Magento\Company\Model\Company\Structure $structureManager */
        $structureManager = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Model\Company\Structure::class);
        /** @var \Magento\Company\Api\Data\TeamInterface $team */
        $team = $this->fixtureModel->getObjectManager()
            ->create(\Magento\Company\Api\Data\TeamInterface::class);
        $team->setName('Team Name' . $customer->getId());
        $team->setDescription('team description');
        $teamRepository = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Api\TeamRepositoryInterface::class);
        $teamRepository->create($team, $this->company->getId());
        $teamStructure = $structureManager->getStructureByTeamId($team->getId());
        $targetTeamId = $teamStructure->getData(\Magento\Company\Api\Data\StructureInterface::STRUCTURE_ID);
        $structureManager->addNode(
            $customer->getId(),
            \Magento\Company\Api\Data\StructureInterface::TYPE_CUSTOMER,
            $targetTeamId
        );
    }

    /**
     * Assign customer to company.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param int $isSuperUser [optional]
     * @return void
     */
    private function assignCustomerToCompany(
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        $isSuperUser = 0
    ) {
        /** @var \Magento\Company\Model\ResourceModel\Customer $customerResource */
        $customerResource = $this->fixtureModel->getObjectManager()
            ->get(\Magento\Company\Model\ResourceModel\Customer::class);
        /** @var \Magento\Company\Api\Data\CompanyCustomerInterface $customerModel */
        $customerModel = $this->fixtureModel->getObjectManager()
            ->create(\Magento\Company\Api\Data\CompanyCustomerInterface::class);
        $customerModel->setCustomerId($customer->getId());
        $customerModel->setCompanyId($this->company->getId());
        $customerModel->setJobTitle('Job title ' . $customer->getId());
        $customerModel->setStatus(CompanyCustomerInterface::STATUS_ACTIVE);
        $customerModel->setTelephone('2222222');
        $customerResource->saveAdvancedCustomAttributes($customerModel);
        if ($isSuperUser) {
            $this->company->setSuperUserId($customer->getId());
            $this->companyRepository->save($this->company);
        }
    }
}
