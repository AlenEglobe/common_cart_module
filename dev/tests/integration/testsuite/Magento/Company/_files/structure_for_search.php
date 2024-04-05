<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Structure;
use Magento\TestFramework\Helper\Bootstrap;

$items = [
    [
        'parent_id' => 0,
        'path' => 'item 1',
        'level' => 0,
        'position' => 0,
    ],
    [
        'parent_id' => 0,
        'path' => 'item 2',
        'level' => 0,
        'position' => 5,
    ],
    [
        'parent_id' => 0,
        'path' => 'item 3',
        'level' => 0,
        'position' => 3,
    ],
    [
        'parent_id' => 0,
        'path' => 'item 4',
        'level' => 0,
        'position' => 2,
    ],
    [
        'parent_id' => 0,
        'path' => 'item 5',
        'level' => 1,
        'position' => 4,
    ],
];

foreach ($items as $data) {
    /** @var $item Structure */
    $item = Bootstrap::getObjectManager()->create(Structure::class);
    $item->setParentId($data['parent_id']);
    $item->setPath($data['path']);
    $item->setLevel($data['level']);
    $item->setPosition($data['position']);
    $item->save();
}
