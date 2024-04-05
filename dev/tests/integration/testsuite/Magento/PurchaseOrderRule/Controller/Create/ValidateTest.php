<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\Create;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Controller test class for validating purchase order rule name.
 *
 * @see \Magento\PurchaseOrderRule\Controller\Create\Validate
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ValidateTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorderrule/create/validate';

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var RuleRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->session = $objectManager->get(Session::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->ruleRepository = $objectManager->get(RuleRepositoryInterface::class);

        // Enable company functionality at the system level
        $scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
        $scopeConfig->setValue('btob/website_configuration/purchaseorder_enabled', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Check if purchase order rule name is valid for company without purchase order rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Company/_files/company.php
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testRuleNameForCompanyWithoutRules()
    {
        $this->loginCompanyUser('admin@magento.com');
        $this->enablePOForCompany('email@magento.com');
        $response = $this->makeValidationRequest('Test');
        $this->assertTrue($response['isValid']);
    }

    /**
     * Check empty purchase order rule name for company without purchase order rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Company/_files/company.php
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testEmptyRuleNameForCompanyWithoutRules()
    {
        $this->loginCompanyUser('admin@magento.com');
        $this->enablePOForCompany('email@magento.com');
        $response = $this->makeValidationRequest('');
        $this->assertFalse($response['isValid']);
    }

    /**
     * Check if purchase order rule name is valid for company with purchase order rules
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testRuleNameForCompanyWithRules()
    {
        $this->loginCompanyUser('admin@magento.com');
        $this->enablePOForCompany('email@magento.com');
        $response = $this->makeValidationRequest('Test');
        $this->assertTrue($response['isValid']);
    }

    /**
     * Check if purchase order rule name is invalid for company with existent purchase order rule name
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExistentRuleNameForCompanyWithRules()
    {
        $this->loginCompanyUser('admin@magento.com');
        $this->enablePOForCompany('email@magento.com');
        $response = $this->makeValidationRequest('Integration Test Rule Name');
        $this->assertFalse($response['isValid']);
    }

    /**
     * Check if purchase order rule name is valid for company with existent purchase order rule name in another company.
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Company/_files/company_with_custom_role.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExistentRuleNameForDifferentCompanies()
    {
        $this->loginCompanyUser('customrole@company.com');
        $this->enablePOForCompany('email@magento.com');
        $this->enablePOForCompany('customrole@company.com');
        $response = $this->makeValidationRequest('Integration Test Rule Name');
        $this->assertTrue($response['isValid']);
    }

    /**
     * Make request to validation controller.
     *
     * @param string $ruleName
     * @param int|null $ruleId
     * @return array
     */
    private function makeValidationRequest(string $ruleName, int $ruleId = null): array
    {
        $this->getRequest()->setParams([
            'rule_name' => $ruleName,
            'rule_id' => $ruleId
        ]);
        $this->dispatch(self::URI);
        return json_decode($this->getResponse()->getContent(), true);
    }

    /**
     * Login company user by email.
     *
     * @param string $email
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function loginCompanyUser(string $email)
    {
        $companyUser = $this->customerRepository->get($email);
        $this->session->loginById($companyUser->getId());
    }

    /**
     * Enable PO for company.
     *
     * @param $email
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function enablePOForCompany($email)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('company_email', $email)->create();
        $companies = $this->companyRepository->getList($searchCriteria)->getItems();
        $company =  array_pop($companies);
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);
    }
}
