<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query\Resolver;

use Magento\Company\Api\Data\StructureInterface;
use Magento\Company\Model\Company\Structure;
use Magento\Company\Model\StructureRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test company team resolver
 */
class CompanyTeamTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $customerAuthenticationHeader;

    /**
     * @var Structure
     */
    private $companyStructure;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StructureRepository
     */
    private $structureRepository;

    /**
     * @var Uid
     */
    private $idEncoder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->companyStructure = $objectManager->get(Structure::class);
        $this->structureRepository = $objectManager->get(StructureRepository::class);
        $this->idEncoder = $objectManager->get(Uid::class);
    }

    /**
     * Test company team
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyTeam(): void
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $structure = $this->companyStructure->getStructureByCustomerId($customer->getId());

        $builder = $this->searchCriteriaBuilder;
        $builder->addFilter(StructureInterface::ENTITY_TYPE, StructureInterface::TYPE_TEAM);
        $builder->addFilter(StructureInterface::PARENT_ID, $structure->getId());
        $results = $this->structureRepository->getList($builder->create())->getItems();
        $team = reset($results);

        $expected = [
            "team" => [
                "description" => "Test team description",
                "name" => "Test team"
            ]
        ];

        $response = $this->executeQuery((string)$team->getEntityId(), 'john.doe@example.com', 'password');
        self::assertSame($response['company'], $expected);
    }

    /**
     * Test access to other company team
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testOtherCompanyTeam(): void
    {
        $customer = $this->customerRepository->get('john.doe@example.com');
        $structure = $this->companyStructure->getStructureByCustomerId($customer->getId());

        $builder = $this->searchCriteriaBuilder;
        $builder->addFilter(StructureInterface::ENTITY_TYPE, StructureInterface::TYPE_TEAM);
        $builder->addFilter(StructureInterface::PARENT_ID, $structure->getId());
        $results = $this->structureRepository->getList($builder->create())->getItems();
        $team = reset($results);

        $response = $this->executeQuery((string)$team->getEntityId(), 'customer@example.com', 'password');
        self::assertNull($response['company']['team']);
    }

    /**
     * Execute query
     *
     * @param string $teamId
     * @param string $email
     * @param string $password
     * @return array|bool|float|int|string
     * @throws AuthenticationException
     */
    private function executeQuery(string $teamId, string $email, string $password)
    {
        $teamId = $this->idEncoder->encode($teamId);

        $query = <<<QUERY
{
  company {
    team (id: "{$teamId}") {
      description
      name
    }
  }
}
QUERY;

        return $this->graphQlQuery(
            $query,
            [],
            '',
            $this->customerAuthenticationHeader->execute($email, $password)
        );
    }
}
