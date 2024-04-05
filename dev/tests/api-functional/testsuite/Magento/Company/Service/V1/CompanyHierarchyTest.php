<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Company\Service\V1;

use Magento\Company\Api\Data\CompanyCustomerInterfaceFactory;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test Company hierarchy operations.
 */
class CompanyHierarchyTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyCompanyHierarchyV1';

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
    }

    /**
     * Test company hierarchy get via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testHierarchyGet()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/hierarchy/' . $company->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetCompanyHierarchy',
            ],
        ];
        $requestData = ['id' => $company->getId()];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEquals($response[0]['structure_parent_id'], 0);
        $this->assertEquals($response[0]['entity_id'], $customer->getId());
        $this->assertEquals($response[0]['entity_type'], 'customer');
    }

    /**
     * Test company hierarchy move via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testHierarchyMove()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());

        /** @var \Magento\Company\Api\TeamRepositoryInterface $teamRepository */
        $teamRepository = $this->objectManager->get(
            \Magento\Company\Api\TeamRepositoryInterface::class
        );

        /** @var \Magento\Company\Api\Data\TeamInterfaceFactory $teamFactory */
        $teamFactory = $this->objectManager->get(
            \Magento\Company\Api\Data\TeamInterfaceFactory::class
        );

        /** @var \Magento\Company\Model\Company\Structure $structureManagement */
        $structureManagement = $this->objectManager->get(
            \Magento\Company\Model\Company\Structure::class
        );

        $team1 = $teamFactory->create();
        $team1->setName('Team 1');
        $teamRepository->create($team1, $company->getId());

        $team2 = $teamFactory->create();
        $team2->setName('Team 2');
        $teamRepository->create($team2, $company->getId());

        $structure1 = $structureManagement->getStructureByTeamId($team1->getId());
        $structure2 = $structureManagement->getStructureByTeamId($team2->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/hierarchy/move/' . $structure1->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'MoveNode',
            ],
        ];

        $requestData = [
            'id' => $structure1->getId(),
            'newParentId' => $structure2->getId()
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEmpty($response);
    }

    /**
     * Test customer move into the team via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Customer/_files/customer_with_website.php
     */
    public function testCustomerMove(): void
    {
        $customerAdmin = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customerAdmin->getId());

        $companyCustomerAttributesFactory = $this->objectManager->get(CompanyCustomerInterfaceFactory::class);
        $companyAttributes = $companyCustomerAttributesFactory->create();
        $companyAttributes->setCompanyId($company->getId());

        $extensionAttributes = $this->objectManager->get(\Magento\Customer\Api\Data\CustomerExtension::class);
        $extensionAttributes->setCompanyAttributes($companyAttributes);

        $customer = $this->customerRepository->get('john.doe@magento.com');
        $customer->getExtensionAttributes()->setCompanyAttributes($companyAttributes);
        $this->customerRepository->save($customer);

        /** @var \Magento\Company\Api\TeamRepositoryInterface $teamRepository */
        $teamRepository = $this->objectManager->get(
            \Magento\Company\Api\TeamRepositoryInterface::class
        );
        /** @var \Magento\Company\Api\Data\TeamInterfaceFactory $teamFactory */
        $teamFactory = $this->objectManager->get(
            \Magento\Company\Api\Data\TeamInterfaceFactory::class
        );
        /** @var \Magento\Company\Model\Company\Structure $structureManagement */
        $structureManagement = $this->objectManager->get(
            \Magento\Company\Model\Company\Structure::class
        );

        $team = $teamFactory->create();
        $team->setName('Team 2');
        $teamRepository->create($team, $company->getId());
        $customer = $this->customerRepository->get('john.doe@magento.com');

        $structureCustomer = $structureManagement->getStructureByCustomerId($customer->getId());
        $structureTeam = $structureManagement->getStructureByTeamId($team->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/hierarchy/move/' . $structureCustomer->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'MoveNode',
            ],
        ];

        $requestData = [
            'id' => $structureCustomer->getId(),
            'newParentId' => $structureTeam->getId(),
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEmpty($response);

        $updatedStructure = $structureManagement->getStructureByCustomerId($customer->getId());
        $this->assertEquals($structureTeam->getId(), $updatedStructure->getParentId());
    }
}
