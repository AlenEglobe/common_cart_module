<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\User\Model\User;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_rollback.php');

$salesRepUsernames = [
    'Abby_Admin',
    'Carly_Admin',
    'Bobby_Admin',
];

foreach ($salesRepUsernames as $salesRepUsername) {
    $user = Bootstrap::getObjectManager()->create(User::class);
    $user->load($salesRepUsername, 'username');
    $user->delete();
}
