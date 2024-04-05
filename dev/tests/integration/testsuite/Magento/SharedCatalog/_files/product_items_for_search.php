<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\SharedCatalog\Model\ProductItem;
use Magento\TestFramework\Helper\Bootstrap;

$items = [
    [
        'customer_group_id' => 1,
        'sku' => 'sku 1',
    ],
    [
        'customer_group_id' => 1,
        'sku' => 'sku 2',
    ],
    [
        'customer_group_id' => 1,
        'sku' => 'sku 3',
    ],
    [
        'customer_group_id' => 1,
        'sku' => 'sku 4',
    ],
    [
        'customer_group_id' => 0,
        'sku' => 'sku 5',
    ],
];

foreach ($items as $data) {
    /** @var $item ProductItem */
    $item = Bootstrap::getObjectManager()->create(ProductItem::class);
    $item->setCustomerGroupId($data['customer_group_id']);
    $item->setSku($data['sku']);
    $item->save();
}
