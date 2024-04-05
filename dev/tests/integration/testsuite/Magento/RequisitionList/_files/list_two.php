<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Model\RequisitionList;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$customerAccountManagement = $objectManager->get(AccountManagementInterface::class);
$customer = $customerAccountManagement->authenticate('customer@example.com', 'password');

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);

/** @var ProductInterfaceFactory $productInterfaceFactory */
$productInterfaceFactory = $objectManager->create(ProductInterfaceFactory::class);

/** @var Product $product */
$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($product->getDefaultAttributeSetId())
    ->setWebsiteIds([1])
    ->setName('list two item 1')
    ->setSku('item_1')
    ->setPrice(10)
    ->setWeight(1)
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setStockData(
        [
            'qty' => 100,
            'is_in_stock' => 1,
            'manage_stock' => 0,
        ]
    );
$productRepository->save($product);

/** @var Product $product */
$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($product->getDefaultAttributeSetId())
    ->setWebsiteIds([1])
    ->setName('list two item 2')
    ->setSku('item_2')
    ->setPrice(20)
    ->setWeight(1)
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setStockData(
        [
            'qty' => 100,
            'is_in_stock' => 1,
            'manage_stock' => 0,
        ]
    );
$productRepository->save($product);

/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->setName('list two');
$list->setCustomerId($customer->getId());
$list->setDescription('list two description');

/** @var RequisitionListRepositoryInterface $listRepository */
$listRepository = $objectManager->get(RequisitionListRepositoryInterface::class);
$listRepository->save($list);
