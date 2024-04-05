<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test CreditLimit history operations.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreditHistoryManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCreditCreditHistoryManagementV1';

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
     * @var \Magento\CompanyCredit\Api\CreditBalanceManagementInterface
     */
    private $creditBalanceManagement;

    /**
     * @var \Magento\CompanyCredit\Api\CreditHistoryManagementInterface
     */
    private $creditHistoryManagement;

    /**
     * @var int
     */
    private $amount = 100;

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
        $this->creditBalanceManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\CompanyCredit\Api\CreditBalanceManagementInterface::class
        );
        $this->creditHistoryManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\CompanyCredit\Api\CreditHistoryManagementInterface::class
        );
    }

    /**
     * Test credit history update via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testHistoryUpdate()
    {
        $builder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Framework\Api\FilterBuilder::class);
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $this->creditBalanceManagement->increase(
            $credit->getId(),
            $this->amount,
            'USD',
            \Magento\CompanyCredit\Model\HistoryInterface::TYPE_REIMBURSED
        );

        $filter = $builder
            ->setField(\Magento\CompanyCredit\Model\HistoryInterface::COMPANY_CREDIT_ID)
            ->setValue($credit->getId())
            ->create();
        $this->searchCriteriaBuilder->addFilters([$filter]);

        $historyList = $this->creditHistoryManagement->getList($this->searchCriteriaBuilder->create())->getItems();
        $historyItem = array_shift($historyList);

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/history/' . $historyItem->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Update',
            ],
        ];

        $requestData = [
            'historyId' => $historyItem->getId(),
            'purchaseOrder' => 'string',
            'comment' => 'string'
        ];
        $this->_webApiCall($serviceInfo, $requestData);
        $historyList = $this->creditHistoryManagement->getList($this->searchCriteriaBuilder->create())->getItems();
        $updatedHistoryItem = array_shift($historyList);

        /** @var $updatedHistoryItem \Magento\CompanyCredit\Model\History */
        $this->assertEquals('string', $updatedHistoryItem->getPurchaseOrder());
        $this->assertEquals('string', $updatedHistoryItem->getCustomReferenceNumber());
    }

    /**
     * Test credit history get via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testHistoryGet()
    {
        $builder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Framework\Api\FilterBuilder::class);
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $this->creditBalanceManagement->increase(
            $credit->getId(),
            $this->amount,
            'USD',
            \Magento\CompanyCredit\Model\HistoryInterface::TYPE_REIMBURSED
        );

        $filter = $builder
            ->setField(\Magento\CompanyCredit\Model\HistoryInterface::COMPANY_CREDIT_ID)
            ->setValue($credit->getId())
            ->create();
        $this->searchCriteriaBuilder->addFilters([$filter]);
        $searchData = $this->dataObjectProcessor->buildOutputDataArray(
            $this->searchCriteriaBuilder->create(),
            \Magento\Framework\Api\SearchCriteriaInterface::class
        );
        $requestData = ['searchCriteria' => $searchData];

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/history/' . '?' . http_build_query($requestData),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetList',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertTrue($response['items'][0]['amount'] == $this->amount);
    }
}
