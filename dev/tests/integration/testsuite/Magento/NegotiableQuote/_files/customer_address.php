<?php
/**
 * Customer address fixture with entity_id = 1
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
/** @var \Magento\Customer\Model\Address $customerAddress */
$customerAddress = $objectManager->create(\Magento\Customer\Model\Address::class);
/** @var \Magento\Customer\Model\CustomerRegistry $customerRegistry */
$customerRegistry = $objectManager->get(\Magento\Customer\Model\CustomerRegistry::class);
$customerAddress->isObjectNew(true);
$customerAddress->setData(
    [
        'entity_id' => 1,
        'attribute_set_id' => 2,
        'telephone' => 3468676,
        'postcode' => 75477,
        'country_id' => 'US',
        'city' => 'CityM',
        'company' => 'CompanyName',
        'street' => 'Green str, 67',
        'lastname' => 'Smith',
        'firstname' => 'John',
        'parent_id' => 1,
        'region_id' => 1,
    ]
);
$customerAddress->save();
