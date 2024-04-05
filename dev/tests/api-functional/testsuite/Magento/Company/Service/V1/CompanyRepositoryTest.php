<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Company\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test Company CRUD operations.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CompanyRepositoryTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCompanyRepositoryV1';

    const SERVICE_VERSION = 'V1';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Company\Api\CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var \Magento\Framework\Api\Search\FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @var \Magento\Framework\Reflection\DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var array
     */
    private $fieldsToCheck = [
        'status',
        'company_name',
        'company_email',
        'comment',
        'customer_group_id',
        'sales_representative_id',
        'super_user_id'
    ];

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(
            \Magento\Customer\Api\CustomerRepositoryInterface::class
        );
        $this->companyManagement = $this->objectManager->get(
            \Magento\Company\Api\CompanyManagementInterface::class
        );
        $this->searchCriteriaBuilder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Api\SearchCriteriaBuilder::class
        );
        $this->sortOrderBuilder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Api\SortOrderBuilder::class
        );
        $this->filterGroupBuilder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Api\Search\FilterGroupBuilder::class
        );
        $this->dataObjectProcessor = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Reflection\DataObjectProcessor::class
        );
    }

    /**
     * Test company create via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testCreateCompany()
    {
        $customer = $this->customerRepository->get('customer@example.com');

        $companyFactory = $this->objectManager->get(
            \Magento\Company\Api\Data\CompanyInterfaceFactory::class
        );
        /** @var \Magento\Company\Api\Data\CompanyInterface $company */
        $company = $companyFactory->create();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];

        $company->setCompanyName('company');
        $company->setStatus(1);
        $company->setCompanyEmail(time() . '@example.com');
        $company->setComment('comment');
        $company->setSuperUserId($customer->getId());
        $company->setSalesRepresentativeId(1);
        $company->setCustomerGroupId(1);
        $company->setCountryId('TV');
        $company->setCity('City');
        $company->setStreet(['avenue, 30']);
        $company->setPostcode('postcode');
        $company->setTelephone('123456');

        $companyDataObject = $this->dataObjectProcessor->buildOutputDataArray(
            $company,
            \Magento\Company\Api\Data\CompanyInterface::class
        );
        $requestData = ['company' => $companyDataObject];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue($this->compare($company, $response));
    }

    /**
     * Test company update via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testUpdateCompany()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/' . $company->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];

        $company->setCompanyName('other company');

        $companyDataObject = $this->dataObjectProcessor->buildOutputDataArray(
            $company,
            \Magento\Company\Api\Data\CompanyInterface::class
        );
        $requestData = ['company' => $companyDataObject];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue($this->compare($company, $response));
    }

    /**
     * Test company get via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCompany()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $response = $this->getCompany($company->getId());
        $this->assertTrue($this->compare($company, $response));
    }

    /**
     * Test company get list via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCompanyList()
    {
        $builder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Framework\Api\FilterBuilder::class);
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());

        $filter = $builder
            ->setField(\Magento\Company\Api\Data\CompanyInterface::COMPANY_EMAIL)
            ->setValue($company->getCompanyEmail())
            ->create();
        $this->searchCriteriaBuilder->addFilters([$filter]);
        $searchData = $this->dataObjectProcessor->buildOutputDataArray(
            $this->searchCriteriaBuilder->create(),
            \Magento\Framework\Api\SearchCriteriaInterface::class
        );
        $requestData = ['searchCriteria' => $searchData];

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/' . '?' . http_build_query($requestData),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetList',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertTrue($this->compare($company, $response['items'][0]));
    }

    /**
     * Compares company object with WebAPI response.
     *
     * @param \Magento\Company\Api\Data\CompanyInterface $company
     * @param array $response
     * @return bool
     */
    private function compare(\Magento\Company\Api\Data\CompanyInterface $company, array $response)
    {
        $originalData = $company->getData();
        $equal = true;
        foreach ($this->fieldsToCheck as $field) {
            if ($response[$field] != $originalData[$field]) {
                $equal = false;
                break;
            }
        }
        return $equal;
    }

    /**
     * Get company via WebAPI.
     *
     * @param int $companyId
     * @return array|bool|float|int|string
     */
    private function getCompany($companyId)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/' . $companyId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];

        return $this->_webApiCall($serviceInfo, ['companyId' => $companyId]);
    }

    /**
     * Test company delete via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testDeleteCompany()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $companyId = $company->getId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/' . $companyId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'DeleteById',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, ['companyId' => $companyId]);
        $this->assertTrue($response);
    }
}
