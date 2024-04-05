<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Controller\Item;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Tests for 'Configure requisition item' Controller.
 *
 * @magentoDataFixture Magento/Customer/_files/customer.php
 * @magentoDataFixture Magento/RequisitionList/_files/products.php
 * @magentoDataFixture Magento/RequisitionList/_files/list_items_for_search.php
 * @magentoAppArea frontend
 */
class ConfigureTest extends AbstractController
{
    /**
     * @var string
     */
    private $urlTemplate = '/requisition_list/item/configure/item_id/%s/id/%s/requisition_id/%s/';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var RequisitionListRepositoryInterface
     */
    private $requisitionListRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);
        $this->requisitionListRepository = $this->_objectManager->get(RequisitionListRepositoryInterface::class);
    }

    /**
     * Check error message appears after dispatching with invalid Product id.
     *
     * @return void
     */
    public function testDispatchWithInvalidProductId(): void
    {
        $invalidProductId = 444;
        $requisitionList = $this->getRequisitionListByName('list name');
        $requisitionListItem = current($requisitionList->getItems());

        $this->dispatch(
            sprintf(
                $this->urlTemplate,
                $requisitionListItem->getId(),
                $invalidProductId,
                $requisitionList->getId()
            )
        );

        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertSessionMessages(
            $this->equalTo([(string)__('Product is not loaded')]),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * Check that Controller successfully dispatches using various HTTP Methods.
     *
     * @param string $httpMethod
     * @return void
     * @dataProvider dispatchWithDifferentHttpMethodsDataProvider
     */
    public function testDispatchWithDifferentHttpMethods(string $httpMethod): void
    {
        $product = $this->productRepository->get('item 1');
        $requisitionList = $this->getRequisitionListByName('list name');
        $requisitionListItem = current($requisitionList->getItems());

        $this->getRequest()->setMethod($httpMethod);
        $this->dispatch(
            sprintf(
                $this->urlTemplate,
                $requisitionListItem->getId(),
                $product->getId(),
                $requisitionList->getId()
            )
        );

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertSessionMessages($this->isEmpty());
    }

    /**
     * DataProvider for testDispatchWithDifferentHttpMethods()
     *
     * @return array
     */
    public function dispatchWithDifferentHttpMethodsDataProvider(): array
    {
        return [
            'dispatch_using_http_get' => [HttpRequest::METHOD_GET],
            'dispatch_using_http_post' => [HttpRequest::METHOD_POST],
        ];
    }

    /**
     * Get Requisition List by name.
     *
     * @param string $listName
     * @return RequisitionListInterface
     */
    private function getRequisitionListByName(string $listName): RequisitionListInterface
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->_objectManager->create(FilterBuilder::class);
        $filter = $filterBuilder->setField(RequisitionListInterface::NAME)->setValue($listName)->create();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter]);
        /** @var RequisitionListInterface[] $list */
        $list = $this->requisitionListRepository->getList($searchCriteriaBuilder->create())->getItems();

        return array_pop($list);
    }
}
