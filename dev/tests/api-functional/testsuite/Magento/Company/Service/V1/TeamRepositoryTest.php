<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Company\Service\V1;

use Magento\Company\Api\Data\CompanyInterface;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * Test Team CRUD operations.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TeamRepositoryTest extends WebapiAbstract
{
    const SERVICE_READ_NAME = 'companyTeamRepositoryV1';

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
     * @var \Magento\Framework\Reflection\DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var \Magento\Company\Api\TeamRepositoryInterface
     */
    private $teamRepository;

    /**
     * @var \Magento\Company\Model\Company\Structure
     */
    private $structure;

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
        $this->dataObjectProcessor = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Reflection\DataObjectProcessor::class
        );
        $this->teamRepository = $this->objectManager->get(
            \Magento\Company\Api\TeamRepositoryInterface::class
        );
        $this->structure = $this->objectManager->get(
            \Magento\Company\Model\Company\Structure::class
        );
    }

    /**
     * Test team creation via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testCreateTeam()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());

        $teamFactory = $this->objectManager->get(
            \Magento\Company\Api\Data\TeamInterfaceFactory::class
        );
        /** @var \Magento\Company\Api\Data\TeamInterface $team */
        $team = $teamFactory->create();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . $company->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Create',
            ],
        ];

        $team->setName('Team Name');
        $team->setDescription('Team Description');

        $teamDataObject = $this->dataObjectProcessor->buildOutputDataArray(
            $team,
            \Magento\Company\Api\Data\TeamInterface::class
        );
        $requestData = ['team' => $teamDataObject, 'companyId' => $company->getId()];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEmpty($response);
    }

    /**
     * Test team update via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testUpdateTeam()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $createdTeam = $this->createTeam($company);

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . $createdTeam->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Save',
            ],
        ];

        $createdTeam->setName('Other Name');
        $createdTeam->setDescription('Other Description');

        $teamDataObject = $this->dataObjectProcessor->buildOutputDataArray(
            $createdTeam,
            \Magento\Company\Api\Data\TeamInterface::class
        );
        $requestData = ['team' => $teamDataObject];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue($response);
        $storedTeam = $this->teamRepository->get($createdTeam->getId());
        $this->assertTrue($this->compareTeams($storedTeam, $createdTeam));
    }

    /**
     * Test team delete via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testDeleteTeam()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $createdTeam = $this->createTeam($company);

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . $createdTeam->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'DeleteById',
            ],
        ];
        $requestData = ['teamId' => $createdTeam->getId()];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEmpty($response);
    }

    /**
     * Test delete team with child nodes via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testDeleteTeamWithChildren(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This team has child users or teams aligned to it and cannot be deleted.');

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
        $structureManagement = $this->objectManager->create(
            \Magento\Company\Model\Company\Structure::class
        );

        $team1 = $teamFactory->create();
        $team1->setName('Team 1');
        $teamRepository->create($team1, $company->getId());

        $team2 = $teamFactory->create();
        $team2->setName('Team 2');
        $teamRepository->create($team2, $company->getId());

        $structureTeam1 = $structureManagement->getStructureByTeamId($team1->getId());
        $structureTeam2 = $structureManagement->getStructureByTeamId($team2->getId());
        $structureManagement->moveNode($structureTeam1->getId(), $structureTeam2->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . $team2->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_DELETE,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'DeleteById',
            ],
        ];
        $requestData = ['teamId' => $team2->getId()];
        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * Test get team list via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetTeamList()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $createdTeam = $this->createTeam($company);

        $builder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create(\Magento\Framework\Api\FilterBuilder::class);

        $filter = $builder
            ->setField(\Magento\Company\Api\Data\TeamInterface::NAME)
            ->setValue($createdTeam->getName())
            ->setConditionType('like')
            ->create();

        /** @var \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Api\SearchCriteriaBuilder::class
        );

        $searchCriteriaBuilder->addFilters([$filter]);
        $searchCriteriaBuilder->setCurrentPage(1);
        $searchCriteriaBuilder->setPageSize(20);
        $searchData = $this->dataObjectProcessor->buildOutputDataArray(
            $searchCriteriaBuilder->create(),
            \Magento\Framework\Api\SearchCriteriaInterface::class
        );
        $requestData = ['searchCriteria' => $searchData];

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . '?' . http_build_query($requestData),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'GetList',
            ],
        ];

        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertEquals($response['items'][0]['name'], $createdTeam->getName());
        $this->assertEquals($response['items'][0]['description'], $createdTeam->getDescription());
    }

    /**
     * Test team get via WebAPI.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     */
    public function testGetTeam()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $company = $this->companyManagement->getByCustomerId($customer->getId());
        $createdTeam = $this->createTeam($company);

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/team/' . $createdTeam->getId(),
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'Get',
            ],
        ];
        $requestData = ['teamId' => $createdTeam->getId()];
        $response = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertEquals($response['name'], $createdTeam->getName());
        $this->assertEquals($response['description'], $createdTeam->getDescription());
    }

    /**
     * Creates a team for company.
     *
     * @param CompanyInterface $company
     * @return \Magento\Company\Api\Data\TeamInterface
     */
    private function createTeam(CompanyInterface $company)
    {
        $teamFactory = $this->objectManager->get(
            \Magento\Company\Api\Data\TeamInterfaceFactory::class
        );
        /** @var \Magento\Company\Api\Data\TeamInterface $team */
        $team = $teamFactory->create();
        $team->setName('Team Name');
        $team->setDescription('Team Description');
        $this->teamRepository->create($team, $company->getId());
        return $team;
    }

    /**
     * Returns true if the teams are equal.
     *
     * @param \Magento\Company\Api\Data\TeamInterface $team1
     * @param \Magento\Company\Api\Data\TeamInterface $team2
     * @return bool
     */
    private function compareTeams(
        \Magento\Company\Api\Data\TeamInterface $team1,
        \Magento\Company\Api\Data\TeamInterface $team2
    ) {
        return $team1->getName() == $team2->getName()
            && $team1->getDescription() == $team2->getDescription();
    }
}
