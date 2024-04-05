<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test credit limit balance increase and decrease.
 */
class CreditBalanceManagementTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCreditCreditBalanceManagementV1';

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
    private $payLoad = [
        'creditId' => null,
        'value'=> 100,
        'currency'=> 'USD',
        'operationType'=> 2,
        'comment'=> 'comment',
        'options'=> [
            'purchase_order'=> '',
            'order_increment'=> '',
            'currency_display'=> 'USD',
            'currency_base'=> 'USD'
        ]
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
     * Test balance increase via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testBalanceIncrease()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());
        $this->payLoad['creditId'] = $credit->getId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/' . $credit->getId() . '/increaseBalance',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Increase',
            ],
        ];

        $this->_webApiCall($serviceInfo, $this->payLoad);
        $updatedCredit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $this->assertTrue(($updatedCredit->getBalance() - $credit->getBalance()) == $this->payLoad['value']);
    }

    /**
     * Test balance decrease via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testBalanceDecrease()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $credit = $this->creditManagement->getCreditByCompanyId($company->getId());
        $this->payLoad['creditId'] = $credit->getId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/companyCredits/' . $credit->getId() . '/decreaseBalance',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Decrease',
            ],
        ];

        $this->_webApiCall($serviceInfo, $this->payLoad);
        $updatedCredit = $this->creditManagement->getCreditByCompanyId($company->getId());

        $this->assertTrue(($credit->getBalance() - $updatedCredit->getBalance()) == $this->payLoad['value']);
    }
}
