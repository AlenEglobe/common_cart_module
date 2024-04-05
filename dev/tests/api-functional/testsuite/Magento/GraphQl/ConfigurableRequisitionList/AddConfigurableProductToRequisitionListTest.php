<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\ConfigurableRequisitionList;

use Exception;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\RequisitionList\GetRequisitionList;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for adding configurable product to Requisition List
 */
class AddConfigurableProductToRequisitionListTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var RequisitionListRepository
     */
    private $requisitionListRepository;

    /**
     * @var GetRequisitionList
     */
    private $getRequisitionList;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/ConfigurableProduct/_files/product_configurable_12345.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     */
    public function testAddConfigurableProduct(): void
    {
        $product = $this->getConfigurableProductInfo();
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $qty = 2;

        $attributeId = (int) $product['configurable_options'][0]['attribute_id'];
        $valueIndex = $product['configurable_options'][0]['values'][0]['value_index'];

        $selectedConfigurableOptionsQuery = $this->generateSuperAttributeOptionIdForQuery($attributeId, $valueIndex);

        $query = $this->getQuery($product['sku'], $qty, $selectedConfigurableOptionsQuery, $listId);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);

        $this->assertArrayHasKey('addProductsToRequisitionList', $response);
        $this->assertArrayHasKey('requisition_list', $response['addProductsToRequisitionList']);
        $requisitionListResponse = $response['addProductsToRequisitionList']['requisition_list'];
        $this->assertEquals($requisitionList->getId(), base64_decode($requisitionListResponse['uid']));
        $this->assertEquals($requisitionList->getName(), $requisitionListResponse['name']);
        $this->assertEquals(count($requisitionList->getItems()), $requisitionListResponse['items_count']);
        $this->assertEquals($requisitionList->getDescription(), $requisitionListResponse['description']);
        foreach ($requisitionList->getItems() as $item) {
            $this->assertEquals(
                $item->getItemId(),
                base64_decode($requisitionListResponse['items']['items'][0]['uid'])
            );
            $this->assertEquals($item->getQty(), $requisitionListResponse['items']['items'][0]['quantity']);
        }
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     * @throws AuthenticationException
     * @throws Exception
     */
    public function testAddConfigurableProductInvalidSku(): void
    {
        $sku = 'configurable_product';
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $qty = 2;
        $attributeId = 20;
        $valueIndex = 10;
        $selectedConfigurableOptionsQuery = $this->generateSuperAttributeOptionIdForQuery($attributeId, $valueIndex);

        $query = $this->getQuery($sku, $qty, $selectedConfigurableOptionsQuery, $listId);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The SKU was not found.');
        $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
     *
     * @return array
     *
     * @throws AuthenticationException
     */
    private function getHeaderAuthentication(
        string $username = 'customer@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }

    /**
     * Returns GraphQl mutation string
     *
     * @param string $parentSku
     * @param int $qty
     * @param string $customizableOptions
     * @param string $requisitionListId
     * @return string
     */
    private function getQuery(
        string $parentSku,
        int $qty,
        string $customizableOptions,
        string $requisitionListId
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}"
    requisitionListItems: [
      {
        sku: "{$parentSku}"
        quantity: {$qty}
        {$customizableOptions}
      }
    ]
) {
    requisition_list {
        uid
        name
        items_count
        description
        items {
            items {
                uid
                quantity
                ... on ConfigurableRequisitionListItem {
                    configurable_options {
                        id
                        option_label
                        value_id
                        value_label
                    }
                }
            }
        }
    }
  }
}
MUTATION;
    }

    /**
     * Generates Id_v2 for super configurable product super attributes
     *
     * @param int $attributeId
     * @param int $valueIndex
     *
     * @return string
     */
    private function generateSuperAttributeOptionIdForQuery(int $attributeId, int $valueIndex): string
    {
        return 'selected_options: ["' . base64_encode("configurable/$attributeId/$valueIndex") . '"]';
    }

    /**
     * Returns information about testable configurable product retrieved from GraphQl query
     *
     * @return array
     *
     * @throws Exception
     */
    private function getConfigurableProductInfo(): array
    {
        $searchResponse = $this->graphQlQuery($this->getFetchProductQuery('configurable'));

        return current($searchResponse['products']['items']);
    }

    /**
     * Returns GraphQl query for fetching configurable product information
     *
     * @param string $term
     *
     * @return string
     */
    private function getFetchProductQuery(string $term): string
    {
        return <<<QUERY
{
  products(
    search:"{$term}"
    pageSize:1
  ) {
    items {
      sku
      ... on ConfigurableProduct {
        variants {
          product {
            sku
          }
        }
        configurable_options {
          attribute_id
          attribute_code
          id
          label
          position
          product_id
          use_default
          values {
            default_label
            label
            store_label
            use_default_value
            value_index
          }
        }
      }
    }
  }
}
QUERY;
    }
}
