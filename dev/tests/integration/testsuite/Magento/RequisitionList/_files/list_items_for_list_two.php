<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\RequisitionList\Model\RequisitionList;
use Magento\RequisitionList\Model\RequisitionListItem;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/RequisitionList/_files/list_two.php');

$objectManager = Bootstrap::getObjectManager();
/** @var $list RequisitionList */
$list = $objectManager->create(RequisitionList::class);
$list->load('list two', 'name');
$items = [
    [
        'sku' => 'list two item 1',
        'store_id' => 1,
        'qty' => 1,
        'options' => ['3'],
    ],
    [
        'sku' => 'list two item 2',
        'store_id' => 1,
        'qty' => 2,
        'options' => ['5'],
    ],
];

foreach ($items as $data) {
    /** @var $item RequisitionListItem */
    $item = $objectManager->create(RequisitionListItem::class);
    $item->setRequisitionListId($list->getId());
    $item->setSku($data['sku']);
    $item->setStoreId($data['store_id']);
    $item->setQty($data['qty']);
    $item->setOptions($data['options']);
    $item->save();
}
