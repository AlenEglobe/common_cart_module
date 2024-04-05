<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\LocalizedException;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class UpdateTeamTest extends GraphQlAbstract
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
     * Setup
     */
    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $this->objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->teamRepository = $this->objectManager->get(TeamRepositoryInterface::class);
    }

    /**
     * Tests unauthorized access
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is not a company user.');

        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "..."
    }
  ) {
    team {
      id
      name
      description
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Update team by ID
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdate()
    {
        $team = $this->findTeamByName('Team A (level 1)');
        $teamNewName = 'Team A (level 1) - new name';
        $teamNewDescription = 'Level 1 Team - new description';

        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
      name: "{$teamNewName}"
      description: "{$teamNewDescription}"
    }
  ) {
    team {
      id
      name
      description
    }
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        $this->assertNotEmpty($response['updateCompanyTeam']['team']['id']);
        $this->assertEquals(base64_encode($team->getId()), $response['updateCompanyTeam']['team']['id']);

        $updatedTeam = $this->findTeamByName($teamNewName);

        $this->assertEquals($updatedTeam->getName(), $response['updateCompanyTeam']['team']['name']);
        $this->assertEquals($updatedTeam->getDescription(), $response['updateCompanyTeam']['team']['description']);
    }

    /**
     * Attempt unauthorized update of team by ID
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUnauthorizedUpdate()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You are not authorized to update the team.');

        $team = $this->findTeamByName('Team A (level 1)');
        $teamId = base64_encode($team->getId() + 1000);

        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
    }
  ) {
    team {
      name
    }
  }
}
MUTATION;

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
    }

    /**
     * Update team by ID, but don't send name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateWithoutName()
    {
        $team = $this->findTeamByName('Team A (level 1)');
        $teamNewDescription = 'Level 1 Team - new description';

        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
      description: "{$teamNewDescription}"
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
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        $this->assertEquals(base64_encode($team->getId()), $response['updateCompanyTeam']['team']['id']);
        $this->assertEquals($team->getName(), $response['updateCompanyTeam']['team']['name']);
    }

    /**
     * Update team by ID, but don't send description
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateWithoutDescription()
    {
        /** @var TeamInterface $team */
        $team = $this->findTeamByName('Team A (level 1)');
        /** @var string $teamNewName */
        $teamNewName = 'Team A (level 1) - new name';

        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
      name: "{$teamNewName}"
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
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        $this->assertEquals(base64_encode($team->getId()), $response['updateCompanyTeam']['team']['id']);
        $this->assertEquals($team->getDescription(), $response['updateCompanyTeam']['team']['description']);
    }

    /**
     * Update team for given company with empty name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateWithEmptyName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid value of "" provided for the name field.');

        /** @var TeamInterface $team */
        $team = $this->findTeamByName('Team A (level 1)');
        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
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
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
    }

    /**
     * Update team for a given company with too long name
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUpdateWithTooLongName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company team name must not be more than 40 characters');
        $teamName = 'Team with a name that exceeds 40 chars  -all of this is not saved-';

        /** @var TeamInterface $team */
        $team = $this->findTeamByName('Team A (level 1)');
        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  updateCompanyTeam(
    input: {
      id: "{$teamId}"
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
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
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
