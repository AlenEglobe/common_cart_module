<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\ObjectManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Webapi\Rest\Request;

/**
 * Test company purchase order extension attributes
 */
class PurchaseOrderCompanyRepositoryTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCompanyRepositoryV1';

    const SERVICE_VERSION = 'V1';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->companyManagement = $this->objectManager->get(CompanyManagementInterface::class);
    }

    /**
     * Test company purchase order extension attribute
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetCompany(): void
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $originalData = $company->getData();
        $extensionAttributes = $company->getExtensionAttributes();
        $response = $this->getCompany($company->getId());
        $this->assertArrayHasKey('company_name', $response);
        $this->assertArrayHasKey('company_email', $response);
        $this->assertArrayHasKey('extension_attributes', $response);
        $this->assertArrayHasKey('is_purchase_order_enabled', $response['extension_attributes']);
        $this->assertSame($originalData['company_name'], $response['company_name']);
        $this->assertSame($originalData['company_email'], $response['company_email']);
        $this->assertSame(
            $extensionAttributes->getIsPurchaseOrderEnabled(),
            $response['extension_attributes']['is_purchase_order_enabled']
        );
    }

    /**
     * Get company via WebAPI
     *
     * @param int $companyId
     * @return array|bool|float|int|string
     */
    private function getCompany($companyId)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/company/' . $companyId,
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];

        return $this->_webApiCall($serviceInfo, ['companyId' => $companyId]);
    }
}
