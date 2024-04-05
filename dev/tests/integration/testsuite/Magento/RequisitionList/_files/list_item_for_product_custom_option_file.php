<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\Data\RequisitionListItemInterface;
use Magento\RequisitionList\Model\ResourceModel\RequisitionList as RequisitionListResource;
use Magento\RequisitionList\Model\ResourceModel\RequisitionListItem as RequisitionListItemResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/RequisitionList/_files/list.php');
Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_simple_with_custom_file_option.php');

$objectManager = Bootstrap::getObjectManager();
/** @var RequisitionListInterface $requisitionList */
$requisitionList = $objectManager->create(RequisitionListInterface::class);
/** @var RequisitionListResource $requisitionListResource */
$requisitionListResource = $objectManager->create(RequisitionListResource::class);
$requisitionListResource->load($requisitionList, 'list name', 'name');

/** @var RequisitionListItemInterface $requisitionListItem */
$requisitionListItem = $objectManager->create(RequisitionListItemInterface::class);
/** @var RequisitionListItemResource $requisitionListItemResource */
$requisitionListItemResource = $objectManager->create(RequisitionListItemResource::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$product = $productRepository->get('simple_with_custom_file_option', false, null, true);
$optionFile = current($product->getOptions());

$requisitionListItem
    ->setRequisitionListId($requisitionList->getId())
    ->setSku($product->getSku())
    ->setStoreId(1)
    ->setQty(1)
    ->setOptions([
        'info_buyRequest' => [
            'options' => [
                $optionFile->getOptionId() => [
                    'type' => 'image/jpeg',
                    'title' => 'image.jpg',
                    'width' => 90,
                    'height' => 90,
                ]
            ]
        ],
    ]);
$requisitionListItemResource->save($requisitionListItem);
