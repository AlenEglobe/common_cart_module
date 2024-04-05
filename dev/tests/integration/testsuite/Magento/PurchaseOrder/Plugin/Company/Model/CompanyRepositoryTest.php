<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Plugin\Company\Model;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\PurchaseOrder\Model\Config as PurchaseOrderConfig;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Test for company repository plugin
 *
 * @see \Magento\PurchaseOrder\Plugin\Company\Model\CompanyRepository
 */
class CompanyRepositoryTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var PurchaseOrderConfig
     */
    private $purchaseOrderConfig;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var MutableScopeConfigInterface
     */
    private $mutableScopeConfig;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->objectManager->get(FilterBuilder::class);
        $this->companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $this->mutableScopeConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        $this->purchaseOrderConfig = $this->objectManager->get(PurchaseOrderConfig::class);
    }

    /**
     * Given the PurchaseOrder module is enabled
     * When I create and save a company
     * Then the Purchase Order configuration for that company is populated with a value of "0" (disabled) by default
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testAfterSave()
    {
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);
        $filter = $this->filterBuilder->setField(\Magento\Company\Api\Data\CompanyInterface::NAME)
            ->setValue('Magento')
            ->create();

        $searchCriteriaBuilder = $this->searchCriteriaBuilder;
        $searchCriteriaBuilder->addFilters([$filter]);
        $searchCriteria = $searchCriteriaBuilder->create();
        $companiesQuery = $this->companyRepository->getList($searchCriteria);
        $companies = array_values($companiesQuery->getItems());

        $company = $companies[count($companies) - 1];

        // trigger plugin with a save
        $this->companyRepository->save($company);

        $this->assertFalse($this->purchaseOrderConfig->isEnabledForCompany($company));

        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);

        $this->assertTrue($this->purchaseOrderConfig->isEnabledForCompany($company));
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
}
