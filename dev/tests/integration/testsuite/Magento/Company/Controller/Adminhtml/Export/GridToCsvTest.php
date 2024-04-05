<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Controller\Adminhtml\Export;

use Laminas\Http\Headers;
use Magento\Backend\Model\Auth;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\Framework\App\ResponseInterface;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 */
class GridToCsvTest extends AbstractBackendController
{
    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->companyRepository = $this->_objectManager->get(CompanyRepositoryInterface::class);

        // Export controller actions prevent download if it's the first requested action after login; set to false
        $auth = $this->_objectManager->get(Auth::class);
        $auth->getAuthStorage()->setIsFirstPageAfterLogin(false);
    }

    /**
     * @magentoDataFixture Magento/Company/_files/companies_for_search_with_all_fields_filled.php
     */
    public function testExportOfAllCompanies()
    {
        ob_start();
        $response = $this->getCsvExportResponse();
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCorrectContentHeadersInResponse($response);

        $csvContents = ob_get_clean();
        $actualRows = $this->mapCsvContentsToArray($csvContents);
        $expectedRows = $this->getExpectedRowsFromCsv();

        $this->assertCount(count($expectedRows), $actualRows);

        foreach ($actualRows as $num => $actualRow) {
            $expectedRow = $expectedRows[$num];

            foreach (array_keys($actualRow) as $key) {
                $this->assertEquals($expectedRow[$key], $actualRow[$key]);
            }
        }
    }

    /**
     * @magentoDataFixture Magento/Company/_files/companies_for_search_with_all_fields_filled.php
     */
    public function testExportOfSelectedCompanies()
    {
        ob_start();
        $companies = $this->getCompaniesCreatedInFixture();

        // select 3rd and 4th company in set
        $selectedCompanyIds = [$companies[3]->getId(), $companies[4]->getId()];

        $response = $this->getCsvExportResponse($selectedCompanyIds);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCorrectContentHeadersInResponse($response);

        $csvContents = ob_get_clean();
        $actualRows = $this->mapCsvContentsToArray($csvContents);

        $expectedRows = array_slice($this->getExpectedRowsFromCsv(), 3, 2); // expect 3rd and 4th company only

        $this->assertCount(count($expectedRows), $actualRows);

        foreach ($actualRows as $num => $actualRow) {
            $expectedRow = $expectedRows[$num];

            foreach (array_keys($actualRow) as $key) {
                $this->assertEquals($expectedRow[$key], $actualRow[$key]);
            }
        }
    }

    /**
     * @param array $selectedCompanyIds
     * @return ResponseInterface
     */
    private function getCsvExportResponse(array $selectedCompanyIds = [])
    {
        if (count($selectedCompanyIds)) {
            $this->getRequest()->setParams([
                'selected' => $selectedCompanyIds
            ]);
        }

        $this->dispatch('backend/mui/export/gridToCsv/?namespace=company_listing');

        $response = $this->getResponse();

        $this->assertEquals(200, $response->getHttpResponseCode());

        return $response;
    }

    /**
     * @param ResponseInterface $response
     */
    private function assertCorrectContentHeadersInResponse(ResponseInterface $response)
    {
        /** @var Headers $headers */
        $headers = $response->getHeaders();
        $headersArr = $headers->toArray();

        $this->assertEquals('application/octet-stream', $headersArr['Content-Type']);
        $this->assertEquals('attachment; filename="export.csv"', $headersArr['Content-Disposition']);
    }

    /**
     * @return CompanyInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getCompaniesCreatedInFixture()
    {
        $filterBuilder = $this->_objectManager->create(FilterBuilder::class);
        $searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);

        $filters = [];

        for ($i = 1; $i <= 5; ++$i) {
            $filters[] = $filterBuilder->setField(CompanyInterface::NAME)
                ->setValue("company $i")
                ->create();
        }

        $searchCriteriaBuilder->addFilters($filters);
        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->companyRepository->getList($searchCriteria);

        return array_values($searchResult->getItems());
    }

    /**
     * @param string $csvContents
     * @return array
     */
    private function mapCsvContentsToArray(string $csvContents)
    {
        $rows = str_getcsv($csvContents, "\n");
        $rows = array_map('str_getcsv', $rows);

        $headerColumnNamesRow = array_shift($rows);

        $rows = array_map(function ($row) use ($headerColumnNamesRow) {
            return array_combine($headerColumnNamesRow, $row);
        }, $rows);

        return $rows;
    }

    /**
     * @return \string[][]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function getExpectedRowsFromCsv()
    {
        $companies = $this->getCompaniesCreatedInFixture();

        $expectedData = [
            [
                'Company Name' => 'company 1',
                'Status' => 'Active',
                'Company Legal Name' => 'legal name 1',
                'Company Email' => 'company1@example.com',
                'VAT/TAX ID' => 'vat tax id 1',
                'Reseller ID' => 'reseller id 1',
                'Comment' => 'comment 1',
                'Phone Number' => 'telephone 1',
                'Country' => 'Andorra',
                'State/Province' => 'Alabama',
                'ZIP' => 'postcode 1',
                'City' => 'city 1',
                'Group/Shared Catalog' => 'Shared CatalogsDefault (General)',
                'Street Address' => 'street 1',
                'Company Admin' => 'First Name 1 Last Name 1',
                'Job Title' => 'Job Title 1',
                'Email' => 'customer1@example.com',
                'Gender' => 'Female',
                'Credit Currency' => 'USD',
                'Outstanding Balance' => '0.0000',
                'Credit Limit' => '',
            ],
            [
                'Company Name' => 'company 2',
                'Status' => 'Active',
                'Company Legal Name' => 'legal name 2',
                'Company Email' => 'company2@example.com',
                'VAT/TAX ID' => 'vat tax id 2',
                'Reseller ID' => 'reseller id 2',
                'Comment' => 'comment 2',
                'Phone Number' => 'telephone 2',
                'Country' => 'United Arab Emirates',
                'State/Province' => 'Alaska',
                'ZIP' => 'postcode 2',
                'City' => 'city 2',
                'Group/Shared Catalog' => 'Shared CatalogsDefault (General)',
                'Street Address' => 'street 2',
                'Company Admin' => 'First Name 2 Last Name 2',
                'Job Title' => 'Job Title 2',
                'Email' => 'customer2@example.com',
                'Gender' => 'Not Specified',
                'Credit Currency' => 'USD',
                'Outstanding Balance' => '0.0000',
                'Credit Limit' => '',
            ],
            [
                'Company Name' => 'company 3',
                'Status' => 'Active',
                'Company Legal Name' => 'legal name 3',
                'Company Email' => 'company3@example.com',
                'VAT/TAX ID' => 'vat tax id 3',
                'Reseller ID' => 'reseller id 3',
                'Comment' => 'comment 3',
                'Phone Number' => 'telephone 3',
                'Country' => 'Afghanistan',
                'State/Province' => 'American Samoa',
                'ZIP' => 'postcode 3',
                'City' => 'city 3',
                'Group/Shared Catalog' => 'Shared CatalogsDefault (General)',
                'Street Address' => 'street 3',
                'Company Admin' => 'First Name 3 Last Name 3',
                'Job Title' => 'Job Title 3',
                'Email' => 'customer3@example.com',
                'Gender' => 'Male',
                'Credit Currency' => 'USD',
                'Outstanding Balance' => '0.0000',
                'Credit Limit' => '',
            ],
            [
                'Company Name' => 'company 4',
                'Status' => 'Active',
                'Company Legal Name' => 'legal name 4',
                'Company Email' => 'company4@example.com',
                'VAT/TAX ID' => 'vat tax id 4',
                'Reseller ID' => 'reseller id 4',
                'Comment' => 'comment 4',
                'Phone Number' => 'telephone 4',
                'Country' => 'Antigua & Barbuda',
                'State/Province' => 'Arizona',
                'ZIP' => 'postcode 4',
                'City' => 'city 4',
                'Group/Shared Catalog' => 'Shared CatalogsDefault (General)',
                'Street Address' => 'street 4',
                'Company Admin' => 'First Name 4 Last Name 4',
                'Job Title' => 'Job Title 4',
                'Email' => 'customer4@example.com',
                'Gender' => 'Female',
                'Credit Currency' => 'USD',
                'Outstanding Balance' => '0.0000',
                'Credit Limit' => '',
            ],
            [
                'Company Name' => 'company 5',
                'Status' => 'Rejected',
                'Company Legal Name' => 'legal name 5',
                'Company Email' => 'company5@example.com',
                'VAT/TAX ID' => 'vat tax id 5',
                'Reseller ID' => 'reseller id 5',
                'Comment' => 'comment 5',
                'Phone Number' => 'telephone 5',
                'Country' => 'Anguilla',
                'State/Province' => 'Arkansas',
                'ZIP' => 'postcode 5',
                'City' => 'city 5',
                'Group/Shared Catalog' => 'Shared CatalogsDefault (General)',
                'Street Address' => 'street 5',
                'Company Admin' => 'First Name 5 Last Name 5',
                'Job Title' => 'Job Title 5',
                'Email' => 'customer5@example.com',
                'Gender' => 'Not Specified',
                'Credit Currency' => 'USD',
                'Outstanding Balance' => '0.0000',
                'Credit Limit' => '',
            ],
        ];

        foreach ($expectedData as $key => &$expectedCompany) {
            $expectedCompany['ID'] = $companies[$key]->getId();
        }

        return $expectedData;
    }
}
