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
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test moving teams and customers within the company tree
 */
class StructureAccessTest extends GraphQlAbstract
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
     * @magentoApiDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoApiDataFixture Magento/Company/_files/roles_edit_denied.php
     * @magentoConfigFixture btob/website_configuration/company_active 1
     */
    public function testCompanyRolesEdit(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have authorization to perform this action.');

        //Given one customer and one team
        $customerAStructure = $this->loadCustomerStructure('alex.smith@example.com');
        $teamBStructure = $this->loadTeamStructure('Test team');

        $treeId = base64_encode($customerAStructure->getId());
        $parentTreeId = base64_encode($teamBStructure->getId());

        $mutation = <<<MUTATION
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

        $this->graphQlMutation(
            $mutation,
            [],
            '',
            $this->getCustomerAuthenticationHeader->execute('veronica.costello@example.com', 'password')
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
     * Find one team by name
     *
     * @param string $name
     * @return TeamInterface
     */
    private function findTeamByName(string $name): TeamInterface
    {
        $this->searchCriteriaBuilder->addFilter('name', $name);
        $results = $this->teamRepository->getList($this->searchCriteriaBuilder->create());
        $items = $results->getItems();
        return array_values($items)[0];
    }
}
