<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

/** @var $objectManager \Magento\TestFramework\ObjectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$taxRuleCodes = [
    'Test Rule US',
];

$taxRuleResource = $objectManager->get(\Magento\Tax\Model\ResourceModel\Calculation\Rule::class);
foreach ($taxRuleCodes as $taxRuleCode) {
    $taxRule = $objectManager->create(\Magento\Tax\Model\Calculation\Rule::class);
    $taxRuleResource->load($taxRule, $taxRuleCode, 'code');
    $taxRuleResource->delete($taxRule);
}

$taxRateCodes = [
    'Test Rate US',
];

$taxRateResource = $objectManager->get(\Magento\Tax\Model\ResourceModel\Calculation\Rate::class);
foreach ($taxRateCodes as $taxRateCode) {
    $taxRate = $objectManager->create(\Magento\Tax\Model\Calculation\Rate::class);
    $taxRateResource->load($taxRate, $taxRateCode, 'code');
    $taxRateResource->delete($taxRate);
}
