<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Model\Company\Config\Repository as ConfigRepository;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test for purchase order config model
 *
 * @see \Magento\PurchaseOrder\Model\Config
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var MutableScopeConfigInterface
     */
    private $mutableScopeConfig;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = $this->objectManager = Bootstrap::getObjectManager();
        $this->config = $objectManager->get(Config::class);
        $this->session = $objectManager->get(Session::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->mutableScopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $this->configRepository = $objectManager->get(ConfigRepository::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
    }

    /**
     * Given a customer that belongs to a company
     *
     * When I check if purchase order is enabled by default
     * I expect purchase order to be inactive for customer
     *
     * When I enable purchase order in default scope
     * Then I expect purchase order to be inactive for customer
     *
     * When I enable purchase order at customer's company level
     * Then I expect purchase order to be active for customer
     *
     * When I disable purchase order in default scope
     * Then I expect purchase order to be inactive for customer
     *
     * When I enable purchase order in default scope
     * And change customer's website
     * Then I expect purchase order to be active for customer
     *
     * When I disable purchase order in customer's website's scope
     * Then I expect purchase order to be inactive for customer
     *
     * When I enable purchase order in customer's website's scope
     * Then I expect purchase order to be active for customer
     *
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testConfigWithLoggedInCompanyUserAndWebsiteScopes()
    {
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        // Enable Purchase Order in default scope
        $this->setPurchaseOrderEnabledStatus(
            true,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $config = $this->config;
        $company = $this->getCompany('email@magento.com');
        $companyAdmin = $this->customerRepository->get('admin@magento.com');

        // first login as customer to hint to CompositeUserContext that $chosenUserContext will be customer session
        $this->loginAsCustomer($companyAdmin);

        // not active by default
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());

        $this->logout();

        // no customer logged in; not active
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());

        $this->loginAsCustomer($companyAdmin);

        // still inactive by default
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($companyAdmin));
        $this->assertFalse($config->isEnabledForCompany($company));

        // Enable Purchase Order in default scope
        $this->setPurchaseOrderEnabledStatus(
            true,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        // still inactive because it needs to be enabled on specific company level
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($companyAdmin));
        $this->assertFalse($config->isEnabledForCompany($company));

        // Enable purchase order for company
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);

        // Assert now active
        $this->assertTrue($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertTrue($config->isEnabledForCustomer($companyAdmin));
        $this->assertTrue($config->isEnabledForCompany($company));

        // Disable purchase order in default scope
        $this->setPurchaseOrderEnabledStatus(
            false,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        // Assert now inactive
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($companyAdmin));
        $this->assertTrue($config->isEnabledForCompany($company));

        // Re-enable Purchase Order in default scope
        $this->setPurchaseOrderEnabledStatus(
            true,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $secondStore = $this->storeManager->getStore('fixture_second_store');
        $secondWebsite = $secondStore->getWebsite();
        $secondWebsiteId = $secondWebsite->getId();
        $secondWebsiteCode = $secondWebsite->getCode();

        // Change customer's website id to that of second website
        $companyAdmin->setWebsiteId($secondWebsiteId);
        $this->customerRepository->save($companyAdmin);

        // Change current store context to that of second website
        $this->storeManager->setCurrentStore($secondStore);

        // Enable B2B Company Features in 2nd website scope
        $this->setB2BFeaturesCompanyActiveStatus(
            true,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        // Assert active
        $this->assertTrue($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertTrue($config->isEnabledForCustomer($companyAdmin));
        $this->assertTrue($config->isEnabledForCompany($company));

        // Disable B2B Company Features in 2nd website scope
        $this->setB2BFeaturesCompanyActiveStatus(
            false,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        // Assert inactive
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($companyAdmin));
        $this->assertFalse($config->isEnabledForCompany($company));

        // Re-enable B2B Company Features in 2nd website scope
        $this->setB2BFeaturesCompanyActiveStatus(
            true,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        // Disable Purchase Order in second website scope
        $this->setPurchaseOrderEnabledStatus(
            false,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        // Assert now inactive
        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($companyAdmin));
        $this->assertTrue($config->isEnabledForCompany($company));

        // Re-enable Purchase Order in second website scope
        $this->setPurchaseOrderEnabledStatus(
            true,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        // Disable B2B company functionality at default level
        $this->setB2BFeaturesCompanyActiveStatus(
            false,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        // Assert still active; second website has B2B Features Company config enabled
        $this->assertTrue($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertTrue($config->isEnabledForCustomer($companyAdmin));
        $this->assertTrue($config->isEnabledForCompany($company));
    }

    /**
     * Given a customer that does not belong to any company
     * When I check if purchase order is enabled
     * Then I expect it to always be false
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     */
    public function testConfigWithCustomerNotBelongingToACompany()
    {
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        $config = $this->config;
        $customer = $this->customerRepository->get('customer@example.com');

        $this->loginAsCustomer($customer);

        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($customer));

        // Enable Purchase Order in default scope
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/purchaseorder_enabled',
            '1',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($customer));

        $secondStore = $this->storeManager->getStore('fixture_second_store');
        $secondWebsite = $secondStore->getWebsite();
        $secondWebsiteId = $secondWebsite->getId();
        $secondWebsiteCode = $secondWebsite->getCode();

        // Change customer's website id to that of second website
        $customer->setWebsiteId($secondWebsiteId);
        $this->customerRepository->save($customer);

        // Change current store context to that of second website
        $this->storeManager->setCurrentStore($secondStore);

        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($customer));

        // Enable Purchase Order explicitly in second website scope
        $this->setPurchaseOrderEnabledStatus(
            true,
            StoreScopeInterface::SCOPE_WEBSITE,
            $secondWebsiteCode
        );

        $this->assertFalse($config->isEnabledForCurrentCustomerAndWebsite());
        $this->assertFalse($config->isEnabledForCustomer($customer));
    }

    /**
     * Login as a customer.
     *
     * @param CustomerInterface $customer
     * @return void
     */
    private function loginAsCustomer(CustomerInterface $customer)
    {
        $this->session->setCustomerDataAsLoggedIn($customer);
    }

    /**
     * Logout of session
     *
     * @return void
     */
    private function logout()
    {
        $this->session->logout();
    }

    /**
     * Get company entity by email.
     *
     * @param string $email
     * @return CompanyInterface
     * @throws LocalizedException
     */
    private function getCompany($email)
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('company_email', $email)->create();

        $companies = $this->companyRepository->getList($searchCriteria)->getItems();

        return array_pop($companies);
    }

    /**
     * Set B2B Features' company active status.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param bool $isActive
     * @param string $scope
     * @param string|null $scopeCode
     */
    private function setB2BFeaturesCompanyActiveStatus(bool $isActive, string $scope, $scopeCode = null)
    {
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/company_active',
            $isActive ? '1' : '0',
            $scope,
            $scopeCode
        );
    }

    /**
     * Set purchase order enabled status.
     *
     * @param bool $isActive
     * @param string $scope
     * @param string|null $scopeCode
     */
    private function setPurchaseOrderEnabledStatus(bool $isActive, string $scope, $scopeCode = null)
    {
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/purchaseorder_enabled',
            $isActive ? '1' : '0',
            $scope,
            $scopeCode
        );
    }
}
