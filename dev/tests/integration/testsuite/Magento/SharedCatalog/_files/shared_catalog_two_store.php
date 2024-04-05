<?php
/**
 * Create fixture store with code test
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/shared_catalog.php');

/** @var $store \Magento\Store\Model\Store */
$store = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
if (!$store->load('shared_catalog', 'code')->getId()) {
    $store->setData(
        [
            'code' => 'shared_catalog',
            'website_id' => '1',
            'group_id' => '1',
            'name' => 'Test Store for shared catalog',
            'sort_order' => '0',
            'is_active' => '1',
        ]
    );
    $store->save();
}
