<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company;

use Laminas\Http\Headers;
use Magento\Backend\Model\Auth;
use Magento\Backend\Model\UrlInterface;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Grid\Collection as CustomerGridCollection;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 */
class CustomerGridCollectionTest extends AbstractController
{
    /**
     * @var CustomerGridCollection
     */
    private $customerGridCollection;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $indexerRegistry = Bootstrap::getObjectManager()->create(IndexerRegistry::class);
        $indexer = $indexerRegistry->get(Customer::CUSTOMER_GRID_INDEXER_ID);
        $indexer->reindexAll();

        $this->customerGridCollection = Bootstrap::getObjectManager()->create(CustomerGridCollection::class);
        $this->url = Bootstrap::getObjectManager()->create(UrlInterface::class);

        parent::setUp();
    }

    /**
     * Test backoffice customer grid provides total result count when filtering by customer type (e.g. company admin,
     * company user, regular customer)
     *
     * Given two storefront non-company customers and a company admin
     * When the backoffice customer grid is filtered by customer type of company admin
     * Then 1 result is returned
     * When the backoffice customer grid is filtered by customer type of company user
     * Then no results are returned
     * When the backoffice customer grid is filtered by customer type of individual (non-company) user
     * Then 2 results are returned
     *
     * @param int $customerType
     * @param int $expectedCount
     * @magentoDataFixture Magento/Customer/_files/two_customers.php
     * @magentoDataFixture Magento/SharedCatalog/_files/assigned_company.php
     * @dataProvider getTotalCountDataProvider
     */
    public function testGetCustomerCountByCustomerType(int $customerType, int $expectedCount)
    {
        $this->customerGridCollection->addFieldToFilter('customer_type', $customerType);
        $count = $this->customerGridCollection->getTotalCount();
        $this->assertEquals($expectedCount, $count);
    }

    /**
     * @return array
     */
    public function getTotalCountDataProvider(): array
    {
        return [
            [
                CompanyCustomerInterface::TYPE_COMPANY_ADMIN,
                1,
            ],
            [
                CompanyCustomerInterface::TYPE_COMPANY_USER,
                0,
            ],
            [
                CompanyCustomerInterface::TYPE_INDIVIDUAL_USER,
                2,
            ],
        ];
    }

    /**
     * Test backoffice customer grid can be filtered by customer type (e.g. company admin, company user, regular
     * customer)
     *
     * Given two storefront non-company customers and a company admin
     * When the backoffice customer grid is filtered by customer type of company admin
     * Then 1 result is returned
     * And that result's email is the company admin user's email
     * When the backoffice customer grid is filtered by customer type of company user
     * Then no results are returned
     * When the backoffice customer grid is filtered by customer type of individual (non-company) user
     * Then 2 results are returned
     * And the results' emails are the 2 non-company customers
     *
     * @param int $customerType
     * @param array $expectedEmails
     * @magentoDataFixture Magento/Customer/_files/two_customers.php
     * @magentoDataFixture Magento/SharedCatalog/_files/assigned_company.php
     * @dataProvider getItemsDataProvider
     */
    public function testGetCustomersByCustomerType(int $customerType, array $expectedEmails)
    {
        $this->customerGridCollection->addFieldToFilter('customer_type', $customerType);
        $items = $this->customerGridCollection->getItems();
        $emails = [];
        foreach ($items as $item) {
            $emails[] = $item->getCustomAttribute('email')->getValue();
        }
        $this->assertSame($expectedEmails, $emails);
    }

    /**
     * @return array
     */
    public function getItemsDataProvider(): array
    {
        return [
            [
                CompanyCustomerInterface::TYPE_COMPANY_ADMIN,
                ['email1@companyquote.com'],
            ],
            [
                CompanyCustomerInterface::TYPE_COMPANY_USER,
                [],
            ],
            [
                CompanyCustomerInterface::TYPE_INDIVIDUAL_USER,
                ['customer@example.com', 'customer_two@example.com'],
            ],
        ];
    }

    /**
     * Test backoffice customer grid can be filtered by Sales Representative usernames
     *
     * Given Company Admin customers with a unique Sales Representative for each customer
     * When the backoffice customer grid is filtered by a Sales Representative's username
     * Then the associated Company Admin customer will appear
     * When the backoffice customer grid is filtered by a nonexistent Sales Representative's username
     * Then an empty result set is returned
     *
     * @param string $salesRepresentativeUsername
     * @param string|null $expectedCompanyAdminEmail
     * @dataProvider getCustomersBySalesRepresentativeUsernameDataProvider
     * @magentoDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
     */
    public function testGetCustomersBySalesRepresentativeUsername(
        string $salesRepresentativeUsername,
        ?string $expectedCompanyAdminEmail = null
    ) {
        $this->customerGridCollection->addFieldToFilter('sales_representative_username', $salesRepresentativeUsername);
        $customerResults = $this->customerGridCollection->getItems();

        if (!is_string($expectedCompanyAdminEmail)) {
            $this->assertCount(0, $customerResults);
        } else {
            $this->assertCount(1, $customerResults);

            $customerResult = array_shift($customerResults);

            $this->assertEquals(
                $expectedCompanyAdminEmail,
                $customerResult->getCustomAttribute('email')->getValue()
            );
        }
    }

    /**
     * @return array
     */
    public function getCustomersBySalesRepresentativeUsernameDataProvider(): array
    {
        return [
            [
                'abby_ADMIN',
                'Company_Admin_Under_Abby@example.com'
            ],
            [
                'Bobby_admin',
                'Company_Admin_Under_Bobby@example.com'
            ],
            [
                'CARLY_admin',
                'Company_Admin_Under_Carly@example.com'
            ],
            [
                'Nobody admin',
                null
            ]
        ];
    }

    /**
     * Test backend customer grid can be sorted by Sales Representative username in alphabetical order
     *
     * Given multiple Sales Representatives whose usernames are not in alphabetical order
     * When the backoffice customer grid is sorted by Sales Representative username ascending
     * Then the associated customer results will be sorted by Sales Representative username alphabetically ascending
     *
     * @param string $sortOrder
     * @param array $expectedSalesRepresentativeUsernames
     * @magentoDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
     * @dataProvider sortingBySalesRepresentativeUsernameDataProvider
     */
    public function testSortingBySalesRepresentativeUsername(
        string $sortOrder,
        array $expectedSalesRepresentativeUsernames
    ) {
        $this->customerGridCollection->setOrder(
            'sales_representative_username',
            $sortOrder
        );

        $customerResults = $this->customerGridCollection->getItems();

        $salesRepresentativeUsernames = array_map(function ($customerResult) {
            return $customerResult->getCustomAttribute('sales_representative_username')->getValue();
        }, $customerResults);

        $salesRepresentativeUsernames = array_values(array_filter(array_unique($salesRepresentativeUsernames)));

        $this->assertEquals($expectedSalesRepresentativeUsernames, $salesRepresentativeUsernames);
    }

    /**
     * @return array
     */
    public function sortingBySalesRepresentativeUsernameDataProvider()
    {
        return [
            [
                CustomerGridCollection::SORT_ORDER_ASC,
                [
                    'Abby_Admin',
                    'Bobby_Admin',
                    'Carly_Admin',
                ]
            ],
            [
                CustomerGridCollection::SORT_ORDER_DESC,
                [
                    'Carly_Admin',
                    'Bobby_Admin',
                    'Abby_Admin',
                ]
            ]
        ];
    }

    /**
     * Test backoffice customer grid can be filtered by company name correctly using exact, partial, and non-existent
     * matches
     *
     * Given Company Admin customers with a unique Company Name for each customer
     * When the backoffice customer grid is filtered by a Company Name
     * Then the associated Company Admin customer will appear
     * When the backoffice customer grid is filtered by partial Company Name
     * Then the associated Company Admin customer will still appear
     * When the backoffice customer grid is filtered by a nonexistent Company Name
     * Then an empty result set is returned
     *
     * @param string $companyName
     * @param string|null $expectedCompanyAdminEmail
     * @magentoDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
     * @dataProvider getCustomersByCompanyNameDataProvider
     */
    public function testGetCustomersByCompanyName(
        string $companyName,
        ?string $expectedCompanyAdminEmail = null
    ) {
        $this->_objectManager->get(Auth::class)->login(
            \Magento\TestFramework\Bootstrap::ADMIN_NAME,
            \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD
        );

        $params = [
            'namespace' => 'customer_listing',
            'filters[company_name]' => $companyName,
            'isAjax' => 1,
            UrlInterface::SECRET_KEY_PARAM_NAME => $this->url->getSecretKey('mui', 'index', 'render'),
        ];

        $this->getRequest()->setHeaders(Headers::fromString('Accept: application/json'));

        $this->dispatch('backend/mui/index/render?' . http_build_query($params));

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $responseBody = json_decode($this->getResponse()->getBody(), true);

        $customerResults = $responseBody['items'];

        if (!is_string($expectedCompanyAdminEmail)) {
            $this->assertCount(0, $customerResults);
        } else {
            $this->assertCount(1, $customerResults);

            $customerResult = array_shift($customerResults);

            $this->assertEquals(
                $expectedCompanyAdminEmail,
                $customerResult['email']
            );
        }
    }

    /**
     * @return array
     */
    public function getCustomersByCompanyNameDataProvider(): array
    {
        return [
            [
                'Company Under Abby',
                'Company_Admin_Under_Abby@example.com'
            ],
            [
                'COMPANY Under bobby',
                'Company_Admin_Under_Bobby@example.com'
            ],
            [
                'CARLY',
                'Company_Admin_Under_Carly@example.com'
            ],
            [
                'Nobody Company',
                null
            ]
        ];
    }
}
