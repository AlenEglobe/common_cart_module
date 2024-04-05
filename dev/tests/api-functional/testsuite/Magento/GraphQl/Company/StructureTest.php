<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company;

use Magento\Company\Api\Data\StructureInterface;
use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Api\TeamRepositoryInterface;
use Magento\Company\Model\Company\Structure as CompanyStructure;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Data\Tree\Node;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test moving teams and customers within the company tree
 */
class StructureTest extends GraphQlAbstract
{
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
     * @var CompanyStructure
     */
    private $companyStructure;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * Setup tests
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getCustomerAuthenticationHeader = $objectManager->get(GetCustomerAuthenticationHeader::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->teamRepository = $objectManager->get(TeamRepositoryInterface::class);
        $this->companyStructure = $objectManager->get(CompanyStructure::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
    }

    /**
     * Test unauthorized access
     */
    public function testUnauthorizedCustomer()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current customer isn\'t authorized.');

        $mutation = <<<MUTATION
mutation {
  updateCompanyStructure(
    input: {
      tree_id: "...",
      parent_tree_id: "..."
    }
  ) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      company_admin {
        email
        firstname
        lastname
        gender
        job_title
      }
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;

        $this->graphQlMutation($mutation);
    }

    /**
     * Move given customer under given team
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testMoveCustomerUnderTeam()
    {
        //Given one customer and one team
        $customerStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $teamStructure = $this->loadTeamStructure('Team A (level 1)');

        //When attempted to move the Customer under the Team
        $mutation = $this->prepareMutation($customerStructure, $teamStructure);
        $response = $this->executeMutation($mutation);

        //Then
        // a) The response should be company
        $this->assertArrayHasKey('company', $response['updateCompanyStructure']);
        // b) The Team should be parent of the customer
        $reloadedCustomerStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $this->assertEquals($teamStructure->getId(), $reloadedCustomerStructure->getParentId());
    }

    /**
     * Move given team under another team
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testMoveTeamUnderTeam()
    {
        //Given two teams
        $teamAStructure = $this->loadTeamStructure('Team A (level 1)');
        $teamBStructure = $this->loadTeamStructure('Team B (level 1)');

        //When attempted to move Team B under Team A
        $mutation = $this->prepareMutation($teamBStructure, $teamAStructure);
        $response = $this->executeMutation($mutation);

        //Then
        // a) The response should be company
        $this->assertArrayHasKey('company', $response['updateCompanyStructure']);
        // b) Team A should be parent of Team B
        $reloadedTeamBStructure = $this->loadTeamStructure('Team B (level 1)');
        $this->assertEquals($teamAStructure->getId(), $reloadedTeamBStructure->getParentId());
    }

    /**
     * Move given customer under another customer
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testMoveCustomerUnderCustomer()
    {
        //Given two customers
        $customerAStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $customerBStructure = $this->loadCustomerStructure('alex.tailor@example.com');

        //When attempted to move Customer B under Customer A
        $mutation = $this->prepareMutation($customerBStructure, $customerAStructure);
        $response = $this->executeMutation($mutation);

        //Then
        // a) The response should be company
        $this->assertArrayHasKey('company', $response['updateCompanyStructure']);
        // b) Customer A should be the parent of Customer B
        $reloadedCustomerBStructure = $this->loadCustomerStructure('alex.tailor@example.com');
        $this->assertEquals($customerAStructure->getId(), $reloadedCustomerBStructure->getParentId());
    }

    /**
     * Move given empty team under given customer
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testMoveEmptyTeamUnderCustomer()
    {
        //Given one customer and one team
        $customerAStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $teamBStructure = $this->loadTeamStructure('Team B (level 1)');

        //When attempted to move the team under the customer
        $mutation = $this->prepareMutation($teamBStructure, $customerAStructure);
        $response = $this->executeMutation($mutation);

        //Then
        // a) The response should be company
        $this->assertArrayHasKey('company', $response['updateCompanyStructure']);
        $reloadedTeamBStructure = $this->loadTeamStructure('Team B (level 1)');
        // b) The customer should be the parent of the team
        $this->assertEquals($customerAStructure->getId(), $reloadedTeamBStructure->getParentId());
    }

    /**
     * Move team with customers under given customer
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testMoveTeamWithCustomersUnderCustomer()
    {
        //Given two customers and one team
        $customerAStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $customerBStructure = $this->loadCustomerStructure('alex.tailor@example.com');
        $teamBStructure = $this->loadTeamStructure('Team B (level 1)');

        //When attempted to
        // a) Move customerB under the team
        $mutationA = $this->prepareMutation($customerBStructure, $teamBStructure);
        $this->executeMutation($mutationA);

        // b) Move the team under customer A
        $mutationB = $this->prepareMutation($teamBStructure, $customerAStructure);
        $this->executeMutation($mutationB);

        //Then customer A should be the parent of the parent of customerB
        $reloadedCustomerBStructure = $this->loadCustomerStructure('alex.tailor@example.com');
        $node = $this->loadTreeNodeById((int)$reloadedCustomerBStructure->getParentId());
        $reloadedTeamBStructure = $this->loadTeamStructureById((int)$node->getEntityId());
        $this->assertEquals($customerAStructure->getId(), $reloadedTeamBStructure->getParentId());
    }

    /**
     * Attempt to move invalid tree id
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testAttemptMoveWithInvalidTreeId()
    {
        //Given one customer and one team
        $customerAStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $teamBStructure = $this->loadTeamStructure('Team B (level 1)');
        $invalidTreeId = $teamBStructure->getId() + 10;

        //Exception is expected to be raised
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "' . $invalidTreeId . '" is incorrect.');

        //When attempted to move the customer under non-existing team
        $mutation = <<<MUTATION
mutation {
  updateCompanyStructure(
    input: {
      tree_id: "{$invalidTreeId}",
      parent_tree_id: "{$customerAStructure->getId()}"
    }
  ) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      company_admin {
        email
        firstname
        lastname
        gender
        job_title
      }
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;

        $this->executeMutation($mutation);
    }

    /**
     * Attempt to move invalid parent tree id
     *
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoApiDataFixture Magento/Company/_files/company_with_teams.php
     */
    public function testAttemptMoveWithInvalidParentTreeId()
    {
        //Given one customer and one team
        $customerAStructure = $this->loadCustomerStructure('veronica.tailor@example.com');
        $teamBStructure = $this->loadTeamStructure('Team B (level 1)');
        $teamBStructureId = base64_encode($teamBStructure->getId());
        $invalidParentTreeId = $customerAStructure->getId() + 10;

        //Exception is expected to be raised
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Value of uid "'.$invalidParentTreeId.'" is incorrect.');

        //When attempted to move the customer under non-existing parent
        $mutation = <<<MUTATION
mutation {
  updateCompanyStructure(
    input: {
      tree_id: "{$teamBStructureId}",
      parent_tree_id: "{$invalidParentTreeId}"
    }
  ) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      company_admin {
        email
        firstname
        lastname
        gender
        job_title
      }
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;

        $this->executeMutation($mutation);
    }

    /**
     * Prepare the mutation
     *
     * @param StructureInterface $movableNodeStructure
     * @param StructureInterface $destinationNodeStructure
     * @return string
     */
    private function prepareMutation(
        StructureInterface $movableNodeStructure,
        StructureInterface $destinationNodeStructure
    ): string {
        $treeId = base64_encode($movableNodeStructure->getId());
        $parentTreeId = base64_encode($destinationNodeStructure->getId());

        return <<<MUTATION
mutation {
  updateCompanyStructure(
    input: {
      tree_id: "{$treeId}",
      parent_tree_id: "{$parentTreeId}"
    }
  ) {
    company {
      id
      email
      name
      legal_name
      vat_tax_id
      reseller_id
      company_admin {
        email
        firstname
        lastname
        gender
        job_title
      }
      legal_address {
        street
        city
        postcode
        country_code
        telephone
        region {
          region_code
          region_id
          region
        }
      }
    }
  }
}
MUTATION;
    }

    /**
     * Execute graphQl mutation
     *
     * @param string $mutation
     * @return array
     */
    private function executeMutation(string $mutation): array
    {
        return $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('customer@example.com', 'password')
        );
    }

    /**
     * Get structure for customer
     *
     * @param string $email
     * @return StructureInterface
     */
    private function loadCustomerStructure(string $email): StructureInterface
    {
        $customer = $this->customerRepository->get($email);
        return $this->companyStructure->getStructureByCustomerId($customer->getId());
    }

    /**
     * Get structure for team
     *
     * @param string $teamName
     * @return StructureInterface
     */
    private function loadTeamStructure(string $teamName): StructureInterface
    {
        $team = $this->findTeamByName($teamName);
        return $this->loadTeamStructureById((int)$team->getId());
    }

    /**
     * Get structure for given team id
     *
     * @param int $teamId
     * @return StructureInterface
     */
    private function loadTeamStructureById(int $teamId): StructureInterface
    {
        return $this->companyStructure->getStructureByTeamId($teamId);
    }

    /**
     * Get tree node for a given id
     *
     * @param int $nodeId
     * @return Node
     */
    private function loadTreeNodeById(int $nodeId): Node
    {
        return $this->companyStructure->getTreeById($nodeId);
    }

    /**
     * Find one team by name
     *
     * @param string $name
     * @return TeamInterface
     */
    private function findTeamByName($name): TeamInterface
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
