<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Helper\Bootstrap;

/**
 * @var \Magento\Tax\Model\ClassModel $taxClass
 */
$taxClass = Bootstrap::getObjectManager()->create(\Magento\Tax\Model\ClassModel::class);
$taxClass->setClassName('Customer Tax Class' . ' ' . time());
$taxClass->setClassType(\Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_CUSTOMER);
$taxClass->save();
