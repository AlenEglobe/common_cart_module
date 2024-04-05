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

class DeleteTeamTest extends GraphQlAbstract
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
  deleteCompanyTeam(
    id: "..."
  ) {
    success
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Delete team by ID
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testDelete()
    {
        $team = $this->findTeamByName('Team A (level 1)');
        $teamId = base64_encode($team->getId());
        $mutation = <<<MUTATION
mutation {
  deleteCompanyTeam(
    id: "{$teamId}"
  ) {
    success
  }
}
MUTATION;

        $response = $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );

        $this->assertNotEmpty($response['deleteCompanyTeam']['success']);
        $this->assertTrue($response['deleteCompanyTeam']['success']);
    }

    /**
     * Attempt unauthorized delete team by ID
     *
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testUnauthorizedDelete()
    {
        $team = $this->findTeamByName('Team A (level 1)');
        $teamId = $team->getId() + 10;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $teamId . '" is incorrect');

        $mutation = <<<MUTATION
mutation {
  deleteCompanyTeam(
    id: "{$teamId}"
  ) {
    success
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
