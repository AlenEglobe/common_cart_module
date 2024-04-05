<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\RequisitionList\Model\RequisitionList;
use Magento\TestFramework\Helper\Bootstrap;

$lists = [
    [
        'name' => 'list 1',
        'customer_id' => 1,
        'description' => 'description 3',
    ],
    [
        'name' => 'list 2',
        'customer_id' => 1,
        'description' => 'description 2',
    ],
    [
        'name' => 'list 3',
        'customer_id' => 1,
        'description' => 'description 5',
    ],
    [
        'name' => 'list 4',
        'customer_id' => 1,
        'description' => 'description 1',
    ],
    [
        'name' => 'list 5',
        'customer_id' => 2,
        'description' => 'description 4',
    ],
];

foreach ($lists as $data) {
    /** @var $list RequisitionList */
    $list = Bootstrap::getObjectManager()->create(RequisitionList::class);
    $list->setName($data['name']);
    $list->setCustomerId($data['customer_id']);
    $list->setDescription($data['description']);
    $list->save();
}
