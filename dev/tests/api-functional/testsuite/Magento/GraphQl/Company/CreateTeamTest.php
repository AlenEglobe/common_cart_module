<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as StructureManager;
use Magento\Company\Model\Company\Structure\Tree;
use Magento\Company\Api\Data\StructureInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class CreateTeamTest extends GraphQlAbstract
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getCustomerAuthenticationHeader;

    /**
     * @var SearchCriteriaBuilder $searchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var TeamRepositoryInterface $teamRespoitory
     */
    private $teamRepository;

    /**
     * @var StructureManager
     */
    private $structureManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Uid
     */
    private $uid;

    /**
     * Setup
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->teamRepository = $this->objectManager->get(TeamRepositoryInterface::class);
        $this->structureManager = $this->objectManager->get(StructureManager::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->uid = $this->objectManager->get(Uid::class);
    }

    /**
     * Test unauthorized access
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "Team Name",
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Create a level 1 team for a given company administrator
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreate()
    {
        $teamName = 'Team C';

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "{$teamName}",
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );

        $this->assertNotEmpty($response['createCompanyTeam']['team']['id']);
        $this->assertEquals($teamName, $response['createCompanyTeam']['team']['name']);
    }

    /**
     * Create a team with empty name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreateWithEmptyName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid value of "" provided for the name field.');

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "",
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );
    }

    /**
     * Create a level 1 team for a given company administrator with too long name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreateWithTooLongName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company team name must not be more than 40 characters');
        $teamName = 'Team with a name that exceeds 40 chars  -all of this is not saved-';

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "{$teamName}",
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );
    }

    /**
     * Create a team for a given company administrator with description
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreateWithDescription()
    {
        $teamName = 'Team C';
        $teamDescription = 'Team C Description';

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "{$teamName}",
      description: "{$teamDescription}"
    }
  ) {
    team {
      id
      description
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );

        $this->assertEquals($teamDescription, $response['createCompanyTeam']['team']['description']);
    }

    /**
     * Create a level 2 team for a given company administrator
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreateWithValidTargetId()
    {
        $teamName = 'Team A-1 (level 2)';
        $targetId = $this->findOneLevelOneStructureId();

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "{$teamName}",
      target_id: "{$targetId}"
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );

        $createdTeam = $this->findTeamByName($response['createCompanyTeam']['team']['name']);

        $this->assertEquals($this->uid->encode($createdTeam->getId()), $response['createCompanyTeam']['team']['id']);

        /** @var StructureInterface $teamStructure */
        $teamStructure = $this->structureManager->getStructureByTeamId($createdTeam->getId());

        $this->assertEquals($targetId, $this->uid->encode($teamStructure->getParentId()));
    }

    /**
     * Attempt to create a level 2 team for a given company administrator with invalid parent id
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCreateWithInvalidTargetId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to create a team from the specified target ID.');

        $teamName = 'Team A-1 (level 2)';
        $targetId = $this->findOneLevelOneStructureId(10);

        $mutation = <<<MUTATION
mutation {
  createCompanyTeam(
    input: {
      name: "{$teamName}",
      target_id: "{$targetId}"
    }
  ) {
    team {
      id
      name
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute()
        );
    }

    /**
     * Find one structure id that is level one below root level
     *
     * @param int $offset
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function findOneLevelOneStructureId(int $offset = 0): string
    {
        /** @var CustomerInterface $customer */
        $customer = $this->customerRepository->get('customer@example.com');
        /** @var Tree $tree */
        $tree = $this->structureManager->getTreeByCustomerId($customer->getId());

        foreach ($tree->getTree()->getNodes() as $node) {
            if ($node->getLevel() == 1) {
                return $this->uid->encode($node->getStructureId() + $offset);
            }
        }

        return $this->uid->encode("0");
    }

    /**
     * Find one team by name
     *
     * @param string $name
     * @return TeamInterface
     * @throws LocalizedException
     */
    private function findTeamByName($name)
    {
        $this->searchCriteriaBuilder->addFilter('name', $name);
        /** @var SearchResults $results */
        $results = $this->teamRepository->getList($this->searchCriteriaBuilder->create());
        /** @var TeamInterface[] $items */
        $items = $results->getItems();
        /** @var TeamInterface $team */
        $team = array_values($items)[0];
        return $team;
    }
}
