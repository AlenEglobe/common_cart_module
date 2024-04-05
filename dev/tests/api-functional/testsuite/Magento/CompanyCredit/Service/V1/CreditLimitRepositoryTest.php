<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test CreditLimit CRUD operations.
 */
class CreditLimitRepositoryTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCreditCreditLimitRepositoryV1';

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
     * @var \Magento\CompanyCredit\Api\CreditLimitManagementInterface
     */
    private $creditManagement;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Reflection\DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var array
     */
    private $fieldsToCheck = [
        'company_id',
        'balance',
        'currency_code',
        'exceed_limit'
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
        $this->creditManagement = $this->objectManager->get(
            \Magento\CompanyCredit\Api\CreditLimitManagementInterface::class
        );
        $this->searchCriteriaBuilder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Api\SearchCriteriaBuilder::class
        );
        $this->dataObjectProcessor = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Reflection\DataObjectProcessor::class
        );
    }

    /**
     * Test company credit update via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testUpdateCredit()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/' . $credit->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];

        $credit->setExceedLimit(true);

        $creditDataObject = $this->dataObjectProcessor->buildOutputDataArray(
            $credit,
            \Magento\CompanyCredit\Api\Data\CreditLimitInterface::class
        );
        $requestData = ['creditLimit' => $creditDataObject];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue($credit->getExceedLimit() == $response['exceed_limit']);
    }

    /**
     * Test company credit get list via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCreditList()
    {
        $builder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Framework\Api\FilterBuilder::class);
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $filter = $builder
            ->setField(\Magento\CompanyCredit\Api\Data\CreditLimitInterface::COMPANY_ID)
            ->setValue($company->getId())
            ->create();
        $this->searchCriteriaBuilder->addFilters([$filter]);
        $searchData = $this->dataObjectProcessor->buildOutputDataArray(
            $this->searchCriteriaBuilder->create(),
            \Magento\Framework\Api\SearchCriteriaInterface::class
        );
        $requestData = ['searchCriteria' => $searchData];

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/' . '?' . http_build_query($requestData),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetList',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertTrue($this->compare($credit, $response['items'][0]));
    }

    /**
     * Test credit limit get via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCreditLimit()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());
        $creditId = $credit->getId();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/' . $creditId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, ['creditId' => $creditId]);

        $this->assertTrue($this->compare($credit, $response));
    }

    /**
     * Compares credit object with WebAPI response.
     *
     * @param \Magento\CompanyCredit\Api\Data\CreditLimitInterface $credit
     * @param array $response
     * @return bool
     */
    private function compare(\Magento\CompanyCredit\Api\Data\CreditLimitInterface $credit, array $response)
    {
        $originalData = $credit->getData();
        $equal = true;
        foreach ($this->fieldsToCheck as $field) {
            if ($response[$field] != $originalData[$field]) {
                $equal = false;
                break;
            }
        }
        return $equal;
    }
}
