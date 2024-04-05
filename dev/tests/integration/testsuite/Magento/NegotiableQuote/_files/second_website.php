<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/** @var \Magento\Store\Model\Website $website */
$website = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Store\Model\Website::class);
$website->setName('Second Website')->setCode('secondwebsite')->save();

$websiteId = $website->getId();

/** @var \Magento\Store\Model\Store $store */
$store = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
$store->setCode('secondwebsitestore')
    ->setWebsiteId($websiteId)
    ->setName('Second Website Store')
    ->setSortOrder(10)
    ->setIsActive(1);
$store->save();
