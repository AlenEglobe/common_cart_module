<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\BundleRequisitionList;

use Exception;
use Magento\Bundle\Model\Option;
use Magento\Bundle\Model\Product\Type;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\RequisitionList\GetRequisitionList;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\Ui\Component\Form\Element\Select;

/**
 * Test coverage for adding bundle product to Requisition List
 */
class AddBundleProductToRequisitionListTest extends GraphQlAbstract
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
     * @var ProductRepositoryInterface;
     */
    private $productRepository;

    /**
     * Set Up
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/Bundle/_files/product_1.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     *
     * @throws Exception
     */
    public function testAddBundleProductWithOptions(): void
    {
        $sku = 'bundle-product';
        $product = $this->productRepository->get($sku);
        $qty = 2;
        $optionQty = 1;

        /** @var Type $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $typeInstance->setStoreFilter($product->getStoreId(), $product);
        /** @var Option $option */
        $option = $typeInstance->getOptionsCollection($product)->getFirstItem();
        /** @var Product $selection */
        $selection = $typeInstance->getSelectionsCollection([$option->getId()], $product)->getFirstItem();
        $optionId = $option->getId();
        $selectionId = $selection->getSelectionId();
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $bundleOptions = $this->generateBundleOptionUid((int) $optionId, (int) $selectionId, $optionQty);

        $query = $this->getQuery($sku, $qty, $bundleOptions, $listId);
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
        $this->assertNotEmpty($requisitionListResponse['items']['items'][0]['bundle_options']);
        $bundleOptions = $requisitionListResponse['items']['items'][0]['bundle_options'];
        $this->assertNotEmpty($bundleOptions[0]['label']);
        $this->assertNotEmpty($bundleOptions[0]['values']);
        $this->assertNotEmpty($bundleOptions[0]['id']);
        $this->assertNotEmpty($bundleOptions[0]['type']);
        $this->assertEquals(Select::NAME, $bundleOptions[0]['type']);
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
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
                ... on BundleRequisitionListItem {
                    bundle_options {
                        id
                        type
                        label
                        values {
                            id
                            label
                            quantity
                            price
                        }
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
     * Generate Selected Bundle Option string uid
     *
     * @param int $optionId
     * @param int $selectionId
     * @param int $quantity
     *
     * @return string
     */
    private function generateBundleOptionUid(int $optionId, int $selectionId, int $quantity): string
    {
        return 'selected_options: ["' . base64_encode("bundle/$optionId/$selectionId/$quantity") . '"]';
    }
}
