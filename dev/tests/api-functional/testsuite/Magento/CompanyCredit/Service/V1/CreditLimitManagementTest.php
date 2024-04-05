<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test CreditLimit get by company id.
 */
class CreditLimitManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCreditCreditLimitManagementV1';

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
    }

    /**
     * Test credit limit get by company id via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCreditLimitByCompanyId()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/company/' . $company->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetCreditByCompanyId',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, ['companyId' => $company->getId()]);

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
