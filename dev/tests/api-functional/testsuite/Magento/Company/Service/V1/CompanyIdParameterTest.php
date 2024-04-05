<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Company\Service\V1;

use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyCustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test enforcing company ID.
 */
class CompanyIdParameterTest extends WebapiAbstract
{
    /**
     * @var int|null
     */
    private $createdCustomerId;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $tokenService;

    /**
     * @var DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerDataFactory;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var CompanyCustomerInterfaceFactory
     */
    private $companyFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->dataObjectProcessor = $objectManager->get(DataObjectProcessor::class);
        $this->dataObjectHelper = $objectManager->get(DataObjectHelper::class);
        $this->tokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->customerDataFactory = $objectManager->get(CustomerInterfaceFactory::class);
        $this->customerRepo = $objectManager->get(CustomerRepositoryInterface::class);
        $this->companyFactory = $objectManager->get(CompanyCustomerInterfaceFactory::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        if (!empty($this->createdCustomerId)) {
                $serviceInfo = [
                    'rest' => [
                        'resourcePath' => '/V1/customers/' . $this->createdCustomerId,
                        'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
                    ],
                    'soap' => [
                        'service' => 'customerCustomerRepositoryV1',
                        'serviceVersion' => 'V1',
                        'operation' => 'customerCustomerRepositoryV1DeleteById',
                    ],
                ];
                $response = $this->_webApiCall($serviceInfo, ['customerId' => $this->createdCustomerId]);
                $this->assertTrue($response);
                $this->createdCustomerId = null;
        }
        $this->customerRepo = null;
    }

    /**
     * Test enforcing when creating an account.
     */
    public function testCreateAccount()
    {
        $customerData = [
            CustomerInterface::FIRSTNAME => 'test',
            CustomerInterface::LASTNAME => 'test',
            CustomerInterface::EMAIL => 'companycustomerparam@test.com',
            CustomerInterface::EXTENSION_ATTRIBUTES_KEY => [
                'company_attributes' => [
                    CompanyCustomerInterface::COMPANY_ID => 1,
                    CompanyCustomerInterface::JOB_TITLE => 'worker',
                    CompanyCustomerInterface::TELEPHONE => '5555555',
                    CompanyCustomerInterface::STATUS => 1
                ]
            ]
        ];
        $this->dataObjectHelper->populateWithArray(
            $customer = $this->customerDataFactory->create(),
            $customerData,
            CustomerInterface::class
        );

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/customers',
                'httpMethod' => RestRequest::HTTP_METHOD_POST
            ],
            'soap' => [
                'service' => 'customerAccountManagementV1',
                'operation' => 'customerAccountManagementV1CreateAccount',
                'serviceVersion' => 'V1',
                'token' => ''
            ]
        ];
        $inputCustomer = $this->dataObjectProcessor->buildOutputDataArray($customer, CustomerInterface::class);
        $response = $this->_webApiCall($serviceInfo, ['customer' => $inputCustomer, 'password' => '12345qWerty']);

        $this->assertNotEmpty($response['id']);
        $this->createdCustomerId = (int)$response['id'];
        $this->assertTrue(
            empty($response['extension_attributes'])
            || empty($response['extension_attributes']['company_attributes'])
            || $response['extension_attributes']['company_attributes']['company_id'] == 0
        );
    }

    /**
     * Test enforcing when updating an account.
     *
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testUpdate()
    {
        $customer = $this->customerRepo->get('customer@example.com');
        $token = $this->tokenService->createCustomerAccessToken($customer->getEmail(), 'password');
        /** @var CompanyCustomerInterface $companyAttributes */
        $companyAttributes = $this->companyFactory->create();
        $companyAttributes->setCompanyId(1);
        $companyAttributes->setStatus(1);
        $companyAttributes->setJobTitle('tst');
        $companyAttributes->setTelephone(CompanyCustomerInterface::STATUS_ACTIVE);
        $customer->getExtensionAttributes()->setCompanyAttributes($companyAttributes);

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/customers/me',
                'httpMethod' => RestRequest::HTTP_METHOD_PUT,
                'token' => $token
            ],
            'soap' => [
                'service' => 'customerCustomerRepositoryV1',
                'operation' => 'customerCustomerRepositoryV1SaveSelf',
                'serviceVersion' => 'V1',
                'token' => $token
            ]
        ];
        $inputCustomer = $this->dataObjectProcessor->buildOutputDataArray($customer, CustomerInterface::class);
        $response = $this->_webApiCall($serviceInfo, ['customer' => $inputCustomer]);

        $this->assertNotEmpty($response['id']);
        $this->assertTrue(
            $response['extension_attributes']['company_attributes']['company_id'] == 0
            && $response['extension_attributes']['company_attributes']['job_title'] === 'tst'
        );
    }
}
