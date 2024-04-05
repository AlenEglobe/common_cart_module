<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\RequisitionList\Model\RequisitionList;
use Magento\TestFramework\Helper\Bootstrap;

/** @var $list RequisitionList */
$list = Bootstrap::getObjectManager()->create(RequisitionList::class);
$list->setName('list name');
$list->setCustomerId(1);
$list->setDescription('list description');
$list->save();
