<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyCredit;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\CompanyCredit\Api\CreditLimitManagementInterface;
use Magento\CompanyCredit\Api\Data\CreditLimitInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Http;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Request;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Test Currency Availability for the Company Credit
 *
 * @magentoAppIsolation enabled
 * @magentoAppArea adminhtml
 * @magentoConfigFixture default_store btob/website_configuration/company_active true
 * @magentoConfigFixture default_store payment/companycredit/active true
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CurrencyAvailabilityForCompanyCreditTest extends AbstractBackendController
{
    /**
     * @var CompanyInterface
     */
    private $company;

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->company) {
            $this->deleteCompany($this->company);

            $customerId = (int) $this->company->getSuperUserId();
            $this->deleteCustomerById($customerId);
        }

        parent::tearDown();
    }

    /**
     * Test the availability of credit currency when there's only one base currency across the entire Magento install
     * We're setting catalog/price/scope to \Magento\Store\Model\Store::PRICE_SCOPE_GLOBAL by default
     *
     * @magentoDbIsolation enabled
     * @magentoConfigFixture default/catalog/price/scope 0
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCompanyCreditCurrencyWithOneDistinctBaseCurrencyAcrossOneWebsite()
    {
        $response = $this->getAdminNewCompanyPageResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCompanyCreditCurrencyDefaultAndAvailableOptionsInResponse($response, 'USD', ['USD']);

        // save company without touching company credit data
        $response = $this->getAdminSaveCompanyPageResponse($this->getNewCompanyFormData());
        $this->assertEquals(302, $response->getHttpResponseCode());
        $this->assertEquals('You have created company CompanyCreditCurrencyTest.', $this->getLastSessionMessage());

        $company = $this->company = $this->getCompanyByEmail('email@companycurrency.com');
        $response = $this->getAdminEditCompanyPageResponse($company);
        $this->assertEquals(200, $response->getHttpResponseCode());

        $this->assertCompanyCreditCurrencyDefaultAndAvailableOptionsInResponse($response, 'USD', ['USD']);

        $creditLimit = $this->getCompanyCreditLimitByCompany($company);
        $this->assertEquals('USD', $creditLimit->getCurrencyCode());
    }

    /**
     * Test the availability of credit currency with the addition of a second website with a different base currency
     * All base currencies across all websites should be options for company credit currency
     * We're setting catalog/price/scope to \Magento\Store\Model\Store::PRICE_SCOPE_WEBSITE by default
     *
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/catalog/price/scope 1
     * @magentoDataFixture Magento/Store/_files/second_website_with_base_second_currency.php
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCompanyCreditCurrencyWithTwoDistinctBaseCurrenciesAcrossTwoWebsites(): void
    {
        $response = $this->getAdminNewCompanyPageResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCompanyCreditCurrencyDefaultAndAvailableOptionsInResponse($response, 'USD', ['EUR', 'USD']);

        // save company without touching company credit data
        $response = $this->getAdminSaveCompanyPageResponse($this->getNewCompanyFormData());
        $this->assertEquals(302, $response->getHttpResponseCode());
        $this->assertEquals('You have created company CompanyCreditCurrencyTest.', $this->getLastSessionMessage());

        $company = $this->company = $this->getCompanyByEmail('email@companycurrency.com');
        $response = $this->getAdminEditCompanyPageResponse($company);
        $this->assertEquals(200, $response->getHttpResponseCode());

        $this->assertCompanyCreditCurrencyDefaultAndAvailableOptionsInResponse($response, 'USD', ['EUR', 'USD']);

        $creditLimit = $this->getCompanyCreditLimitByCompany($company);
        $this->assertEquals('USD', $creditLimit->getCurrencyCode());
    }

    /**
     * Get form data for creating a new company
     *
     * @return array
     */
    private function getNewCompanyFormData()
    {
        return [
            'general' => [
                'company_name' => 'CompanyCreditCurrencyTest',
                'company_email' => 'email@companycurrency.com',
                'sales_representative_id' => 1,
                'status' => CompanyInterface::STATUS_APPROVED
            ],
            'address' => [
                'street' => ['6161 West Centinela Avenue'],
                'city' => 'Culver City',
                'postcode' => 90230,
                'country_id' => 'US',
                'region_id' => 12,
                'telephone' => '555-55-555-55'
            ],
            'company_admin' => [
                'firstname' => 'foo',
                'lastname' => 'Bar',
                'email' => 'email@companycurrency.com',
                'gender' => 3,
                'website_id' => 1
            ],
            'company_credit' => [
                'currency_code' => 'USD',
                'credit_limit' => 0,
                'exceed_limit' => false,
                'currency_rate' => ''
            ],
            'settings' => [
                'customer_group_id' => 1
            ]
        ];
    }

    /**
     * Dispatch GET request to new company page in backoffice
     *
     * @return ResponseInterface
     */
    private function getAdminNewCompanyPageResponse()
    {
        $this->resetRequest();
        $this->resetResponse();
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('backend/company/index/new');

        return $this->getResponse();
    }

    /**
     * Dispatch GET request to edit company page in backoffice
     *
     * @param CompanyInterface $company
     * @return ResponseInterface
     */
    private function getAdminEditCompanyPageResponse(CompanyInterface $company)
    {
        $this->resetRequest();
        $this->resetResponse();
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch("backend/company/index/edit/id/{$company->getId()}/");

        return $this->getResponse();
    }

    /**
     * Dispatch POST request to save company page in backoffice with $formData
     *
     * @param array $formData
     * @return ResponseInterface
     */
    private function getAdminSaveCompanyPageResponse(array $formData)
    {
        $this->resetRequest();
        $this->resetResponse();

        $request = $this->getRequest();
        $request->setParams($formData);
        $request->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/company/index/save');

        return $this->getResponse();
    }

    /**
     * Get last session message after dispatching the request
     *
     * @return string
     */
    private function getLastSessionMessage(): string
    {
        /** @var ManagerInterface $messageManager */
        $messageManager = $this->_objectManager->get(ManagerInterface::class);
        $messages = $messageManager->getMessages(true)->getItems();

        $lastMessage = array_pop($messages);

        return $lastMessage->getText();
    }

    /**
     * Get company entity by $email
     *
     * @param string $email
     * @return CompanyInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getCompanyByEmail(string $email): CompanyInterface
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('company_email', $email)->create();

        /** @var CompanyRepositoryInterface $repository */
        $repository = $this->_objectManager->get(CompanyRepositoryInterface::class);
        $items = $repository->getList($searchCriteria)->getItems();

        return array_pop($items);
    }

    /**
     * Get Company Credit Limit by $company
     *
     * @param CompanyInterface $company
     * @return CreditLimitInterface
     */
    private function getCompanyCreditLimitByCompany(CompanyInterface $company): CreditLimitInterface
    {
        /** @var CreditLimitManagementInterface $creditLimitManagement */
        $creditLimitManagement = $this->_objectManager->get(CreditLimitManagementInterface::class);
        return $creditLimitManagement->getCreditByCompanyId($company->getId());
    }

    /**
     * Assert the default currency code and available currency code options for company credit in $response
     * This data is embedded in the UI component payload for company_form
     *
     * @param ResponseInterface $response
     * @param string $expectedDefaultCurrencyCode
     * @param array $expectedAvailableCurrencyCodes
     */
    private function assertCompanyCreditCurrencyDefaultAndAvailableOptionsInResponse(
        ResponseInterface $response,
        string $expectedDefaultCurrencyCode,
        array $expectedAvailableCurrencyCodes
    ) {
        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $domDocument->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($response->getBody());
        libxml_use_internal_errors(false);

        $xpathFinder = new \DOMXPath($domDocument);

        $mageInitScripts = $xpathFinder->query(
            '//script[@type="text/x-magento-init"]'
        );

        $hasCompanyCreditCurrencyConfigBeenFound = false;

        foreach ($mageInitScripts as $mageInitScript) {
            $jsonArr = json_decode($mageInitScript->textContent, true);

            $currencyCodeConfig = $jsonArr['*']
                ['Magento_Ui/js/core/app']
                ['components']
                ['company_form']
                ['children']
                ['company_form']
                ['children']
                ['company_credit']
                ['children']
                ['currency_code']
                ['config'] ?? null;

            if (!$currencyCodeConfig) {
                continue;
            }

            $hasCompanyCreditCurrencyConfigBeenFound = true;

            $actualDefaultCurrencyCode = $currencyCodeConfig['value'];

            $this->assertEquals($expectedDefaultCurrencyCode, $actualDefaultCurrencyCode);

            $actualAvailableCurrencyCodes = array_map(function (array $option) {
                return $option['value'];
            }, $currencyCodeConfig['options']);

            $this->assertEquals($expectedAvailableCurrencyCodes, $actualAvailableCurrencyCodes);

            break;
        }

        if (!$hasCompanyCreditCurrencyConfigBeenFound) {
            $this->fail('Could not find company credit currency config');
        }
    }

    /**
     * Delete company entity
     *
     * @param CompanyInterface $company
     */
    private function deleteCompany(CompanyInterface $company)
    {
        try {
            /** @var CompanyRepositoryInterface $repository */
            $repository = $this->_objectManager->get(CompanyRepositoryInterface::class);
            $repository->delete($company);
        } catch (CouldNotDeleteException $e) {
            // isolation on
        }
    }

    /**
     * Delete customer entity by customer id
     *
     * @param int $customerId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function deleteCustomerById(int $customerId)
    {
        try {
            /** @var CustomerRepositoryInterface $repository */
            $repository = $this->_objectManager->get(CustomerRepositoryInterface::class);
            $repository->deleteById($customerId);
        } catch (NoSuchEntityException $e) {
            // isolation on
        }
    }

    /**
     * Reset request singleton
     */
    protected function resetRequest(): void
    {
        $this->_objectManager->removeSharedInstance(Http::class);
        $this->_objectManager->removeSharedInstance(Request::class);
        parent::resetRequest();
    }

    /**
     * Reset response singleton
     */
    private function resetResponse()
    {
        Bootstrap::getObjectManager()->removeSharedInstance(Response::class);
        Bootstrap::getObjectManager()->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
        parent::setUp(); // log in again as admin
    }
}
