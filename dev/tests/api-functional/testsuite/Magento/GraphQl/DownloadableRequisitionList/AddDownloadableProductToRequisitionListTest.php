<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\DownloadableRequisitionList;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\GraphQl\RequisitionList\GetCustomOptionsWithUidForQueryBySku;
use Magento\GraphQl\RequisitionList\GetRequisitionList;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for adding downloadable product to Requisition List
 */
class AddDownloadableProductToRequisitionListTest extends GraphQlAbstract
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
     * @var GetCustomOptionsWithUidForQueryBySku
     */
    private $getCustomOptionsWithUidForQueryBySku;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $this->objectManager->get(CustomerTokenServiceInterface::class);
        $this->requisitionListRepository = $this->objectManager->get(RequisitionListRepository::class);
        $this->getRequisitionList = $this->objectManager->get(GetRequisitionList::class);
        $this->getCustomOptionsWithUidForQueryBySku = $this->objectManager->get(
            GetCustomOptionsWithUidForQueryBySku::class
        );
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/Downloadable/_files/product_downloadable_with_custom_options.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     */
    public function testAddDownloadableProductWithOptions(): void
    {
        $sku = 'downloadable-product-with-purchased-separately-links';
        $qty = 2;
        $links = $this->getProductsLinks($sku);
        $linkId = key($links);
        $itemOptions = $this->getCustomOptionsWithUidForQueryBySku->execute($sku);
        $itemOptions['selected_options'][] = $this->generateProductLinkSelectedOptions($linkId);
        $productOptionsQuery = preg_replace(
            '/"([^"]+)"\s*:\s*/',
            '$1:',
            json_encode($itemOptions)
        );
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $query = $this->getQuery($sku, $qty, trim($productOptionsQuery, '{}'), $listId);

        $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

        $this->assertArrayHasKey('addProductsToRequisitionList', $response);
        $this->assertArrayHasKey('requisition_list', $response['addProductsToRequisitionList']);

        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $requisitionListResponse = $response['addProductsToRequisitionList']['requisition_list'];
        $this->assertEquals($requisitionList->getId(), base64_decode($requisitionListResponse['uid']));
        $this->assertEquals($requisitionList->getName(), $requisitionListResponse['name']);
        $this->assertEquals(count($requisitionList->getItems()), $requisitionListResponse['items_count']);
        $this->assertEquals($requisitionList->getDescription(), $requisitionListResponse['description']);
        foreach ($requisitionList->getItems() as $item) {
            $this->assertEquals($item->getItemId(), base64_decode($requisitionListResponse['items']['items'][0]['uid']));
            $this->assertEquals($item->getQty(), $requisitionListResponse['items']['items'][0]['quantity']);
        }
        $this->assertNotEmpty($requisitionListResponse['items']['items'][0]['links']);
        $requisitionListTitleResponse = $requisitionListResponse['items']['items'][0]['links'];
        $this->assertEquals('Downloadable Product Link 1', $requisitionListTitleResponse[0]['title']);
        $this->assertNotEmpty($requisitionListResponse['items']['items'][0]['samples']);
        $requisitionListSampleResponse = $requisitionListResponse['items']['items'][0]['samples'];
        $this->assertEquals('Downloadable Product Sample', $requisitionListSampleResponse[0]['title']);
    }

    /**
     * Function returns array of all product's links
     *
     * @param string $sku
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getProductsLinks(string $sku): array
    {
        $result = [];
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $product = $productRepository->get($sku, false, null, true);

        foreach ($product->getDownloadableLinks() as $linkObject) {
            $result[$linkObject->getLinkId()] = [
                'title' => $linkObject->getTitle(),
                'price' => $linkObject->getPrice(),
            ];
        }

        return $result;
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
     * @param string $sku
     * @param int $qty
     * @param string $customizableOptions
     * @param string $requisitionListId
     * @return string
     */
    private function getQuery(
        string $sku,
        int $qty,
        string $customizableOptions,
        string $requisitionListId
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        sku: "{$sku}"
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
        updated_at
        items {
            items {
                uid
                quantity
                ... on DownloadableRequisitionListItem {
                    links {
                        id
                        title
                        sample_url
                    }
                    samples {
                        id
                        title
                        sample_url
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
     * Generates uid for downloadable links
     *
     * @param int $linkId
     *
     * @return string
     */
    private function generateProductLinkSelectedOptions(int $linkId): string
    {
        return base64_encode("downloadable/$linkId");
    }
}
