<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\RequisitionList\Model\RequisitionList;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionListItem;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/RequisitionList/_files/list_two.php');

$objectManager = Bootstrap::getObjectManager();
/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->load('list two', 'name');

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder->addFilter('sku', ['item_1', 'item_2'], 'in')->create();
$productList = $productRepository->getList($searchCriteria)->getItems();

$items = [];
foreach ($productList as $product) {
    $items[] = [
        'sku' => $product->getSku(),
        'qty' => 1,
        'options' => [
            'qty' => 1,
            'item' => $product->getId(),
            'product' => $product->getId()
        ]
    ];
}

foreach ($items as $data) {
    /** @var $item RequisitionListItem */
    $item = $objectManager->create(RequisitionListItem::class);
    $item->setRequisitionListId($list->getId());
    $item->setSku($data['sku']);
    $item->setStoreId(1);
    $item->setQty($data['qty']);
    $item->setOptions(['info_buyRequest' => $data['options']]);
    $item->save();
}
