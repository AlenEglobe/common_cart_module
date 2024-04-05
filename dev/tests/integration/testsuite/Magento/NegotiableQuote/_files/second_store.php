<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/** @var \Magento\Store\Model\Store $store */
$store = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
$websiteId = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
    \Magento\Store\Model\StoreManagerInterface::class
)->getWebsite()->getId();
$store->setCode('secondstore')
    ->setWebsiteId($websiteId)
    ->setName('Second Store')
    ->setSortOrder(10)
    ->setIsActive(1);
$store->save();
