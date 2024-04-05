<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\TestFramework\Helper\Bootstrap;

$catalogs = [
    [
        'name' => 'catalog 1',
        'description' => 'description 3',
        'customer_group_id' => 1,
        'type' => 1,
        'create_by' => 1,
        'store_id' => 1,
    ],
    [
        'name' => 'catalog 2',
        'description' => 'description 5',
        'customer_group_id' => 1,
        'type' => 1,
        'create_by' => 1,
        'store_id' => 1,
    ],
    [
        'name' => 'catalog 3',
        'description' => 'description 4',
        'customer_group_id' => 1,
        'type' => 1,
        'create_by' => 1,
        'store_id' => 1,
    ],
    [
        'name' => 'catalog 4',
        'description' => 'description 1',
        'customer_group_id' => 1,
        'type' => 1,
        'create_by' => 1,
        'store_id' => 1,
    ],
    [
        'name' => 'catalog 5',
        'description' => 'description 2',
        'customer_group_id' => 2,
        'type' => 1,
        'create_by' => 1,
        'store_id' => 1,
    ],
];

foreach ($catalogs as $data) {
    /** @var $catalog SharedCatalog */
    $catalog = Bootstrap::getObjectManager()->create(SharedCatalog::class);
    $catalog->setName($data['name']);
    $catalog->setDescription($data['description']);
    $catalog->setCustomerGroupId($data['customer_group_id']);
    $catalog->setType($data['type']);
    $catalog->setCreatedBy($data['create_by']);
    $catalog->setStoreId($data['store_id']);
    $catalog->save();
}
