<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/** @var $objectManager \Magento\TestFramework\ObjectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$defaultCustomerTaxClass = 3;
$defaultProductTaxClass = 2;

$taxRateData = [
    'tax_country_id' => 'DE',
    'tax_region_id' => '*',
    'tax_postcode' => '*',
    'code' => 'Test Rate DE',
    'rate' => '21',
];
$taxRate = $objectManager->create(\Magento\Tax\Model\Calculation\Rate::class)->setData($taxRateData)->save();

$taxRuleData = [
    'code' => 'Test Rule DE',
    'priority' => '0',
    'position' => '0',
    'customer_tax_class_ids' => [$defaultCustomerTaxClass],
    'product_tax_class_ids' => [$defaultProductTaxClass],
    'tax_rate_ids' => [$taxRate->getId()],
    'tax_rates_codes' => [$taxRate->getId() => $taxRate->getCode()],
];

$taxRule = $objectManager->create(\Magento\Tax\Model\Calculation\Rule::class)->setData($taxRuleData)->save();
