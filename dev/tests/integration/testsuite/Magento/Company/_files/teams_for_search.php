<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Team;
use Magento\TestFramework\Helper\Bootstrap;

$teams = [
    [
        'name' => 'team 1',
        'description' => 'description 1',
    ],
    [
        'name' => 'team 2',
        'description' => 'description 5',
    ],
    [
        'name' => 'team 3',
        'description' => 'description 4',
    ],
    [
        'name' => 'team 4',
        'description' => 'description 2',
    ],
    [
        'name' => 'team 5',
        'description' => 'description 3',
    ],
];

foreach ($teams as $data) {
    /** @var $team Team */
    $team = Bootstrap::getObjectManager()->create(Team::class);
    $team->setName($data['name']);
    $team->setDescription($data['description']);
    $team->save();
}
