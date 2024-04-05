<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Customer;

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Model\CompanyRepository;
use Magento\Company\Model\RoleManagement;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Website;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDataFixture Magento/Company/_files/company_with_custom_role.php
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateTest extends AbstractController
{
    private const XML_PATH_COMPANY_ACTIVE = 'btob/website_configuration/company_active';
    private const URI = 'company/customer/create';
    private const WEBSITE_RESTRICTION_LP_CACHE_KEY = 'RESTRICTION_LANGING_PAGE_';
    private const WEBSITE_CLOSED_MESSAGE = 'This website is temporarily closed.';
    private const CUSTOMER_SUCCESSFULLY_CREATED_MESSAGE = 'The customer was successfully created.';
    /**
     * @var FormKey
     */
    private $formKey;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;
    /**
     * @var CustomerInterface
     */
    private $customer;
    /**
     * @var MutableScopeConfigInterface
     */
    private $appConfig;
    /**
     * @var CompanyInterface
     */
    private $company;
    /**
     * @var RoleInterface
     */
    private $defaultRole;
    /**
     * @var Config
     */
    private $cache;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formKey = $this->_objectManager->create(FormKey::class);
        $this->session = $this->_objectManager->get(Session::class);
        $this->accountManagement = $this->_objectManager->get(AccountManagementInterface::class);
        $this->appConfig = $this->_objectManager->get(MutableScopeConfigInterface::class);
        $this->cache = $this->_objectManager->get(Config::class);
        $this->customer = $this->accountManagement->authenticate('customrole@company.com', 'password');
        $this->session->setCustomerDataAsLoggedIn($this->customer);
        $this->company = $this->getCompany('customrole@company.com');
        $this->defaultRole = $this->getDefaultRole((int)$this->company->getId());
        $this->appConfig->setValue(self::XML_PATH_COMPANY_ACTIVE, 1, ScopeInterface::SCOPE_WEBSITE);
        $this->cache->save(self::WEBSITE_CLOSED_MESSAGE, self::WEBSITE_RESTRICTION_LP_CACHE_KEY, [Website::CACHE_TAG]);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->appConfig->setValue(self::XML_PATH_COMPANY_ACTIVE, 0, ScopeInterface::SCOPE_WEBSITE);
        $this->cache->remove(self::WEBSITE_RESTRICTION_LP_CACHE_KEY);
        parent::tearDown();
    }

    /**
     * Test storefront company admin can create a new company user with valid form data
     *
     * Given an active storefront company admin
     * When the company admin requests to save a company user in the default role with valid form data
     * Then the company admin receives a 200 response code
     * And the company user is successfully created
     */
    public function testStorefrontCompanyAdminCanCreateNewCompanyUser(): void
    {
        $this->addUser();
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString(self::CUSTOMER_SUCCESSFULLY_CREATED_MESSAGE, $this->getResponse()->getBody());
    }

    /**
     * Test storefront company admin cannot create a new company user with valid form data when website restriction mode
     * is set to "Website Closed"
     *
     * Given an active storefront company admin
     * And General > Website Restrictions > Access Restriction is set to Yes in default store
     * And General > Website Restrictions > Restriction Mode is set to "Website Closed"
     * And General > Website Restrictions > Http Response is set to "503 Service Unavailable"
     * When the company admin requests to save a company user in the default role with valid form data
     * Then the company admin receives a 503 response page
     * And "This website is temporarily closed." is present in the response body
     *
     * @magentoConfigFixture current_store general/restriction/is_active 1
     * @magentoConfigFixture current_store general/restriction/mode 0
     * @magentoConfigFixture current_store general/restriction/http_status 1
     */
    public function testStorefrontCompanyAdminCannotCreateNewCompanyUserWhenWebsiteRestrictionModeIsWebsiteClosed()
    : void
    {
        $this->addUser();
        $this->assertEquals(503, $this->getResponse()->getHttpResponseCode());
        $this->assertEquals(self::WEBSITE_CLOSED_MESSAGE, $this->getResponse()->getBody());
    }

    /**
     * Test storefront company admin can create new company user with valid form data when website restriction mode is
     * set to "Private Sales: Login Only"
     *
     * Given an active storefront company admin
     * And General > Website Restrictions > Access Restriction is set to Yes in default store
     * And General > Website Restrictions > Restriction Mode is set to "Private Sales: Login Only"
     * When the company admin requests to save a company user in the default role with valid form data
     * Then the company admin receives a 200 response code
     * And the company user is successfully created
     *
     * @magentoConfigFixture current_store general/restriction/is_active 1
     * @magentoConfigFixture current_store general/restriction/mode 1
     */
    public function testCompanyAdminCanCreateNewCompanyUserWhenWebsiteRestrictionModeIsPrivateSalesLoginOnly(): void
    {
        $this->addUser();
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString(self::CUSTOMER_SUCCESSFULLY_CREATED_MESSAGE, $this->getResponse()->getBody());
    }

    /**
     * Test storefront company admin can create new company user with valid form data when website restriction mode is
     * set to "Private Sales: Login and Register"
     *
     * Given an active storefront company admin
     * And General > Website Restrictions > Access Restriction is set to Yes in default store
     * And General > Website Restrictions > Restriction Mode is set to "Private Sales: Login and Register"
     * When the company admin requests to save a company user in the default role with valid form data
     * Then the company admin receives a 200 response code
     * And the company user is successfully created
     *
     * @magentoConfigFixture current_store general/restriction/is_active 1
     * @magentoConfigFixture current_store general/restriction/mode 2
     */
    public function testCompanyAdminCanCreateNewCompanyUserWhenWebsiteRestrictionModeIsPrivateSalesLoginAndRegister()
    : void
    {
        $this->addUser();
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString(self::CUSTOMER_SUCCESSFULLY_CREATED_MESSAGE, $this->getResponse()->getBody());
    }

    private function addUser(): void
    {
        $data = [
            'form_key' => $this->formKey->getFormKey(),
            'firstname' => 'James',
            'lastname' => 'Bond',
            'email' => 'james.bond@magento.com',
            'role' => $this->defaultRole->getId(),
            'extension_attributes' => [
                'company_attributes' => [
                    'job_title' => 'Manager',
                    'telephone' => '8003001010',
                    'status' => 1,
                ]
            ],
            'custom_attributes' => [
                'test_attribute_1' => [
                    'attribute_code' => 'test_attribute_1',
                    'value' => 'test_value_1'
                ],
                'test_attribute_2' => [
                    'attribute_code' => 'test_attribute_2',
                    'value' => 'test_value_2'
                ]
            ]
        ];
        $this->getRequest()
            ->setMethod(HttpRequest::METHOD_POST)
            ->setParam('isAjax', true)
            ->setPostValue($data);
        $this->dispatch(self::URI);
    }

    private function getDefaultRole(int $companyId): RoleInterface
    {
        /** @var RoleManagement $roleManagement */
        $roleManagement = $this->_objectManager->get(RoleManagement::class);
        return $roleManagement->getCompanyDefaultRole($companyId);
    }

    private function getCompany(string $email): CompanyInterface
    {
        $searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('company_email', $email)
            ->create();
        /** @var CompanyRepository $companyRepository */
        $companyRepository = $this->_objectManager->get(CompanyRepository::class);
        $searchResult = $companyRepository->getList($searchCriteria);
        $this->assertEquals(1, $searchResult->getTotalCount());
        $items = $searchResult->getItems();
        return reset($items);
    }
}
